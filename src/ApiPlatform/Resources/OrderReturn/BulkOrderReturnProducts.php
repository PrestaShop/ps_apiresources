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

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\OrderReturn\Dto\OrderReturnProductRow;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Command\BulkDeleteProductsFromOrderReturnCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\CannotDeleteLastProductFromOrderReturnException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnConstraintException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/order-returns/{orderReturnId}/products/bulk-delete',
            requirements: ['orderReturnId' => '\d+'],
            extraProperties: [
                'minVersion' => '9.2.0',
            ],
            CQRSCommand: BulkDeleteProductsFromOrderReturnCommand::class,
            // The command reads the column names of the rows
            CQRSCommandMapping: [
                '[stagedProductRows][@index][orderDetailId]' => '[stagedProductRows][@index][order_detail_id]',
                '[stagedProductRows][@index][customizationId]' => '[stagedProductRows][@index][customization_id]',
            ],
            scopes: ['order_return_write'],
            // The body is required, the CQRSDelete default allows an empty one
            allowEmptyBody: false,
        ),
    ],
    exceptionToStatus: [
        OrderReturnNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderReturnConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // A merchandise return must keep at least one product: a client error, not a 500
        CannotDeleteLastProductFromOrderReturnException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkOrderReturnProducts
{
    public int $orderReturnId;

    /**
     * The lines to remove.
     *
     * @var OrderReturnProductRow[]
     */
    #[Assert\NotBlank]
    #[Assert\Valid]
    public array $stagedProductRows;
}
