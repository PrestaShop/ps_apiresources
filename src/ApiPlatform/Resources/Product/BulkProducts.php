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
use PrestaShop\PrestaShop\Core\Domain\Product\Command\BulkDeleteProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\BulkDuplicateProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\BulkUpdateProductStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\BulkProductException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The bulk operations of the domain share this class: the body of each operation is documented from its
 * command, so only the fields that command takes appear; the validation groups keep the field of one
 * operation optional for the others.
 */
#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/products/bulk-update-status',
            output: false,
            status: Response::HTTP_NO_CONTENT,
            validationContext: ['groups' => ['Default', 'BulkUpdateStatus']],
            CQRSCommand: BulkUpdateProductStatusCommand::class,
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
                '[enabled]' => '[newStatus]',
            ],
            scopes: ['product_write'],
            openapi: new OpenApiOperation(
                summary: 'Enable or disable several products.',
                description: 'Sets the status of the listed products. A product that cannot be updated (unknown) '
                    . 'does not stop the others, the response is then a 422 once all the products are processed.',
            ),
        ),
        new CQRSCreate(
            uriTemplate: '/products/bulk-duplicate',
            output: false,
            status: Response::HTTP_NO_CONTENT,
            CQRSCommand: BulkDuplicateProductCommand::class,
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
            scopes: ['product_write'],
            openapi: new OpenApiOperation(
                summary: 'Duplicate several products.',
                description: 'Creates a copy of each listed product, disabled and named "copy of" its source like '
                    . 'POST /products/{productId}/duplicate. A product that cannot be duplicated (unknown) does not '
                    . 'stop the others, the response is then a 422 once all the products are processed.',
            ),
        ),
        new CQRSDelete(
            uriTemplate: '/products/bulk-delete',
            allowEmptyBody: false,
            CQRSCommand: BulkDeleteProductCommand::class,
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
            scopes: ['product_write'],
            openapi: new OpenApiOperation(
                summary: 'Delete several products.',
                description: 'Deletes the listed products. A product that cannot be deleted (unknown) does not stop '
                    . 'the others, the response is then a 422 once all the products are processed.',
            ),
        ),
    ],
    exceptionToStatus: [
        // An invalid id of the list, refused before any product is processed
        ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // The failed products of a bulk action: the product bulk exceptions don't implement the core
        // BulkCommandExceptionInterface, so they answer a 422 instead of the 207 detailing each product
        BulkProductException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkProducts
{
    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['example' => [1, 3]])]
    #[Assert\NotBlank]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $productIds;

    #[ApiProperty(openapiContext: ['description' => 'Status to set on every listed product.'])]
    #[Assert\NotNull(groups: ['BulkUpdateStatus'])]
    public bool $enabled;
}
