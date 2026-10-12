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
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Exception\GroupConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Exception\GroupNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Query\GetCustomerGroupForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/customers/groups/{customerGroupId}',
            CQRSQuery: GetCustomerGroupForEditing::class,
            scopes: [
                'customer_group_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/customers/groups',
            CQRSCommand: AddCustomerGroupCommand::class,
            CQRSQuery: GetCustomerGroupForEditing::class,
            scopes: [
                'customer_group_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[groupId]' => '[customerGroupId]',
                '[names]' => '[localizedNames]',
            ],
            extraProperties: [
                // The shops of the context are used only when the payload has no shopIds (PrestaShop 9.2.0 and later,
                // shopIds is required on older versions)
                'defaultValues' => ['shopIds' => '[_context][shopIds]'],
            ],
            validationContext: ['groups' => ['Default', 'Create']],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/customers/groups/{customerGroupId}',
            read: false,
            CQRSCommand: EditCustomerGroupCommand::class,
            CQRSQuery: GetCustomerGroupForEditing::class,
            scopes: [
                'customer_group_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[names]' => '[localizedNames]',
            ],
            validationContext: ['groups' => ['Default', 'Update']],
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
    exceptionToStatus: [
        GroupConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        GroupNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CustomerGroup
{
    #[ApiProperty(identifier: true)]
    public int $customerGroupId;

    #[LocalizedValue]
    public array $names;

    public DecimalNumber $reductionPercent;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $displayPriceTaxExcluded;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $showPrice;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $shopIds;

    public const QUERY_MAPPING = [
        '[id]' => '[customerGroupId]',
        '[localizedNames]' => '[names]',
        '[reduction]' => '[reductionPercent]',
    ];
}
