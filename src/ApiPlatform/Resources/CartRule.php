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

    // minimumAmount est un array qui peut contenir des montants par devise
    // Validation sera faite via callback pour gérer la complexité

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
        // Vérifier cohérence dates de validité
        if (isset($this->validityDateRange['from']) && isset($this->validityDateRange['to'])) {
            $from = $this->validityDateRange['from'];
            $to = $this->validityDateRange['to'];

            if ($from instanceof \DateTimeInterface && $to instanceof \DateTimeInterface) {
                if ($from > $to) {
                    $context->buildViolation('La date de début de validité doit être antérieure à la date de fin')
                        ->atPath('validityDateRange')
                        ->addViolation();
                }
            }
        }

        // Vérifier montants minimum (si array avec clés de devise)
        if (is_array($this->minimumAmount)) {
            foreach ($this->minimumAmount as $currency => $amount) {
                if (is_numeric($amount) && $amount < 0) {
                    $context->buildViolation('Les montants minimum ne peuvent pas être négatifs')
                        ->atPath('minimumAmount')
                        ->addViolation();
                    break;
                }
            }
        }
    }
}
