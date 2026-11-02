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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\OrderReturn;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\OrderReturn\Dto\OrderReturnCustomizationField;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\OrderReturn\Dto\OrderReturnProductRow;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Command\BulkDeleteProductsFromOrderReturnCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Command\DeleteProductFromOrderReturnCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\CannotDeleteLastProductFromOrderReturnException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnConstraintException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Query\GetOrderReturnProducts;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDeleteCollection;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/order-returns/{orderReturnId}/products',
            requirements: ['orderReturnId' => '\d+'],
            extraProperties: [
                'minVersion' => '9.2.0',
            ],
            openapi: new OpenApiOperation(
                summary: 'List the product lines of an order return.',
                description: 'Not paginated. The customized lines come first. customizationId is 0 on a line without '
                    . 'customization. Before PrestaShop 9.2.1, the list also holds the customized lines of the other '
                    . 'returns of the same order, lists a customized line twice and customizationFields is always empty.',
            ),
            CQRSQuery: GetOrderReturnProducts::class,
            scopes: ['order_return_read'],
        ),
        new CQRSDeleteCollection(
            uriTemplate: '/order-returns/{orderReturnId}/products/bulk-delete',
            requirements: ['orderReturnId' => '\d+'],
            extraProperties: [
                'minVersion' => '9.2.1',
            ],
            openapi: new OpenApiOperation(
                summary: 'Remove several product lines from an order return.',
                description: 'Requires PrestaShop 9.2.1. The body is required. Answers the remaining lines, like '
                    . 'GET /order-returns/{orderReturnId}/products. An order return keeps at least one line (422).',
            ),
            CQRSCommand: BulkDeleteProductsFromOrderReturnCommand::class,
            // The command reads the column names of the rows
            CQRSCommandMapping: [
                '[stagedProductRows][@index][orderDetailId]' => '[stagedProductRows][@index][order_detail_id]',
                '[stagedProductRows][@index][customizationId]' => '[stagedProductRows][@index][customization_id]',
            ],
            CQRSQuery: GetOrderReturnProducts::class,
            scopes: ['order_return_write'],
            allowEmptyBody: false,
            // The single line delete has no body: only this operation requires the rows
            validationContext: ['groups' => ['Default', 'BulkDelete']],
        ),
        new CQRSDeleteCollection(
            uriTemplate: '/order-returns/{orderReturnId}/products/{orderDetailId}',
            requirements: ['orderReturnId' => '\d+', 'orderDetailId' => '\d+'],
            extraProperties: [
                'minVersion' => '9.2.1',
            ],
            openapi: new OpenApiOperation(
                summary: 'Remove a product line from an order return.',
                description: 'Requires PrestaShop 9.2.1. Removes the line of the order detail that has no '
                    . 'customization, an unknown line answers 404. To remove a customized line, use DELETE '
                    . '/order-returns/{orderReturnId}/products/bulk-delete with its customizationId. Answers the '
                    . 'remaining lines. An order return keeps at least one line (422).',
            ),
            // Only the URI variables and the body reach the command: the customization stays 0
            CQRSCommand: DeleteProductFromOrderReturnCommand::class,
            CQRSQuery: GetOrderReturnProducts::class,
            scopes: ['order_return_write'],
        ),
    ],
    exceptionToStatus: [
        OrderReturnNotFoundException::class => Response::HTTP_NOT_FOUND,
        // OrderReturnId rejects 0
        OrderReturnConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // A merchandise return must keep at least one product: a client error, not a 500
        CannotDeleteLastProductFromOrderReturnException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderReturnProductList
{
    public int $orderDetailId;

    public int $customizationId;

    public string $reference;

    public string $productName;

    public int $quantity;

    public bool $customization;

    /**
     * Customer-provided data of a customized line (file thumbnails, text inputs), empty for a classic line.
     * Filled from PrestaShop 9.2.1.
     *
     * @var OrderReturnCustomizationField[]
     */
    public array $customizationFields;

    // Types the URI variable for the commands, neither in the lines nor in the body
    #[ApiProperty(readable: false, writable: false)]
    public int $orderReturnId;

    /**
     * The lines to remove, only read by the bulk delete.
     *
     * @var OrderReturnProductRow[]
     */
    #[ApiProperty(readable: false)]
    #[Assert\NotBlank(groups: ['BulkDelete'])]
    #[Assert\Valid]
    public array $stagedProductRows;
}
