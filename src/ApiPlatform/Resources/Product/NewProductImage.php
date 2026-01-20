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
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Command\AddProductImageCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Query\GetProductImage;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/products/{productId}/images',
            inputFormats: ['multipart' => ['multipart/form-data']],
            requirements: ['productId' => '\d+'],
            read: false,
            CQRSCommand: AddProductImageCommand::class,
            CQRSQuery: GetProductImage::class,
            scopes: [
                'product_write',
            ],
            CQRSQueryMapping: NewProductImage::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
                '[image].pathName' => '[pathName]',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class NewProductImage
{
    public int $productId;
    public int $imageId;

    public string $imageUrl;

    public string $thumbnailUrl;

    #[LocalizedValue]
    public array $legends;

    public bool $cover;

    public int $position;

    public array $shopIds;

    public File $image;

    public const QUERY_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[localizedLegends]' => '[legends]',
    ];
}
