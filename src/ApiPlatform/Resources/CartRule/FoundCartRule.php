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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CartRule;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\PrestaShop\Core\Domain\CartRule\Query\SearchCartRules;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/cart-rules/search',
            scopes: [
                'cart_rule_read',
            ],
            CQRSQuery: SearchCartRules::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            parameters: new Parameters([
                new QueryParameter(
                    key: 'phrase',
                    required: true,
                    description: 'Search phrase to find cart rules by name or code'
                ),
            ]),
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'phrase',
                        'in' => 'query',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                        'description' => 'Search phrase to find cart rules by name or code',
                    ],
                ],
            ],
        ),
    ],
)]
class FoundCartRule
{
    #[ApiProperty(identifier: true)]
    public int $cartRuleId;

    public string $name;

    public string $code;

    public const QUERY_MAPPING = [
        '[phrase]' => '[searchPhrase]',
    ];
}
