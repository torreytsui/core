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

namespace ApiPlatform\Core\Tests\Fixtures\SubresourceOperationFactoryDummy;

use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\ORM\Mapping as ORM;

/**
 * @author Torrey Tsui <torreytsui@gmail.com>
 *
 * @ApiResource
 */
class DummyEntity
{
    /**
     * @var int
     * @ApiProperty(identifier=true)
     */
    public $id;

    /**
     * @ORM\OneToOne(targetEntity="RelatedDummyEntity")
     * @ApiSubresource
     */
    private $subresource;

    public function getSubresource() { /** Not implemented */ }

    /**
     * @ORM\OneToMany(targetEntity="RelatedDummyEntity")
     * @ApiSubresource
     */
    private $subcollection;

    public function getSubcollection() { /** Not implemented */ }
}