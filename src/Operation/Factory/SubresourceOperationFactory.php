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
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Operation\Factory\Exception\LastItemWorkaroundNotAllowedException;
use ApiPlatform\Core\Operation\Factory\Exception\MaxDepthExceededException;
use ApiPlatform\Core\Operation\Factory\Exception\SubresourceClassPropertyAlreadyVisitedException;
use ApiPlatform\Core\Operation\PathSegmentNameGeneratorInterface;
use ApiPlatform\Core\Operation\SubresourceOperation;

/**
 * Configure and build a subresource operation
 *
 * @internal
 *
 * @author Torrey Tsui <torreytsui@gmail.com>
 */
final class SubresourceOperationFactory
{
    public const SUBRESOURCE_SUFFIX = '_subresource';
    public const FORMAT_SUFFIX = '.{_format}';
    public const ROUTE_OPTIONS = ['defaults' => [], 'requirements' => [], 'options' => [], 'host' => '', 'schemes' => [], 'condition' => '', 'controller' => null];

    // Factory metadata
    private $operationNameTemplate = '%s_%s'.self::SUBRESOURCE_SUFFIX;
    private $routeNameTemplate;
    private $collectionPathTemplate;
    private $itemPathTemplate;
    private $visited = [];
    private $depth = 0;
    private $maxDepth = null;
    private $allowLastItemWorkaround = true;
    private $rootResourceClass;
    private $identifiers;
    private $trueByShortNames = [];

    // Cache
    private $rootResourceMetadata;

    // Dependencies
    private $resourceMetadataFactory;
    private $propertyNameCollectionFactory;
    private $propertyMetadataFactory;
    private $pathSegmentNameGenerator;
    private $formats;

    // Route options
    private $defaults = [];
    private $requirements = [];
    private $options = [];
    private $host = [];
    private $schemas = [];
    private $condition = '';
    private $controller = null;

    // Subresource operation data
    /** @var string */
    private $property;
    /** @var bool */
    private $isPropertySubresourceACollection;
    /** @var string */
    private $resourceClass;
    /** @var string */
    private $subresourceClass;
    /** @var string */
    private $identifierName;
    /** @var string */
    private $inflectedProperty;
    /** @var string */
    private $subresourceSegment;
    /** @var bool */
    private $isLastItem;

    public function __construct(ResourceMetadataFactoryInterface $resourceMetadataFactory, PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, PathSegmentNameGeneratorInterface $pathSegmentNameGenerator, array $formats = [])
    {
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->propertyNameCollectionFactory = $propertyNameCollectionFactory;
        $this->propertyMetadataFactory = $propertyMetadataFactory;
        $this->pathSegmentNameGenerator = $pathSegmentNameGenerator;
        $this->formats = $formats;
    }

    public function from(string $rootResourceClass): self
    {
        $clone = $this;

        // Reset to default
        $clone->operationNameTemplate = '%s_%s'.self::SUBRESOURCE_SUFFIX;
        $clone->maxDepth = null;
        $clone->depth = 0;
        $clone->allowLastItemWorkaround = true;

        // Reset cache
        $this->rootResourceMetadata;

        // Initialise from $rootResourceClass
        $clone->rootResourceClass = $rootResourceClass;
        $clone->visited = [$rootResourceClass => true];
        $clone->identifiers = [['id', $rootResourceClass, true, 'id']];
        $clone->routeNameTemplate = RouteNameGenerator::ROUTE_NAME_PREFIX.RouteNameGenerator::inflector($this->getRootResourceMetadata()->getShortName()).'_%s';
        $clone->trueByShortNames = [$this->getRootResourceMetadata()->getShortName() => true];

        $routePrefix = trim(trim($this->getRootResourceMetadata()->getAttribute('route_prefix', '')), '/');
        if ('' !== $routePrefix) {
            $routePrefix .= '/';
        }

        $collectionPathTemplate = '/'.
            $routePrefix.
            $this->pathSegmentNameGenerator->getSegmentName($rootResourceMetadata->getShortName()).
            '/{id}%s%s'.
            self::FORMAT_SUFFIX;

        $this->collectionPathTemplate = $collectionPathTemplate;
        $this->itemPathTemplate = $collectionPathTemplate;

        return $clone;
    }

    public function withSubresourceCollectionOperation(array $subresourceOperation): self
    {
        $clone = clone $this;



        return $clone;
    }

