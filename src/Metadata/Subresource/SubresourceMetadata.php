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

namespace ApiPlatform\Core\Metadata\Subresource;

use ApiPlatform\Core\Bridge\Symfony\Routing\RouteNameGenerator;
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;
use ApiPlatform\Core\Metadata\Property\SubresourceMetadata as PropertySubresourceMetadata;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\Operation\PathSegmentNameGeneratorInterface;

/**
 * Subresource metadata value object
 *
 * @author Torrey Tsui <torreytsui@gmail.com>
 */
final class SubresourceMetadata
{
    // Dependencies
    private $pathSegmentNameGenerator;
    private $resourceMetadataFactory;

    // Data
    private $resourceClass;
    private $property;
    private $propertyMetadata;
    private $propertySubresourceMetadata;
    private $subresourceClass;
    private $inflectedProperty;
    private $subresourceSegment;
    private $subresourceResourceMetadata;

    public function __construct(
        PathSegmentNameGeneratorInterface $pathSegmentNameGenerator,
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        string $resourceClass,
        string $property,
        PropertyMetadata $propertyMetadata,
        PropertySubresourceMetadata $propertySubresourceMetadata,
        string $subresourceClass
    ) {
        $this->pathSegmentNameGenerator = $pathSegmentNameGenerator;
        $this->resourceMetadataFactory = $resourceMetadataFactory;

        $this->resourceClass = $resourceClass;
        $this->property = $property;
        $this->propertyMetadata = $propertyMetadata;
        $this->propertySubresourceMetadata = $propertySubresourceMetadata;
        $this->subresourceClass = $subresourceClass;
    }

    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getPropertyMetadata(): PropertyMetadata
    {
        return $this->propertyMetadata;
    }

    public function getPropertySubresourceMetadata(): PropertySubresourceMetadata
    {
        return $this->propertySubresourceMetadata;
    }

    public function getSubresourceClass(): string
    {
        return $this->subresourceClass;
    }

    public function getSubresourceResourceMetadata(): ResourceMetadata
    {
        return $this->subresourceResourceMetadata ??
            $this->subresourceResourceMetadata =
                $this->resourceMetadataFactory->create($this->subresourceClass);
    }

    public function getMaxDepth(): ?int
    {
        return $this->propertySubresourceMetadata->getMaxDepth();
    }

    /**
     * TODO: next major: remove last item workaround and its impact
     */
    public function isLastItem(): bool
    {
        return $this->propertyMetadata->isIdentifier() ?? false;
    }

    public function getInflectedProperty(): string
    {
        return $this->inflectedProperty ?? $this->inflectedProperty = RouteNameGenerator::inflector($this->property, $this->propertySubresourceMetadata->isCollection());
    }

    public function getSubresourceSegment(): string
    {
        return $this->subresourceSegment ?? $this->subresourceSegment = $this->pathSegmentNameGenerator->getSegmentName($this->property, $this->propertySubresourceMetadata->isCollection());
    }
}
