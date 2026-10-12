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
 * consecutive calls can answer the same cartId. Its response only holds the identifiers until the cart
 * endpoints exist to return the created cart, hence the experimental flag.
 *
 * The list needs PrestaShop 9.2.1 (PrestaShop/PrestaShop#43116): older cores answer 500 on a cart holding products,
 * as the total was computed without a context cart. The creation works on older cores, except for an unknown
 * customer which gets an orphan cart and 201 instead of 404.
 */
#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/customers/{customerId}/carts',
            requirements: ['customerId' => '\d+'],
            CQRSQuery: GetCustomerCarts::class,
            scopes: ['customer_read'],
            extraProperties: [
                'minVersion' => '9.2.1',
            ],
            openapi: new OpenApiOperation(
                summary: 'List the pending carts of a customer.',
                description: 'Lists the carts of the customer that have not been turned into an order yet, most recent '
                    . 'first. A cart that became an order is no longer listed, see GET /customers/{customerId}/orders. '
                    . 'totalPrice is formatted in the currency of the cart, for display.',
            ),
        ),
        new CQRSCreate(
            uriTemplate: '/customers/{customerId}/carts',
            requirements: ['customerId' => '\d+'],
            allowEmptyBody: true,
            CQRSCommand: CreateEmptyCustomerCartCommand::class,
            scopes: ['customer_write'],
            normalizationContext: ['groups' => ['Create']],
            experimentalOperation: true,
            openapi: new OpenApiOperation(
                summary: 'Create an empty cart for a customer.',
                description: 'Creates an empty cart for the customer and returns its identifier. No body is expected. '
                    . 'When the customer already has an empty cart, that cart is returned instead of a new one. '
                    . 'An unknown customer answers 404 from PrestaShop 9.2.1, older versions create a cart that '
                    . 'belongs to no customer and answer 201. Experimental: the response should be the created cart, '
                    . 'which needs the cart endpoints that are not available yet.',
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
