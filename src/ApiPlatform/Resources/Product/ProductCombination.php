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
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Command\DeleteCombinationCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Command\UpdateCombinationCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\GetCombinationForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/combinations/{combinationId}',
            requirements: ['combinationId' => '\\d+'],
            CQRSQuery: GetCombinationForEditing::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: ProductCombination::QUERY_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/products/combinations/{combinationId}',
            requirements: ['combinationId' => '\\d+'],
            output: false,
            CQRSCommand: DeleteCombinationCommand::class,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/products/combinations/{combinationId}',
            requirements: ['combinationId' => '\\d+'],
            CQRSCommand: UpdateCombinationCommand::class,
            CQRSQuery: GetCombinationForEditing::class,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
                '[availableNowLabels]' => '[localizedAvailableNowLabels]',
                '[availableLaterLabels]' => '[localizedAvailableLaterLabels]',
                '[impactOnPrice]' => '[impactOnPrice]',
                '[impactOnWeight]' => '[impactOnWeight]',
                '[impactOnUnitPrice]' => '[impactOnUnitPrice]',
                '[wholesalePrice]' => '[wholesalePrice]',
                '[minimalQuantity]' => '[minimalQuantity]',
            ],
            CQRSQueryMapping: ProductCombination::QUERY_MAPPING,
        ),
    ],
    exceptionToStatus: [
        CombinationNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductCombination
{
    #[ApiProperty(identifier: true)]
    public int $combinationId;

    public ?bool $isDefault = null;

    public ?string $gtin = null;

    public ?string $isbn = null;

    public ?string $mpn = null;

    public ?string $reference = null;

    public ?string $upc = null;

    public ?float $impactOnWeight = null;

    public ?float $impactOnPrice = null;

    public ?float $ecoTax = null;

    public ?float $impactOnUnitPrice = null;

    public ?float $wholesalePrice = null;

    public ?int $minimalQuantity = null;

    public ?int $lowStockThreshold = null;

    public ?\DateTimeImmutable $availableDate = null;

    #[LocalizedValue]
    public ?array $availableNowLabels = null;

    #[LocalizedValue]
    public ?array $availableLaterLabels = null;

    public const QUERY_MAPPING = [
        // inputs (for CQRSQuery construction)
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[_context][uriVariables][combinationId]' => '[combinationId]',
        // identifiers
        '[combinationId]' => '[combinationId]',
        // root flags
        '[isDefault]' => '[isDefault]',
        // details → flat fields
        '[details][reference]' => '[reference]',
        '[details][gtin]' => '[gtin]',
        '[details][isbn]' => '[isbn]',
        '[details][mpn]' => '[mpn]',
        '[details][upc]' => '[upc]',
        '[details][impactOnWeight]' => '[impactOnWeight]',
        // prices → flat fields
        '[prices][impactOnPrice]' => '[impactOnPrice]',
        '[prices][ecotax]' => '[ecoTax]',
        '[prices][impactOnUnitPrice]' => '[impactOnUnitPrice]',
        '[prices][wholesalePrice]' => '[wholesalePrice]',
        // stock → flat/localized fields
        '[stock][minimalQuantity]' => '[minimalQuantity]',
        '[stock][lowStockThreshold]' => '[lowStockThreshold]',
        '[stock][availableDate]' => '[availableDate]',
        '[stock][localizedAvailableNowLabels]' => '[availableNowLabels]',
        '[stock][localizedAvailableLaterLabels]' => '[availableLaterLabels]',
    ];
}
