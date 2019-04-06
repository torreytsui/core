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
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;
use ApiPlatform\Core\Metadata\Property\SubresourceMetadata;
use ApiPlatform\Core\Metadata\Resource\Factory\OperationResourceMetadataFactory;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\SubresourceMetadataFactoryInterface;
use ApiPlatform\Core\Operation\Factory\Exception\LastItemWorkaroundNotAllowedException;
use ApiPlatform\Core\Operation\Factory\Exception\MaxDepthExceededException;
use ApiPlatform\Core\Operation\Factory\Exception\SubresourceClassPropertyAlreadyVisitedException;
use ApiPlatform\Core\Metadata\Subresource\Factory\Exception\SubresourceMetadataNotFoundException;
use ApiPlatform\Core\Operation\PathSegmentNameGeneratorInterface;

/**
 * @internal
 */
final class SubresourceOperationsFactory implements SubresourceOperationsFactoryInterface
{
    const SUBRESOURCE_SUFFIX = '_subresource';
    const FORMAT_SUFFIX = '.{_format}';
    const ROUTE_OPTIONS = ['defaults' => [], 'requirements' => [], 'options' => [], 'host' => '', 'schemes' => [], 'condition' => '', 'controller' => null];

    private $subresourceMetadataFactory;
    private $resourceMetadataFactory;
    private $propertyNameCollectionFactory;
    private $propertyMetadataFactory;
    private $pathSegmentNameGenerator;
    private $formats;

