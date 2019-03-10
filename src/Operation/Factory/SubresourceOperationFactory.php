<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Core\Operation\Factory;

use ApiPlatform\Core\Bridge\Symfony\Routing\RouteNameGenerator;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Core\Metadata\Property\SubresourceMetadata;
use ApiPlatform\Core\Metadata\Resource\Factory\OperationResourceMetadataFactory;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\Operation\PathSegmentNameGeneratorInterface;

/**
 * @internal
 */
final class SubresourceOperationFactory implements SubresourceOperationFactoryInterface
{
    const SUBRESOURCE_SUFFIX = '_subresource';
    const FORMAT_SUFFIX = '.{_format}';
    const ROUTE_OPTIONS = ['defaults' => [], 'requirements' => [], 'options' => [], 'host' => '', 'schemes' => [], 'condition' => '', 'controller' => null];

    private $resourceMetadataFactory;
    private $propertyNameCollectionFactory;
    private $propertyMetadataFactory;
    private $pathSegmentNameGenerator;
    private $formats;

    public function __construct(ResourceMetadataFactoryInterface $resourceMetadataFactory, PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, PathSegmentNameGeneratorInterface $pathSegmentNameGenerator, array $formats = [])
    {
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->propertyNameCollectionFactory = $propertyNameCollectionFactory;
        $this->propertyMetadataFactory = $propertyMetadataFactory;
        $this->pathSegmentNameGenerator = $pathSegmentNameGenerator;
        $this->formats = $formats;
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $resourceClass): array
    {
        $tree = [];
        $this->computeSubresourceOperations($resourceClass, $tree);

        return $tree;
    }

    /**
     * Handles subresource operations recursively and declare their corresponding routes.
     *
     * @param string $rootResourceClass null on the first iteration, it then keeps track of the origin resource class
     * @param array  $parentOperation   the previous call operation
     * @param int    $depth             the number of visited
     * @param int    $maxDepth
     */
    private function computeSubresourceOperations(string $resourceClass, array &$tree, string $rootResourceClass = null, array $parentOperation = null, array $visited = [], int $depth = 0, int $maxDepth = null)
    {
        if (null === $rootResourceClass) {
            $rootResourceClass = $resourceClass;
        }

        foreach ($this->propertyNameCollectionFactory->create($resourceClass) as $property) {
            $propertyMetadata = $this->propertyMetadataFactory->create($resourceClass, $property);

            if (!$propertyMetadata->hasSubresource()) {
                continue;
            }

            $subresource = $propertyMetadata->getSubresource();
            $subresourceClass = $subresource->getResourceClass();
            $subresourceMetadata = $this->resourceMetadataFactory->create($subresourceClass);
            $subresourceMetadata = OperationResourceMetadataFactory::populateOperations($subresourceClass, $subresourceMetadata, $this->formats);
            $isLastItem = $resourceClass === $parentOperation['resource_class'] && $propertyMetadata->isIdentifier();

            // A subresource that is also an identifier can't be a start point
            if ($isLastItem && (null === $parentOperation || false === $parentOperation['collection'])) {
                continue;
            }

            $visiting = "$resourceClass $property $subresourceClass";

            // Handle maxDepth
            if ($rootResourceClass === $resourceClass) {
                $maxDepth = $subresource->getMaxDepth();
                // reset depth when we return to rootResourceClass
                $depth = 0;
            }

            if (null !== $maxDepth && $depth >= $maxDepth) {
                break;
            }
            if (isset($visited[$visiting])) {
                continue;
            }

            $rootResourceMetadata = $this->resourceMetadataFactory->create($rootResourceClass);

            foreach ($subresourceMetadata->getCollectionOperations() as $operationName => $operation) {
                $this->populateOperation(
                    $operationName,
                    $operation,
                    $resourceClass,
                    $tree,
                    $rootResourceClass,
                    $parentOperation,
                    $visited,
                    $depth,
                    $maxDepth,
                    $property,
                    $subresource,
                    $subresourceClass,
                    $subresourceMetadata,
                    $rootResourceMetadata,
                    $isLastItem,
                    $visiting
                );
            }

            foreach ($subresourceMetadata->getItemOperations() as $operationName => $operation) {
                $this->populateOperation(
                    $operationName,
                    $operation,
                    $resourceClass,
                    $tree,
                    $rootResourceClass,
                    $parentOperation,
                    $visited,
                    $depth,
                    $maxDepth,
                    $property,
                    $subresource,
                    $subresourceClass,
                    $subresourceMetadata,
                    $rootResourceMetadata,
                    $isLastItem,
                    $visiting
                );
            }
        }
    }

