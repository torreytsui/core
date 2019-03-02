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

/**
 * @author Torrey Tsui <torreytsui@gmail.com>
 *
 * @ApiResource
 */
class RelatedDummyEntity
{
    /**
     * @var DummyEntity
     * @ApiSubresource
     */
    private $anotherSubresource;

    public function getAnotherSubresource()  { /** Not implemented */ }
}