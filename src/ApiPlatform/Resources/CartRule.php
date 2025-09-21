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
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
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

    #[Assert\Regex(pattern: '/^[A-Z0-9\-_]+$/i')]
    #[Assert\Length(max: 254)]
    public string $code;

    /**
     * @var array<string, DecimalNumber>|null Minimum amounts per currency (e.g. ['EUR' => '50.00', 'USD' => '60.00'])
     */
    public ?array $minimumAmount = null;

    public bool $minimumAmountShippingIncluded;

    public int $customerId;

    #[LocalizedValue]
    public array $localizedNames;

    public bool $highlightInCart;

    public bool $allowPartialUse;

    public int $priority;

    public bool $active;

    public array $validityDateRange;

    #[Assert\GreaterThanOrEqual(0)]
    public int $totalQuantity;

    #[Assert\GreaterThanOrEqual(0)]
    public int $quantityPerUser;

    public array $cartRuleAction;

    #[Assert\Callback]
    public function validateBusinessRules(ExecutionContextInterface $context): void
    {
        // Check validity date range consistency
        if (isset($this->validityDateRange['from']) && isset($this->validityDateRange['to'])) {
            $from = $this->validityDateRange['from'];
            $to = $this->validityDateRange['to'];

            if ($from instanceof \DateTimeInterface && $to instanceof \DateTimeInterface) {
                if ($from > $to) {
                    $context->buildViolation('The start validity date must be before the end validity date')
                        ->atPath('validityDateRange')
                        ->addViolation();
                }
            }
        }

        // Check minimum amounts (if array with currency keys)
        if (is_array($this->minimumAmount)) {
            foreach ($this->minimumAmount as $currency => $amount) {
                // Validate currency code format (3 uppercase letters)
                if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                    $context->buildViolation('Currency code must consist of 3 uppercase letters')
                        ->atPath('minimumAmount')
                        ->addViolation();
                    break;
                }

                // Validate that amount is a positive or zero DecimalNumber
                if (!$amount instanceof \PrestaShop\Decimal\DecimalNumber) {
                    $context->buildViolation('Minimum amounts must be DecimalNumber instances')
                        ->atPath('minimumAmount')
                        ->addViolation();
                    break;
                }

                if ($amount->isNegative()) {
                    $context->buildViolation('Minimum amounts cannot be negative')
                        ->atPath('minimumAmount')
                        ->addViolation();
                    break;
                }
            }
        }
    }
}
