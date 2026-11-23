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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Module\APIResources\ApiPlatform\Metadata\CQRSDeleteCollection;
use PrestaShop\Module\APIResources\ApiPlatform\Metadata\CQRSUpdateCollection;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\RemoveAllRelatedProductsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\SetRelatedProductsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Query\GetRelatedProducts;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/{productId}/related-products',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'List the related products of a product.',
                description: 'Each item is a related product (accessory) of the product of the URI: its id, its name '
                    . 'in the language of the context, its reference and the URL of its cover image.',
            ),
            CQRSQuery: GetRelatedProducts::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['product_read'],
        ),
        new CQRSUpdateCollection(
            uriTemplate: '/products/{productId}/related-products',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Set the related products of a product.',
                description: 'Requires PrestaShop 9.2.2. Replaces the related products of the product and answers '
                    . 'them like the GET. An unknown product, in the URI or in relatedProductIds, answers 404 and '
                    . 'nothing is changed.',
            ),
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: SetRelatedProductsCommand::class,
            CQRSQuery: GetRelatedProducts::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['product_write'],
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
        ),
        new CQRSDeleteCollection(
            uriTemplate: '/products/{productId}/related-products',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Remove all the related products of a product.',
                description: 'Requires PrestaShop 9.2.2. Answers the related products of the product, an empty list.',
            ),
            CQRSCommand: RemoveAllRelatedProductsCommand::class,
            CQRSQuery: GetRelatedProducts::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['product_write'],
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class RelatedProductList
{
    /**
     * The id of the related product (the product of the URI in the requests).
     */
    #[ApiProperty(identifier: true)]
    public int $productId;

    public string $name;

    public string $reference;

    public string $imageUrl;

    /**
     * The related products to set, only read by the update.
     *
     * @var int[]|null
     */
    #[ApiProperty(readable: false, openapiContext: ['example' => [2, 3]])]
    #[Assert\NotBlank(groups: ['Update'])]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public ?array $relatedProductIds = null;

    public const QUERY_MAPPING = [
        '[_context][langId]' => '[languageId]',
    ];
}
