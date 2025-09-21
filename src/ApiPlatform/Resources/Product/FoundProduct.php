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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Product\Query\SearchProducts;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/search/{phrase}/{resultsLimit}/{isoCode}',
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'phrase',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                    [
                        'name' => 'resultsLimit',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'int',
                        ],
                    ],
                    [
                        'name' => 'isoCode',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                    [
                        'name' => 'orderId',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'int',
                        ],
                    ],
                ],
            ],
            CQRSQuery: SearchProducts::class
        ),
    ],
)]
class FoundProduct
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    public bool $availableOutOfStock;

    public string $name;

    #[Assert\Range(min: 0, max: 100)]
    public DecimalNumber $taxRate;

    public string $formattedPrice;

    #[Assert\GreaterThanOrEqual(0)]
    public DecimalNumber $priceTaxIncl;

    #[Assert\GreaterThanOrEqual(0)]
    public DecimalNumber $priceTaxExcl;

    public int $stock;

    public string $location;

    public array $combinations;

    public array $customizationFields;

    #[Assert\Callback]
    public function validatePriceConsistency(ExecutionContextInterface $context): void
    {
        // Check price consistency (tax included vs tax excluded)
        if ($this->priceTaxIncl < $this->priceTaxExcl) {
            $context->buildViolation('The price with tax must be greater than or equal to the price without tax')
                ->atPath('priceTaxIncl')
                ->addViolation();
        }

        // Check consistency with tax rate
        if ($this->taxRate > 0 && $this->priceTaxExcl > 0) {
            $expectedTaxIncl = $this->priceTaxExcl->plus(
                $this->priceTaxExcl->times($this->taxRate->dividedBy(100))
            );
            if (!$this->priceTaxIncl->equals($expectedTaxIncl)) {
                $context->buildViolation('The price with tax does not match the calculation with the tax rate')
                    ->atPath('priceTaxIncl')
                    ->addViolation();
            }
        }
    }
}
