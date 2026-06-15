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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Shop;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\PrestaShop\Core\Domain\Shop\Query\SearchShops;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/shops/search',
            CQRSQuery: SearchShops::class,
            scopes: ['shop_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            parameters: new Parameters([
                new QueryParameter(
                    key: 'searchTerm',
                    required: true,
                    description: 'Search term to find shops and shop groups by name'
                ),
            ]),
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'searchTerm',
                        'in' => 'query',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                        'description' => 'Search term to find shops and shop groups by name',
                    ],
                ],
            ],
        ),
    ],
)]
class FoundShop
{
    #[ApiProperty(identifier: true)]
    public int $id;

    public string $color;

    public string $name;

    // Only present for shop rows (shop-group rows omit these)
    public ?int $groupId = null;

    public ?string $groupName = null;

    public ?string $groupColor = null;

    public const QUERY_MAPPING = [
        '[searchTerm]' => '[searchTerm]',
    ];
}