    public function bySomething(\ApiPlatform\Core\Metadata\Subresource\SubresourceMetadata $subresourceMetadata): self
    {
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
        ]);

        $this->assertFromIsCalled();

        $property = $subresourceMetadata->getProperty();
        $propertySubresourceMetadata = $subresourceMetadata->getPropertySubresourceMetadata();
        $subresourceClass = $subresourceMetadata->getSubresourceClass();
        $subresourceResourceMetadata = $subresourceMetadata->getSubresourceResourceMetadata();

        /*
         * [$id, $resourceClass, $hasIdentifier]
         * @see \ApiPlatform\Core\DataProvider\OperationDataProviderTrait::extractIdentifiers
         */
        $identifierName = $this->resolveIdentifierName($property);
        $identifiers = array_merge($this->identifiers, [[$property, $subresourceClass, $propertySubresourceMetadata->isCollection(), $identifierName]]);

        $clone = clone $this;

        $clone->maxDepth = $this->resolveMaxDepth($subresourceMetadata);
        $clone->depth++;
        $clone->identifiers = $identifiers;
        $clone->trueByShortNames[$subresourceResourceMetadata->getShortName()] = true;
        // TODO: next major: remove this workaround
        $clone->allowLastItemWorkaround = $propertySubresourceMetadata->isCollection();
        /*
         * Template: ($ancientResource(s)_)%s_%s_subresource
         *
         * Example Input: %s_%s_subresource
         * Example Output: relatedDummy_%s_%s_subresource
         */
        $clone->operationNameTemplate = sprintf($this->operationNameTemplate, $subresourceMetadata->getInflectedProperty().'_%s', '%s');

        $clone->visited = $this->visited + [$this->resolveVisiting($subresourceMetadata, $property) => true];

        return $clone;
    }

    public function bySubresourceMetadata(\ApiPlatform\Core\Metadata\Subresource\SubresourceMetadata $subresourceMetadata): self
    {
        $this->assertFromIsCalled();

        $property = $subresourceMetadata->getProperty();
        $resourceClass = $subresourceMetadata->getResourceClass();
        $propertySubresourceMetadata = $subresourceMetadata->getPropertySubresourceMetadata();
        $subresourceClass = $subresourceMetadata->getSubresourceClass();

        /**
         * Stop circular subresource.
         *
         * Example 1: /dummy/{id}/another_subresource/{id}/subcollections/{id}/another_subresource/{id}/...
         * Example 2: /dummy/{id}/subcollections/{id}/another_subresource/{id}/subcollections/{id}...
         *
         * In example 1, another_subresource contains a subcollections and it in turn contains another
         * another_subresource. This forms a circle and will not end unless intervened.
         */
        $visitStack = [
            [$rootResourceClass, $maxDepth, $depth, $rootProperties, $operationNameTemplate, $routeNameTemplate, $collectionPathTemplate, $itemPathTemplate, $identifiers, $trueByShortNames, $visited, $allowLastItemWorkaround],
        ];

        // Resources
        while ($visitFrame = array_pop($visitStack)) {
            list($resourceClass, $maxDepth, $depth, $properties, $operationNameTemplate, $routeNameTemplate, $collectionPathTemplate, $itemPathTemplate, $identifiers, $trueByShortNames, $visited, $allowLastItemWorkaround) = array_values($visitFrame);

            // Subresources
            foreach ($properties as $property) {
                $propertyMetadata = $this->propertyMetadataFactory->create($resourceClass, $property);
                if (!$propertyMetadata->hasSubresource()) {
                    continue;
                }

                if (!$subresource = $propertyMetadata->getSubresource()) {
                    continue;
                }

                /**
                 * Max depth resolves from the nearest parent resource.
                 *
                 * Options are:
                 * - null = to be resolved
                 * - false = unlimited depth
                 * - integer (e.g., 1)
                 */
                $maxDepth = $subresource->getMaxDepth() ?? $maxDepth ?? false;
                if (false !== $maxDepth && $depth >= $maxDepth) {
                    continue;
                }

                $subresourceClass = $subresource->getResourceClass();

                /**
                 * Stop circular subresource.
                 *
                 * Example 1: /dummy/{id}/another_subresource/{id}/subcollections/{id}/another_subresource/{id}/...
                 * Example 2: /dummy/{id}/subcollections/{id}/another_subresource/{id}/subcollections/{id}...
                 *
                 * In example 1, another_subresource contains a subcollections and it in turn contains another
                 * another_subresource. This forms a circle and will not end unless intervened.
                 */
                $visiting = sprintf('%s:%s', $property, $subresourceClass);
                if (isset($visited[$visiting])) {
                    continue;
                }

                // TODO design a dev friendly way to define subresource operations so they can be partially disabled
                // TODO Maybe merge in them with the default so we don't need to worry in later on population or we will probably need to resolve on the fly
                $subresourceMetadata = $this->resourceMetadataFactory->create($subresourceClass);
                $subresourceMetadata = OperationResourceMetadataFactory::populateOperations($subresourceClass, $subresourceMetadata, $this->formats);

                if (($isLastItem = $propertyMetadata->isIdentifier()) && $allowLastItemWorkaround) {
                    // TODO: next majory: throw an exception and remove $isLastItem and $allowLastItemWorkaround and their impacts
                    @trigger_error(sprintf(
                        'Declaring identifier property "%s" (in class %s) with @ApiSubresource as a workaround (https://github.com/api-platform/core/pull/1875) to enable subresource GET item operation is deprecated and will cause an error in Api-Platform 3.0. The operation(s) is now offered by default. Please remove the @ApiSubresource declaration in the "%s" property.',
                        $property,
                        $resourceClass,
                        $property
                    ), E_USER_DEPRECATED);
                }
                // TODO: else-if support non-entity object / array of objects (e.g., embededable)s

                if ($isLastItem && !$allowLastItemWorkaround) {
                    continue;
                }

                $inflectedProperty = RouteNameGenerator::inflector($property, $subresource->isCollection());

                $subresourceSegment = $this->pathSegmentNameGenerator->getSegmentName($property, $subresource->isCollection());

                $subresourceCollectionOperations = !$isLastItem && $subresource->isCollection() ? $subresourceMetadata->getCollectionOperations() : [];
                $subresourceItemOperations = $subresourceMetadata->getItemOperations();

                $names = array_map(function ($identifier) {
                    return $identifier[3];
                }, $identifiers);
                $identifierName = $property;
                while (\in_array($identifierName, $names, true) && $segment = array_pop($names)) {
                    $identifierName = $segment.ucfirst($identifierName);
                }

                $operationTemplate = [
                    'property' => $property,
                    'collection' => $subresource->isCollection(),
                    'resource_class' => $subresourceClass,
                    'shortNames' => array_keys($trueByShortNames + [$subresourceMetadata->getShortName() => true]),
                ] + self::ROUTE_OPTIONS;

                $subresourceCollectionPathByMethod = [];
                $subresourceItemPathByMethod = [];

                // Subresource collection operations
                foreach ($subresourceCollectionOperations as $subresourceOperationName => $subresourceOperation) {
                    /*
                     * Resolve full operation name by a predefined naming convention so that the operation can be customised
                     *
                     * Convention: $subresource(s)_$method_subresource
                     * Example: relatedDummy_get_subresource
                     *
                     * Convention: $ancientResource(s)_$subresource(s)_$method_subresource
                     * Example: relatedDummy_relatedDummies_get_subresource
                     *
                     * Template: ($ancientResource(s)_)%s_%s_subresource
                     */
                    $operationName = sprintf(
                        $operationNameTemplate,
                        $inflectedProperty,
                        strtolower($subresourceOperation['method'])
                    );

                    $overriddenSubresourceOperation = $rootResourceMetadata->getSubresourceOperations()[$operationName] ?? [];

                    if ($overriddenPath = $overriddenSubresourceOperation['path'] ?? null) {
                        $subresourceCollectionPathByMethod[$subresourceOperation['method']] = $subresourceCollectionPathByMethod[$subresourceOperation['method']] ?? $overriddenPath;
                    }

                    /*
                     * Resolve full route name by a predefined naming convention so that the operation can be customised
                     *
                     * Template: api_$resource(s)_%s
                     *
                     * Convention 1: $prefix_$resource(s)_$subresource(s)_$method_subresource
                     * Output 1: api_dummy_relatedDummy_get_subresource
                     *
                     * Convention 2: $prefix_$resource(s)_$ancientResource(s)_$subresource(s)_$method_subresource
                     * Output 2: api_dummy_relatedDummy_relatedDummies_get_subresource
                     */
                    $routeName = sprintf($routeNameTemplate, $operationName);

                    $tree[$routeName] = array_merge($operationTemplate, [
                        'collection' => true,
                        'method' => $subresourceOperation['method'],
                        'operation_name' => $operationName,
                        'identifiers' => $identifiers,
                        'route_name' => $routeName,

                        /*
                         * Convention: /$resource/{id}/$subresources.{_format}
                         * Template: /dummy/{id}%s%s.{_format}
                         * Example: /dummy/{id}/related_dummies.{_format}
                         *
                         * related_dummies
                         * - GET    /dummies/{id}/related_dummies        Collection
                         */
                        'path' => $overriddenPath ?? sprintf(
                            $collectionPathTemplate,
                            ...($isLastItem ? ['', ''] : ['/', $subresourceSegment])
                        ),
                    ] + array_intersect_key($overriddenSubresourceOperation, static::ROUTE_OPTIONS));
                }

                // Subresource item operations
                foreach ($subresourceItemOperations as $subresourceOperationName => $subresourceOperation) {
                    /*
                     * Resolve full operation name by a predefined naming convention so that the operation can be customised
                     *
                     * Convention: $subresource(s)_$method_subresource
                     * Example: relatedDummy_get_subresource
                     *
                     * Convention: $ancientResource(s)_$subresource(s)_$method_subresource
                     * Example: relatedDummy_relatedDummies_get_subresource
                     *
                     * Template: ($ancientResource(s)_)%s_%s_subresource
                     */
                    $operationName = sprintf(
                        $operationNameTemplate,
                        RouteNameGenerator::inflector(($isLastItem ? 'item' : $property), $subresource->isCollection()),
                        ($isLastItem ? '' : 'item_').strtolower($subresourceOperation['method'])
                    );

                    $overriddenSubresourceOperation = $rootResourceMetadata->getSubresourceOperations()[$operationName] ?? [];

                    if ($overriddenPath = $overriddenSubresourceOperation['path'] ?? null) {
                        $subresourceItemPathByMethod[$subresourceOperation['method']] = $subresourceItemPathByMethod[$subresourceOperation['method']] ?? $overriddenPath;
                    }

                    /*
                     * Resolve full route name by a predefined naming convention so that the operation can be customised
                     *
                     * Convention: $prefix_$resource(s)_$subresource(s)_$method_subresource
                     * Example: api_dummy_relatedDummy_get_subresource
                     *
                     * Convention: $prefix_$resource(s)_$ancientResource(s)_$subresource(s)_$method_subresource
                     * Example: api_dummy_relatedDummy_relatedDummies_get_subresource
                     *
                     * Template: api_$resource(s)_%s
                     */
                    $routeName = sprintf($routeNameTemplate, $operationName);

                    $tree[$routeName] = array_merge($operationTemplate, [
                        'collection' => false,
                        'method' => $subresourceOperation['method'],
                        'operation_name' => $operationName,
                        'identifiers' => array_merge($identifiers, $subresource->isCollection() ? [[$property, $subresourceClass, true, $identifierName]] : []),
                        'route_name' => $routeName,

                        /*
                         * Convention 1: /$resource/{id}/$subresource.{_format}
                         * Example 1: /dummy/{id}/related_dummy.{_format}
                         * Template 1: /dummy/{id}/%s.{_format}
                         *
                         * related_dummy
                         * - GET    /dummies/{id}/related_dummy         Item
                         * - PUT    /dummies/{id}/related_dummy         Item
                         * - DELETE /dummies/{id}/related_dummy         Item
                         * - PATCH  /dummies/{id}/related_dummy         Item
                         *
                         * Convention 2: /$resource/{id}/$subresources.{_format}
                         * Example 2: /dummy/{id}/related_dummies/{id}.{_format}
                         * Template 2: /dummy/{id}/%s/{id}.{_format}
                         *
                         * related_dummies
                         * - GET    /dummies/{id}/related_dummies/{id}   Item
                         * - PUT    /dummies/{id}/related_dummies/{id}   Item
                         * - DELETE /dummies/{id}/related_dummies/{id}   Item
                         * - PATCH  /dummies/{id}/related_dummies/{id}   Item
                         *
                         */
                        'path' => $overriddenPath ?? sprintf(
                            $itemPathTemplate,
                            ...($isLastItem ? ['', ''] : ['/', $subresourceSegment.($subresource->isCollection() ? '/{'.$identifierName.'}' : '')])
                        ),
                    ] + array_intersect_key($overriddenSubresourceOperation, static::ROUTE_OPTIONS));
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
                        null;

                    $itemOperationOverriddenPath =
                        $subresourceItemPathByMethod['GET'] ??
                        $subresourceItemPathByMethod['PUT'] ??
                        $subresourceItemPathByMethod['DELETE'] ??
                        null;

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

        // Compute subresource resource metadata

        $clone = clone $this;

        $clone->subresourceClass = $subresourceClass;

        $clone->identifierName = $this->resolveIdentifierName($property);

        $clone->property = $property;
        $clone->isPropertySubresourceACollection = $propertySubresourceMetadata->isCollection();
        $clone->resourceClass = $subresourceClass;
        $clone->trueByShortNames[$subresourceMetadata->getSubresourceResourceMetadata()->getShortName()] = true;

        $clone->inflectedProperty = $subresourceMetadata->getInflectedProperty();
        $clone->subresourceSegment = $subresourceMetadata->getSubresourceSegment();

        $clone->isLastItem = $isLastItem;

        return $clone;
    }

    public function getCollectionOperationName(array $subresourceOperation): string
    {
        $this->assertFromIsCalled();

        /*
         * Resolve full operation name by a predefined naming convention so that the operation can be customised
         *
         * Convention: $subresource(s)_$method_subresource
         * Example: relatedDummy_get_subresource
         *
         * Convention: $ancientResource(s)_$subresource(s)_$method_subresource
         * Example: relatedDummy_relatedDummies_get_subresource
         *
         * Template: ($ancientResource(s)_)%s_%s_subresource
         */
        return sprintf(
            $this->operationNameTemplate,
            $this->inflectedProperty,
            strtolower($subresourceOperation['method'])
        );
    }

    public function getItemOperationName(array $subresourceOperation): string
    {
        $this->assertFromIsCalled();

        /*
         * Resolve full operation name by a predefined naming convention so that the operation can be customised
         *
         * Convention: $subresource(s)_$method_subresource
         * Example: relatedDummy_get_subresource
         *
         * Convention: $ancientResource(s)_$subresource(s)_$method_subresource
         * Example: relatedDummy_relatedDummies_get_subresource
         *
         * Template: ($ancientResource(s)_)%s_%s_subresource
         */
        return sprintf(
            $this->operationNameTemplate,
            RouteNameGenerator::inflector(($this->isLastItem ? 'item' : $this->property), $this->isPropertySubresourceACollection),
            ($this->isLastItem ? '' : 'item_').strtolower($subresourceOperation['method'])
        );
    }

    public function getOverridingSubresourceOperation(string $operationName): ?array
    {
        return $this->getRootResourceMetadata()->getSubresourceOperations()[$operationName] ?? [];
    }

    public function createCollectionOperation(array $subresourceOperation): SubresourceOperation
    {
        $this->assertFromIsCalled();

        // Always true, as it is a subresource collection operation
        $collection = true;

        $operationName = $this->getCollectionOperationName($subresourceOperation);

        /*
         * Convention: /$resource/{id}/$subresources.{_format}
         * Template: /dummy/{id}%s%s.{_format}
         * Example: /dummy/{id}/related_dummies.{_format}
         *
         * related_dummies
         * - GET    /dummies/{id}/related_dummies        Collection
         */
        $path = $overriddenPath ?? sprintf(
            $this->collectionPathTemplate,
            ...($this->isLastItem ? ['', ''] : ['/', $this->subresourceSegment])
        );

        $identifiers = $this->identifiers;

        return $this->create(
            $subresourceOperation,
            $collection,
            $operationName,
            $identifiers,
            $path
        );
    }

    public function createItemOperation(array $subresourceOperation): SubresourceOperation
    {
        $this->assertFromIsCalled();

        // Always false, as it is a subresource item operation
        $collection = false;

        $operationName = $this->getItemOperationName($subresourceOperation);

        $identifiers = array_merge(
            $this->identifiers,
            $this->isPropertySubresourceACollection ? [[$this->property, $this->subresourceClass, true, $this->identifierName]] : []
        );

        /*
         * Convention 1: /$resource/{id}/$subresource.{_format}
         * Example 1: /dummy/{id}/related_dummy.{_format}
         * Template 1: /dummy/{id}/%s.{_format}
         *
         * related_dummy
         * - GET    /dummies/{id}/related_dummy         Item
         * - PUT    /dummies/{id}/related_dummy         Item
         * - DELETE /dummies/{id}/related_dummy         Item
         * - PATCH  /dummies/{id}/related_dummy         Item
         *
         * Convention 2: /$resource/{id}/$subresources.{_format}
         * Example 2: /dummy/{id}/related_dummies/{id}.{_format}
         * Template 2: /dummy/{id}/%s/{id}.{_format}
         *
         * related_dummies
         * - GET    /dummies/{id}/related_dummies/{id}   Item
         * - PUT    /dummies/{id}/related_dummies/{id}   Item
         * - DELETE /dummies/{id}/related_dummies/{id}   Item
         * - PATCH  /dummies/{id}/related_dummies/{id}   Item
         *
         */
        $path = $overriddenPath ?? sprintf(
            $this->itemPathTemplate,
            ...(
                $this->isLastItem ?
                    ['', ''] :
                    ['/', $this->subresourceSegment.($this->isPropertySubresourceACollection ? '/{'.$this->identifierName.'}' : '')]
            )
        );

        return $this->create(
            $subresourceOperation,
            $collection,
            $operationName,
            $identifiers,
            $path
        );
    }

    private function create(
        array $subresourceOperation,
        $collection,
        $operationName,
        $identifiers,
        $path
    ): SubresourceOperation {
        /*
         * Resolve full route name by a predefined naming convention so that the operation can be customised
         *
         * Template: api_$resource(s)_%s
         *
         * Convention 1: $prefix_$resource(s)_$subresource(s)_$method_subresource
         * Output 1: api_dummy_relatedDummy_get_subresource
         *
         * Convention 2: $prefix_$resource(s)_$ancientResource(s)_$subresource(s)_$method_subresource
         * Output 2: api_dummy_relatedDummy_relatedDummies_get_subresource
         */
        $routeName = sprintf($this->routeNameTemplate, $operationName);

        [
            'defaults' => $defaults,
            'requirements' => $requirements,
            'options' => $options,
            'host' => $host,
            'schemes' => $schemes,
            'condition' => $condition,
            'controller' => $controller
        ] = $this->getOverridingSubresourceOperation($operationName) + self::ROUTE_OPTIONS;

        return new SubresourceOperation(
            $this->property,
            $collection,
            $subresourceOperation['method'],
            $operationName,
            $identifiers,
            $routeName,
            $path,
            $this->resourceClass,
            array_keys($this->trueByShortNames),
            $defaults,
            $requirements,
            $options,
            $host,
            $schemes,
            $condition,
            $controller
        );
    }

    private function getRootResourceMetadata()
    {
        $this->assertFromIsCalled();

        return $this->rootResourceMetadata ?? $this->rootResourceMetadata = $this->resourceMetadataFactory->create($this->rootResourceClass);
    }

    private function assertFromIsCalled()
    {
        if (null === $this->rootResourceClass) {
            throw new \Exception('Root resource class is undefined. Please define it with from().');
        }
    }

    /**
     * Max depth resolves from the nearest parent resource.
     *
     * Options are:
     * - null = to be resolved
     * - false = unlimited depth
     * - integer (e.g., 1)
     *
     * @return int|null|false
     */
    private function resolveMaxDepth(\ApiPlatform\Core\Metadata\Subresource\SubresourceMetadata $subresourceMetadata)
    {
        return $subresourceMetadata->getMaxDepth() ?? $this->maxDepth ?? false;
    }

    private function resolveIdentifierName($property): string
    {
        $names = array_map(function ($identifier) {
            return $identifier[3];
        }, $this->identifiers);
        $identifierName = $property;
        while (\in_array($identifierName, $names, true) && $segment = array_pop($names)) {
            $identifierName = $segment . ucfirst($identifierName);
        }
        return $identifierName;
    }

    private function resolveVisiting(
        \ApiPlatform\Core\Metadata\Subresource\SubresourceMetadata $subresourceMetadata,
        string $property
    ): string {
        return sprintf('%s:%s', $property, $subresourceMetadata->getSubresourceClass());
    }
}
