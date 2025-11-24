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

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\GetCombinationIds;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\GetEditableCombinationsList;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/{productId}/combinations',
            CQRSQuery: GetCombinationIds::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
                // '[@index][combinationId]' => '[@index]' TODO Test in PS 9.0.2
            ],
            ApiResourceMapping: [
                '[localizedLegends]' => '[legends]',
            ],
        ),
        // TODO: we would like to implement this resource but we need to improve the core
        // new CQRSGetCollection(
        //     uriTemplate: '/product/{productId}/combinations',
        //     CQRSQuery: GetEditableCombinationsList::class,
        //     scopes: [
        //         'product_read',
        //     ],
        //     CQRSQueryMapping: [
        //         '[_context][shopConstraint]' => '[shopConstraint]',
        //         '[_context][langId]' => '[languageId]',
        //         '[combinations]' => '[]',
        //     ],
        // ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductCombinationList
{
    public int $productId;
    public int $combinationId;
    public array $shopIds;
}

// CombinationListForEditing
