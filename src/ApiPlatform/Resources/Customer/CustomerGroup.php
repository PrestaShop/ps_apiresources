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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Command\AddCustomerGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Command\DeleteCustomerGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Command\EditCustomerGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Exception\GroupNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Query\GetCustomerGroupForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/customers/groups/{customerGroupId}',
            CQRSQuery: GetCustomerGroupForEditing::class,
            scopes: [
                'customer_group_read',
            ],
            // QueryResult format doesn't match with ApiResource, so we can specify a mapping so that it is normalized with extra fields adapted for the ApiResource DTO
            CQRSQueryMapping: [
                // EditableCustomerGroup::$id is normalized as [customerGroupId]
                '[id]' => '[customerGroupId]',
                // EditableCustomerGroup::$reduction is normalized as [reductionPercent]
                '[reduction]' => '[reductionPercent]',
            ],
        ),
        new CQRSCreate(
            uriTemplate: '/customers/groups',
            CQRSCommand: AddCustomerGroupCommand::class,
            CQRSQuery: GetCustomerGroupForEditing::class,
            scopes: [
                'customer_group_write',
            ],
            // Here, we use query mapping to adapt normalized query result for the ApiPlatform DTO
            CQRSQueryMapping: [
                '[id]' => '[customerGroupId]',
                '[reduction]' => '[reductionPercent]',
            ],
            // Here, we use command mapping to adapt the normalized command result for the CQRS query
            CQRSCommandMapping: [
                '[_context][shopIds]' => '[shopIds]',
                '[groupId]' => '[customerGroupId]',
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/customers/groups/{customerGroupId}',
            CQRSCommand: EditCustomerGroupCommand::class,
            CQRSQuery: GetCustomerGroupForEditing::class,
            scopes: [
                'customer_group_write',
            ],
            // Here we use the ApiResource DTO mapping to transform the normalized query result
            ApiResourceMapping: [
                '[id]' => '[customerGroupId]',
                '[reduction]' => '[reductionPercent]',
            ],
        ),
        new CQRSDelete(
            uriTemplate: '/customers/groups/{customerGroupId}',
            CQRSCommand: DeleteCustomerGroupCommand::class,
            scopes: [
                'customer_group_write',
            ],
            // Here, we use query mapping to adapt URI parameters to the expected constructor parameter name
            CQRSCommandMapping: [
                '[customerGroupId]' => '[groupId]',
            ],
        ),
    ],
    exceptionToStatus: [GroupNotFoundException::class => 404],
)]
class CustomerGroup
{
    #[ApiProperty(identifier: true)]
    public int $customerGroupId;

    #[LocalizedValue]
    public array $localizedNames;

    public DecimalNumber $reductionPercent;

    public bool $displayPriceTaxExcluded;

    public bool $showPrice;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $shopIds;
}
