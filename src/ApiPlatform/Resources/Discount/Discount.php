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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Discount;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Discount\Command\AddDiscountCommand;
use PrestaShop\PrestaShop\Core\Domain\Discount\Command\DeleteDiscountCommand;
use PrestaShop\PrestaShop\Core\Domain\Discount\Exception\DiscountConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Discount\Exception\DiscountNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Discount\Query\GetDiscountForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/discount/{discountId}',
            requirements: ['discountId' => '\d+'],
            CQRSQuery: GetDiscountForEditing::class,
            scopes: ['discount_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/discount',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddDiscountCommand::class,
            CQRSQuery: GetDiscountForEditing::class,
            scopes: ['discount_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/discount/{discountId}',
            CQRSCommand: DeleteDiscountCommand::class,
            scopes: [
                'discount_write',
            ],
        ),
    ],
    exceptionToStatus: [
        DiscountNotFoundException::class => Response::HTTP_NOT_FOUND,
        DiscountConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Discount
{
    #[ApiProperty(identifier: true)]
    public int $discountId;
    #[Assert\NotBlank(groups: ['Create'])]
    #[LocalizedValue]
    public array $names;
    public int $priority;
    public bool $active;
    public \DateTimeImmutable $validFrom;
    public \DateTimeImmutable $validTo;
    #[Assert\GreaterThanOrEqual(0)]
    public int $totalQuantity;
    #[Assert\GreaterThanOrEqual(0)]
    public int $quantityPerUser;
    public string $description;
    #[Assert\Regex(pattern: '/^[A-Z0-9\-_]+$/i')]
    #[Assert\Length(max: 254)]
    public string $code;
    public int $customerId;
    public bool $highlightInCart;
    public bool $allowPartialUse;
    #[Assert\NotBlank(groups: ['Create'])]
    public string $type;
    #[Assert\Range(min: 0, max: 100)]
    public ?DecimalNumber $percentDiscount;
    #[Assert\GreaterThanOrEqual(0)]
    public ?DecimalNumber $amountDiscount;
    public int $currencyId;
    public bool $isTaxIncluded;
    public int $productId;
    public array $combinations;
    public int $reductionProduct;

    protected const QUERY_MAPPING = [
        '[localizedNames]' => '[names]',
    ];
    protected const COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
    ];

    #[Assert\Callback]
    public function validateBusinessRules(ExecutionContextInterface $context): void
    {
        // Check validity date range consistency
        if ($this->validFrom > $this->validTo) {
            $context->buildViolation('The start validity date must be before the end validity date')
                ->atPath('validFrom')
                ->addViolation();
        }

        // Check discount type logic
        if ($this->type === 'percent' && $this->percentDiscount === null) {
            $context->buildViolation('A discount percentage is required for the "percent" type')
                ->atPath('percentDiscount')
                ->addViolation();
        }

        if ($this->type === 'amount' && $this->amountDiscount === null) {
            $context->buildViolation('A discount amount is required for the "amount" type')
                ->atPath('amountDiscount')
                ->addViolation();
        }

        // Check that only one discount type is defined
        if ($this->percentDiscount !== null && $this->amountDiscount !== null) {
            $context->buildViolation('You can only define one discount type (percentage or amount)')
                ->atPath('percentDiscount')
                ->addViolation();
        }
    }
}
