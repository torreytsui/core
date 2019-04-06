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

namespace ApiPlatform\Core\Metadata\Resource\Factory;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use ApiPlatform\Core\Metadata\Subresource\SubresourceMetadata;

/**
 * Create subresource metadata value object
 *
 * @author Torrey Tsui <torreytsui@gmail.com>
 */
interface SubresourceMetadataFactoryInterface
{
    /**
     * Creates a resource metadata.
     *
     * @throws ResourceClassNotFoundException
     */
    public function create(string $resourceClass, string $property): SubresourceMetadata;
}
