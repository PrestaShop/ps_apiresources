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
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Command\BulkDeleteOrderReturnsCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnConstraintException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/order-returns/bulk-delete',
            extraProperties: [
                'minVersion' => '9.2.0',
            ],
            CQRSCommand: BulkDeleteOrderReturnsCommand::class,
            scopes: ['order_return_write'],
            // The body is required, the CQRSDelete default allows an empty one
            allowEmptyBody: false,
        ),
    ],
    exceptionToStatus: [
        OrderReturnNotFoundException::class => Response::HTTP_NOT_FOUND,
        // Thrown by the command constructor on an invalid id
        OrderReturnConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkOrderReturns
{
    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['example' => [1, 3]])]
    #[Assert\NotBlank]
    // The command casts each id with (int): "3abc" would act on order return 3
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $orderReturnIds;
}
