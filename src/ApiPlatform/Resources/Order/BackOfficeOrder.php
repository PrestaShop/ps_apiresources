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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Order;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Employee\ValueObject\NoEmployeeId;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\AddOrderFromBackOfficeCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\Exception\InvalidModuleException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/orders',
            CQRSCommand: AddOrderFromBackOfficeCommand::class,
            scopes: ['order_write'],
        ),
    ],
    exceptionToStatus: [
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvalidModuleException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        OrderException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BackOfficeOrder
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\GreaterThan(0)]
        public int $cartId = 0,

        /**
         * Technical name of the installed payment module driving validateOrder(),
         * e.g. 'ps_wirepayment', 'ps_checkpayment', 'boorder'.
         */
        #[Assert\NotBlank]
        public string $paymentModuleName = '',

        #[Assert\NotBlank]
        #[Assert\GreaterThan(0)]
        public int $orderStateId = 0,

        /**
         * Employee that placed the order. Defaults to NoEmployeeId::NO_EMPLOYEE_ID_VALUE (0)
         * — this also short-circuits the translator-dependent "Manual order —
         * Employee: …" internal note code path.
         */
        public int $employeeId = NoEmployeeId::NO_EMPLOYEE_ID_VALUE,

        public string $orderMessage = '',
    ) {
    }
}
