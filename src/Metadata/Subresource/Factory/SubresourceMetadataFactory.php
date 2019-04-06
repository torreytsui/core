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

namespace ApiPlatform\Core\Metadata\Subresource\Factory;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\SubresourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Subresource\Factory\Exception\SubresourceMetadataNotFoundException;
use ApiPlatform\Core\Metadata\Subresource\SubresourceMetadata;
use ApiPlatform\Core\Operation\PathSegmentNameGeneratorInterface;

/**
 * Subresource metadata factory
 *
 * @author Torrey Tsui <torreytsui@gmail.com>
 */
final class SubresourceMetadataFactory implements SubresourceMetadataFactoryInterface
{
    private $formats = [];
    private $resourceMetadataFactory;
    private $propertyNameCollectionFactory;
    private $propertyMetadataFactory;
    private $pathSegmentNameGenerator;

    public function __construct(ResourceMetadataFactoryInterface $resourceMetadataFactory, PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, PathSegmentNameGeneratorInterface $pathSegmentNameGenerator)
    {
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->propertyNameCollectionFactory = $propertyNameCollectionFactory;
        $this->propertyMetadataFactory = $propertyMetadataFactory;
        $this->pathSegmentNameGenerator = $pathSegmentNameGenerator;
    }

    /**
     * @param string[] $formats
     * @return self
     */
    public function withFormats(array $formats): self
    {
        $clone = clone $this;
        $clone->formats = $formats;
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $resourceClass, string $property): SubresourceMetadata
    {
        $propertyMetadata = $this->propertyMetadataFactory->create($resourceClass, $property);

        if (!$propertyMetadata->hasSubresource()) {
            throw new SubresourceMetadataNotFoundException();
        }

        $propertySubresourceMetadata = $propertyMetadata->getSubresource();
        $subresourceClass = $propertySubresourceMetadata->getResourceClass();

        return new SubresourceMetadata(
            $this->pathSegmentNameGenerator,
            $this->resourceMetadataFactory,
            $resourceClass,
            $property,
            $propertyMetadata,
            $propertySubresourceMetadata,
            $subresourceClass
        );
    }
}