    public function __construct(SubresourceMetadataFactoryInterface $subresourceMetadataFactory, ResourceMetadataFactoryInterface $resourceMetadataFactory, PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, PathSegmentNameGeneratorInterface $pathSegmentNameGenerator, array $formats = [])
    {
        $this->subresourceMetadataFactory = $subresourceMetadataFactory;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->propertyNameCollectionFactory = $propertyNameCollectionFactory;
        $this->propertyMetadataFactory = $propertyMetadataFactory;
        $this->pathSegmentNameGenerator = $pathSegmentNameGenerator;
        $this->formats = $formats;
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $rootResourceClass): array
    {
        // TODO: Implement operation object to return insteads
        $tree = [];

        // TODO: inject it
        /** @var \ApiPlatform\Core\Operation\Factory\SubresourceOperationFactory $subresourceOperationFactory */
        $rootedSubresourceOperationFactory = $subresourceOperationFactory->from($rootResourceClass);

        $rootProperties = $this->propertyNameCollectionFactory->create($rootResourceClass);

        /**
         * Schedule visits.
         *
         * Structure:
         *
         * [0] - Dummy
         *       |
         * [1]   |- relatedDummy: RelatedDummy
         *       |  |
         * [2]   |  |- thirdLevel: ThirdLevel
         *       |  |  |
         * [?]   |  |  |-...
         *       |
         * [?]   |- relatedDummies: RelatedDummy[]
         *       |  |
         * [?]   |  |-...
         */
        $visitStack = [
            [$rootResourceClass, $maxDepth, $depth, $rootProperties, $operationNameTemplate, $routeNameTemplate, $collectionPathTemplate, $itemPathTemplate, $identifiers, $trueByShortNames, $visited, $allowLastItemWorkaround],
        ];

        // Resources
        while ($visitFrame = array_pop($visitStack)) {
            list($resourceClass, $maxDepth, $depth, $properties, $operationNameTemplate, $routeNameTemplate, $collectionPathTemplate, $itemPathTemplate, $identifiers, $trueByShortNames, $visited, $allowLastItemWorkaround) = array_values($visitFrame);

            // Subresources
            foreach ($properties as $property) {

                try {
                    $subresourceMetadata = $this->subresourceMetadataFactory->create($resourceClass, $property);

                    $configuredSubresourceOperationFactory = $rootedSubresourceOperationFactory
                        ->bySubresourceMetadata($subresourceMetadata)
                    ;

                    $somethingSubresourceOperationFactory = $rootedSubresourceOperationFactory
                        ->bySomething($subresourceMetadata)
                    ;

                    // TODO design a best dev friendly way to define subresource operations so they can be partially disabled
                    // TODO merge in them with the default so we don't need to worry in later on population
                    $subresourceResourceMetadata = $subresourceMetadata->getSubresourceResourceMetadata();
                    $subresourceResourceMetadata = OperationResourceMetadataFactory::populateOperations($subresourceMetadata->getSubresourceClass(), $subresourceResourceMetadata, $this->formats);

                    $subresourceCollectionOperations = !$subresourceMetadata->isLastItem() && $subresourceMetadata->getPropertySubresourceMetadata()->isCollection() ? $subresourceResourceMetadata->getCollectionOperations() : [];
                    $subresourceItemOperations = $subresourceResourceMetadata->getItemOperations();

                    $subresourceCollectionPathByMethod = [];
                    $subresourceItemPathByMethod = [];

                    // Subresource collection operations
                    foreach ($subresourceCollectionOperations as $subresourceOperationName => $subresourceOperation) {
                        $operationName = $configuredSubresourceOperationFactory->getCollectionOperationName($subresourceOperation);
                        $overriddenSubresourceOperation = $configuredSubresourceOperationFactory->getOverridingSubresourceOperation($operationName);

                        if ($overriddenPath = $overriddenSubresourceOperation['path'] ?? null) {
                            $subresourceCollectionPathByMethod[$subresourceOperation['method']] = $subresourceCollectionPathByMethod[$subresourceOperation['method']] ?? $overriddenPath;
                        }

                        $somethingSubresourceOperationFactory = $somethingSubresourceOperationFactory->withSubresourceCollectionOperation($subresourceOperation);

                        $subresourceCollectionOperation = $configuredSubresourceOperationFactory->createCollectionOperation($subresourceOperation);

                        $tree[$subresourceCollectionOperation->getRouteName()] = $subresourceCollectionOperation;
                    }

                    // Subresource item operations
                    foreach ($subresourceItemOperations as $subresourceOperationName => $subresourceOperation) {
                        $operationName = $configuredSubresourceOperationFactory->getItemOperationName($subresourceOperation);
                        $overriddenSubresourceOperation = $configuredSubresourceOperationFactory->getOverridingSubresourceOperation($operationName);

                        if ($overriddenPath = $overriddenSubresourceOperation['path'] ?? null) {
                            $subresourceItemPathByMethod[$subresourceOperation['method']] = $subresourceItemPathByMethod[$subresourceOperation['method']] ?? $overriddenPath;
                        }

                        $somethingSubresourceOperationFactory = $somethingSubresourceOperationFactory->withSubresourceItemOperation($subresourceOperation);

                        $subresourceCollectionOperation = $configuredSubresourceOperationFactory->createItemOperation($subresourceOperation);

                        $tree[$subresourceCollectionOperation->getRouteName()] = $subresourceCollectionOperation;
                    }



                } catch (SubresourceMetadataNotFoundException $ex) {
                    continue;
                } catch (MaxDepthExceededException $ex) {
                    continue;
                } catch (SubresourceClassPropertyAlreadyVisitedException $ex) {
                    continue;
                } catch (LastItemWorkaroundNotAllowedException $ex) {
                    continue;
                }

                $visitStackTemplate = [
                    'subresource_class' => $subresourceClass,
                    'max_depth' => $maxDepth,
                    'depth' => $depth + 1,
                    'properties' => $this->propertyNameCollectionFactory->create($subresourceClass),

                    'operation_name_template' => null,

                    'route_name_template' => $routeNameTemplate,

                    'collection_path_template' => null,
                    'item_path_template' => null,

                    /*
                     * [$id, $resourceClass, $hasIdentifier]
                     * @see \ApiPlatform\Core\DataProvider\OperationDataProviderTrait::extractIdentifiers
                     */
                    'identifiers' => array_merge($identifiers, [[$property, $subresourceClass, $subresource->isCollection(), $identifierName]]),

                    'true_by_short_names' => $trueByShortNames + [$subresourceMetadata->getShortName() => true],

                    'visited' => null,

                    // TODO: next major: remove this workaround
                    'allow_last_item_workaround' => $subresource->isCollection(),
                ];

                /*
                 * Avoid collision among operations on depth >= 3
                 *
                 * Each subresource may contain multiple operations (e.g., GET collection and GET item).
                 * If a subresource contains another subresource, this brings the depth up to >= 3.
                 * In this situation, each operation computes from the parent subresource will collide with each other,
                 * for example:
                 *
                 * question_get_item
                 *  |
                 *  |- question_answer_get_subresource
                 *  |   |
                 *  |   |- question_answer_related_questions_get_subresource (collision 1)
                 *  |   |- question_answer_related_questions_get_item_subresource (collision 2)
                 *  |
                 *  |- question_answer_get_item_subresource
                 *      |
                 *      |- question_answer_related_questions_get_subresource (collision 1)
                 *      |- question_answer_related_questions_get_item_subresource (collision 2)
                 */
                if (!$isLastItem && (!empty($subresourceCollectionOperations) || !empty($subresourceItemOperations))) {
                    $collectionOperationOverriddenPath =
                        $subresourceCollectionPathByMethod['GET'] ??
                        $subresourceCollectionPathByMethod['POST'] ??
                        null
                    ;

                    $itemOperationOverriddenPath =
                        $subresourceItemPathByMethod['GET'] ??
                        $subresourceItemPathByMethod['PUT'] ??
                        $subresourceItemPathByMethod['DELETE'] ??
                        null
                    ;

                    $subresourcePathSegment = $subresourceSegment.($subresource->isCollection() ? '/{'.$identifierName.'}' : '');
                    $visitStack[] = array_merge($visitStackTemplate, [
                        /*
                         * Template: ($ancientResource(s)_)%s_%s_subresource
                         *
                         * Example Input: %s_%s_subresource
                         * Example Output: relatedDummy_%s_%s_subresource
                         */
                        'operation_name_template' => sprintf($operationNameTemplate, $inflectedProperty.'_%s', '%s'),

                        /*
                         * Example Input: /dummy/{id}/%s.{_format}
                         *
                         * Example Output: /dummy/{id}/related_dummy/{id}/%s.{_format}
                         * Example Output: /dummy/{id}/related_dummies/%s.{_format}
                         * Example Output: /dummy/{id}/related_dummies/{id}/%s.{_format}
                         */
                        'collection_path_template' => $collectionOperationOverriddenPath ?
                            str_replace(self::FORMAT_SUFFIX, '', $collectionOperationOverriddenPath).'%s%s'.self::FORMAT_SUFFIX :
                            sprintf($collectionPathTemplate, '/', $subresourcePathSegment.'%s%s'),
                        'item_path_template' => $itemOperationOverriddenPath ?
                            str_replace(self::FORMAT_SUFFIX, '', $itemOperationOverriddenPath).'%s%s'.self::FORMAT_SUFFIX :
                            sprintf($itemPathTemplate, '/', $subresourcePathSegment.'%s%s'),

                        'visited' => $visited + [$visiting => true],
                    ]);
                }
            }
        }

        return $tree;
    }
}
