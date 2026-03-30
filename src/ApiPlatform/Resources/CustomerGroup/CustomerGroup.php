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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CustomerGroup;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Command\AddCustomerGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Command\DeleteCustomerGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Command\EditCustomerGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Exception\CannotAddGroupException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Exception\CannotDeleteGroupException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Exception\CannotUpdateGroupException;
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
            uriTemplate: '/customer-groups/{customerGroupId}',
            requirements: ['customerGroupId' => '\d+'],
            CQRSQuery: GetCustomerGroupForEditing::class,
            scopes: ['customer_group_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/customer-groups',
            CQRSCommand: AddCustomerGroupCommand::class,
            CQRSQuery: GetCustomerGroupForEditing::class,
            scopes: ['customer_group_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/customer-groups/{customerGroupId}',
            requirements: ['customerGroupId' => '\d+'],
            CQRSCommand: EditCustomerGroupCommand::class,
            CQRSQuery: GetCustomerGroupForEditing::class,
            scopes: ['customer_group_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/customer-groups/{customerGroupId}',
            requirements: ['customerGroupId' => '\d+'],
            CQRSCommand: DeleteCustomerGroupCommand::class,
            scopes: ['customer_group_write'],
        ),
    ],
    exceptionToStatus: [
        GroupConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        GroupNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotAddGroupException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateGroupException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotDeleteGroupException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CustomerGroup
{
    #[ApiProperty(identifier: true)]
    public int $customerGroupId;

    #[LocalizedValue]
    #[Assert\NotBlank]
    public array $names;

    public string $reduction;

    public bool $displayPriceTaxExcluded;

    public bool $showPrice;

    /**
     * @var int[]
     */
    public array $shopIds;

    public const QUERY_MAPPING = [
        '[id]' => '[customerGroupId]',
        '[localizedNames]' => '[names]',
    ];

    public const COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[reduction]' => '[reductionPercent]',
    ];
}
