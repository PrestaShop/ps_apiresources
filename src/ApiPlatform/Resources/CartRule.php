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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\CartRule\Command\EditCartRuleCommand;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/cart-rule/{cartRuleId}',
            CQRSCommand: EditCartRuleCommand::class,
            scopes: [
                'cart_rule_write',
            ],
            experimentalOperation: true,
        ),
    ],
)]
class CartRule
{
    #[ApiProperty(identifier: true)]
    public int $cartRuleId;

    public string $description;

    public string $code;

    public array $minimumAmount;

    public bool $minimumAmountShippingIncluded;

    public int $customerId;

    #[LocalizedValue]
    public array $localizedNames;

    public bool $highlightInCart;

    public bool $allowPartialUse;

    public int $priority;

    public bool $active;

    public array $validityDateRange;

    public int $totalQuantity;

    public int $quantityPerUser;

    public array $cartRuleAction;
}
