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
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CannotDeleteCombinationException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CannotUpdateCombinationException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\GetCombinationForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/products/{combinationId}',
            requirements: ['combinationId' => '\d+'],
            CQRSCommand: UpdateCombinationCommand::class,
            CQRSQuery: GetCombinationForEditing::class,
            scopes: ['product_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/products/{combinationId}',
            requirements: ['combinationId' => '\d+'],
            CQRSCommand: DeleteCombinationCommand::class,
            scopes: ['product_write'],
        ),
    ],
    exceptionToStatus: [
        CombinationNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotUpdateCombinationException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotDeleteCombinationException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CombinationUpdate
{
    #[ApiProperty(identifier: true)]
    public int $combinationId;

    public ?bool $isDefault = null;

    public ?string $gtin = null;

    public ?string $isbn = null;

    public ?string $mpn = null;

    public ?string $reference = null;

    public ?string $upc = null;

    public ?string $impactOnWeight = null;

    public ?string $impactOnPrice = null;

    public ?string $ecoTax = null;

    public ?string $impactOnUnitPrice = null;

    public ?string $wholesalePrice = null;

    public ?int $minimalQuantity = null;

    public ?int $lowStockThreshold = null;

    public ?string $availableDate = null;

    #[LocalizedValue]
    public ?array $localizedAvailableNowLabels = null;

    #[LocalizedValue]
    public ?array $localizedAvailableLaterLabels = null;
}
