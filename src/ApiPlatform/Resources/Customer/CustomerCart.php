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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\CreateEmptyCustomerCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Query\GetCustomerCarts;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * One cart of a customer, as summarized by GetCustomerCarts.
 *
 * The query returns a list, so this is a collection operation, declared like ProductImageList:
 * QueryResultSerializerTrait only wraps a query result behind "_queryResult" when it is a
 * scalar, so the ['[_queryResult]' => '[carts]'] mapping the source PR used could never fill
 * anything and the response came back with the identifier alone.
 *
 * Only the carts that were never turned into an order are listed: the core calls
 * Cart::getCustomerCarts($customerId, false), so a customer whose carts all became orders
 * gets an empty list.
 *
 * The create operation adds an empty cart, the way the back office does when an order is created for
 * the customer. The core reuses the last empty cart of the customer when there is one, so two
 * consecutive calls can answer the same cartId. Its response only holds the identifiers.
 */
#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/customers/{customerId}/carts',
            requirements: ['customerId' => '\d+'],
            CQRSQuery: GetCustomerCarts::class,
            scopes: ['customer_read'],
        ),
        new CQRSCreate(
            uriTemplate: '/customers/{customerId}/carts',
            requirements: ['customerId' => '\d+'],
            allowEmptyBody: true,
            CQRSCommand: CreateEmptyCustomerCartCommand::class,
            scopes: ['customer_write'],
            normalizationContext: ['groups' => ['Create']],
            openapi: new OpenApiOperation(
                summary: 'Create an empty cart for a customer.',
                description: 'Creates an empty cart for the customer and returns its identifier. No body is expected. '
                    . 'When the customer already has an empty cart, that cart is returned instead of a new one.',
            ),
        ),
    ],
    exceptionToStatus: [
        CustomerNotFoundException::class => Response::HTTP_NOT_FOUND,
        CustomerConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CustomerCart
{
    #[Groups(['Default', 'Create'])]
    public int $customerId;

    #[Groups(['Default', 'Create'])]
    public int $cartId;

    public string $creationDate;

    public string $totalPrice;
}
