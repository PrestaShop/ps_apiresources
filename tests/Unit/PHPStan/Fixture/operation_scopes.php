<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Stub;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/stubs/{stubId}',
            scopes: ['stub_read'],
            openapi: new OpenApiOperation(summary: 'Nested objects are not operations'),
        ),
        new CQRSDelete(
            uriTemplate: '/stubs/{stubId}',
        ),
        new CQRSDelete(
            uriTemplate: '/stubs/bulk-delete',
            scopes: [],
        ),
        new PaginatedList(
            uriTemplate: '/stubs',
            extraProperties: ['scopes' => ['stub_read']],
        ),
        new PaginatedList(
            uriTemplate: '/stubs/archived',
            extraProperties: ['scopes' => []],
        ),
    ],
)]
class OperationScopes
{
    public int $stubId;
}