    private function populateOperation(
        string $subresourceOperationName,
        array $subresourceOperation,
        string $resourceClass,
        array &$tree,
        string $rootResourceClass,
        ?array $parentOperation,
        array $visited,
        int $depth,
        ?int $maxDepth,
        string $property,
        SubresourceMetadata $subresource,
        string $subresourceClass,
        ResourceMetadata $subresourceMetadata,
        ResourceMetadata $rootResourceMetadata,
        bool $isLastItem,
        string $visiting
    ) {
        try {
            /**
             * Resolve operation name
             * @see \ApiPlatform\Core\Metadata\Resource\Factory\OperationResourceMetadataFactory::createOperations
             */
            $operationName = strtolower($subresourceOperation['method']);

            $operation = [
                'property' => $property,
                'collection' => $subresource->isCollection(),
                'resource_class' => $subresourceClass,
                'shortNames' => [$subresourceMetadata->getShortName()],
                'method' => $subresourceOperation['method'],
            ];

            if (null === $parentOperation) {
                $rootShortname = $rootResourceMetadata->getShortName();

                /**
                 * $id, $resourceClass, $hasIdentifier
                 * @see \ApiPlatform\Core\DataProvider\OperationDataProviderTrait::extractIdentifiers
                 */
                $operation['identifiers'] = [['id', $rootResourceClass, true]];

                /**
                 * Resolve full operation name by a predefined naming convention so that the operation can be customised
                 *
                 * Convention: $subresource(s)_$method_subresource
                 *
                 * Example: answer_get_subresource
                 */
                $operation['operation_name'] = sprintf(
                    '%s_%s%s',
                    RouteNameGenerator::inflector($operation['property'], $operation['collection'] ?? false),
                    $operationName,
                    self::SUBRESOURCE_SUFFIX
                );

                // Get operation definition from root resource
                $subresourceOperation = $rootResourceMetadata->getSubresourceOperations()[$operation['operation_name']] ?? [];

                /**
                 * Resolve full route name by a predefined naming convention so that the operation can be customised
                 *
                 * Convention: $resource(s)_$subresource(s)_$method_subresource
                 *
                 * Example: api_question_answer_get_subresource
                 */
                $operation['route_name'] = sprintf(
                    '%s%s_%s',
                    RouteNameGenerator::ROUTE_NAME_PREFIX,
                    RouteNameGenerator::inflector($rootShortname),
                    $operation['operation_name']
                );

                $prefix = trim(trim($rootResourceMetadata->getAttribute('route_prefix', '')), '/');
                if ('' !== $prefix) {
                    $prefix .= '/';
                }

                /**
                 * Convention: /$resource(s)/{id}/$subresource{s}.{_format}
                 *
                 * Example: /question/{id}/answer.{format}
                 */
                $operation['path'] = $subresourceOperation['path'] ?? sprintf(
                    '/%s%s/{id}/%s%s',
                    $prefix,
                    $this->pathSegmentNameGenerator->getSegmentName($rootShortname, true),
                    $this->pathSegmentNameGenerator->getSegmentName($operation['property'], $operation['collection']),
                    self::FORMAT_SUFFIX
                );

                if (!\in_array($rootShortname, $operation['shortNames'], true)) {
                    $operation['shortNames'][] = $rootShortname;
                }
            } else {
                $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
                $operation['identifiers'] = $parentOperation['identifiers'];
                $operation['identifiers'][] = [$parentOperation['property'], $resourceClass, $isLastItem ? true : $parentOperation['collection']];

                /**
                 * Resolve full operation name by a defined naming convention so that the operation can be customised
                 *
                 * Convention: $subresource(s)_$grandsubresource_$method_subresource
                 *
                 * Example: answer_relatedQuestions_get_subresource
                 */
                $operation['operation_name'] = str_replace(
                    'get'.self::SUBRESOURCE_SUFFIX,
                    RouteNameGenerator::inflector($isLastItem ? 'item' : $property, $operation['collection']).'_'.$operationName.self::SUBRESOURCE_SUFFIX,
                    $parentOperation['operation_name']
                );

                $operation['route_name'] = str_replace($parentOperation['operation_name'], $operation['operation_name'], $parentOperation['route_name']);

                if (!\in_array($resourceMetadata->getShortName(), $operation['shortNames'], true)) {
                    $operation['shortNames'][] = $resourceMetadata->getShortName();
                }

                $subresourceOperation = $rootResourceMetadata->getSubresourceOperations()[$operation['operation_name']] ?? [];

                if (isset($subresourceOperation['path'])) {
                    $operation['path'] = $subresourceOperation['path'];
                } else {
                    $operation['path'] = str_replace(self::FORMAT_SUFFIX, '', (string) $parentOperation['path']);

                    if ($parentOperation['collection']) {
                        // Assume that as it is a COLLECTION, the path is related to the LAST ID above
                        [$key] = end($operation['identifiers']);
                        $operation['path'] .= sprintf('/{%s}', $key);
                    }

                    if ($isLastItem) {
                        $operation['path'] .= self::FORMAT_SUFFIX;
                    } else {
                        $operation['path'] .= sprintf('/%s%s', $this->pathSegmentNameGenerator->getSegmentName($property, $operation['collection']), self::FORMAT_SUFFIX);
                    }
                }
            }

            foreach (self::ROUTE_OPTIONS as $routeOption => $defaultValue) {
                $operation[$routeOption] = $subresourceOperation[$routeOption] ?? $defaultValue;
            }

            $tree[$operation['route_name']] = $operation;

            if ('GET' === $operation['method']) {
                /**
                 * Simple solution to avoid collision among operations on depth >= 3
                 *
                 * (Limitation: nested subresources must be enabled by GET predecessor)
                 *
                 * Each subresource may contain multiple operations (e.g., GET and PUT). If a subresource contain
                 * another subresource, this brings the depth up to 3. In this situation, each operation computes
                 * from the parent subresource will collide with each other, for example:
                 *
                 * question_get_item
                 *  |
                 *  |- question_answer_get_subresource
                 *  |   |
                 *  |   |- question_answer_related_questions_get_subresource (collision 1)
                 *  |   |- question_answer_related_questions_put_subresource (collision 2)
                 *  |
                 *  |- question_answer_put_subresource
                 *      |
                 *      |- question_answer_related_questions_get_subresource (collision 1)
                 *      |- question_answer_related_questions_put_subresource (collision 2)
                 *
                 */
                $this->computeSubresourceOperations($subresourceClass, $tree, $rootResourceClass, $operation, $visited + [$visiting => true], ++$depth, $maxDepth);
            }
        } catch (\Exception $ex) {
            // TODO: Remove try-catch. Put try catch here just to keep the git history for now
            throw $ex;
        }
    }
}
