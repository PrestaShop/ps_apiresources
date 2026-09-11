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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Query\GetCustomerCarts;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * One cart of a customer, as summarized by GetCustomerCarts.
 *
 * The query returns a list, so this is a collection operation, declared like ProductImageList:
 * QueryResultSerializerTrait only wraps a query result behind "_queryResult" when it is a
 * scalar, so the ['[_queryResult]' => '[carts]'] mapping the source PR used could never fill
 * anything and the response came back with the identifier alone.
 */
#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/customers/{customerId}/carts',
            CQRSQuery: GetCustomerCarts::class,
            scopes: ['customer_read'],
        ),
    ],
    exceptionToStatus: [
        CustomerNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CustomerCart
{
    public int $customerId;

    public int $cartId;

    public string $creationDate;

    public string $totalPrice;
}
