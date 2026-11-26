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
use PrestaShop\PrestaShop\Core\Domain\Attachment\Exception\AttachmentConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Exception\AttachmentNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Attachment\Command\RemoveAllAssociatedProductAttachmentsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Attachment\Command\SetAssociatedProductAttachmentsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Query\GetProductForEditing;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\ShopAssociationNotFound;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{productId}/attachments',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Get the attachments of a product.',
                description: 'The ids of the attachments associated with the product.',
            ),
            CQRSQuery: GetProductForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['product_read'],
        ),
        new CQRSUpdate(
            uriTemplate: '/products/{productId}/attachments',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Set the attachments of a product.',
                description: 'Replaces the attachments associated with the product and answers them like the GET. '
                    . 'An unknown product or attachment answers 404 and nothing is changed.',
            ),
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: SetAssociatedProductAttachmentsCommand::class,
            CQRSQuery: GetProductForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['product_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/products/{productId}/attachments',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Remove all the attachments of a product.',
                description: 'Requires PrestaShop 9.2.2. Answers the attachments of the product, an empty list.',
            ),
            CQRSCommand: RemoveAllAssociatedProductAttachmentsCommand::class,
            scopes: ['product_write'],
            extraProperties: [
                // A CQRSDelete answers the resource read back by a query from PrestaShop 9.2.2, the arguments are
                // passed as extra properties so that the resource still loads on the older cores
                'CQRSQuery' => GetProductForEditing::class,
                'CQRSQueryMapping' => self::QUERY_MAPPING,
                'minVersion' => '9.2.2',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        ShopAssociationNotFound::class => Response::HTTP_NOT_FOUND,
        AttachmentConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        AttachmentNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductAttachments
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    /**
     * The ids of the attachments of the product, an empty list without attachments.
     *
     * @var int[]|null
     */
    #[ApiProperty(openapiContext: ['example' => [1, 3]])]
    #[Assert\NotBlank(groups: ['Update'])]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    // The mapping of the query result only fills the list when the product has attachments
    public ?array $attachmentIds = [];

    public const QUERY_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[_context][langId]' => '[displayLanguageId]',
        '[associatedAttachments][@index][attachmentId]' => '[attachmentIds][@index]',
    ];
}
