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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\Dto\CustomerAddressInformation;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\Dto\CustomerCartInformation;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\Dto\CustomerDiscountInformation;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\Dto\CustomerGeneralInformation;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\Dto\CustomerGroupInformation;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\Dto\CustomerLastConnection;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\Dto\CustomerMessageInformation;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\Dto\CustomerOrdersInformation;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\Dto\CustomerPersonalInformation;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\Dto\CustomerProductsInformation;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\Dto\CustomerSentEmail;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\CleanHtml;
use PrestaShop\PrestaShop\Core\Domain\Customer\Command\SetPrivateNoteAboutCustomerCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Query\GetCustomerForViewing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/customers/{customerId}/details',
            requirements: ['customerId' => '\d+'],
            CQRSQuery: GetCustomerForViewing::class,
            scopes: [
                'customer_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            openapi: new OpenApiOperation(
                summary: 'Get the details of a customer.',
                description: 'Returns the information displayed on the customer page of the back office. Dates, birthday and '
                    . 'amounts are formatted for display. The carts, products, discounts and addresses lists are not filled '
                    . 'by the core query yet and are always empty.',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/customers/{customerId}/set-private-note',
            requirements: ['customerId' => '\d+'],
            // No read, so that an omitted privateNote reaches the NotNull constraint
            read: false,
            CQRSCommand: SetPrivateNoteAboutCustomerCommand::class,
            CQRSQuery: GetCustomerForViewing::class,
            scopes: ['customer_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            validationContext: ['groups' => ['Default', 'Update']],
            openapi: new OpenApiOperation(
                summary: 'Set the private note about a customer.',
                description: 'Replaces the private note visible only in the back office, and returns the customer details '
                    . 'where the note is read back in generalInformation.privateNote.',
            ),
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        CustomerNotFoundException::class => Response::HTTP_NOT_FOUND,
        CustomerConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CustomerDetails
{
    #[ApiProperty(identifier: true, openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $customerId;

    public CustomerPersonalInformation $personalInformation;

    public CustomerOrdersInformation $ordersInformation;

    /**
     * @var CustomerCartInformation[]
     */
    public array $cartsInformation;

    public CustomerProductsInformation $productsInformation;

    /**
     * @var CustomerMessageInformation[]
     */
    public array $messagesInformation;

    /**
     * @var CustomerDiscountInformation[]
     */
    public array $discountsInformation;

    /**
     * @var CustomerSentEmail[]
     */
    public array $sentEmailsInformation;

    /**
     * @var CustomerLastConnection[]
     */
    public array $lastConnectionsInformation;

    /**
     * @var CustomerGroupInformation[]
     */
    public array $groupsInformation;

    /**
     * @var CustomerAddressInformation[]
     */
    public array $addressesInformation;

    public CustomerGeneralInformation $generalInformation;

    /**
     * Input of PATCH /set-private-note only, the note is read back in generalInformation. Nullable so that an
     * explicit null reaches the NotNull constraint and gets the same 422 as an omitted field, instead of a 400
     * from the denormalizer.
     */
    #[ApiProperty(readable: false)]
    #[Assert\NotNull]
    #[CleanHtml]
    public ?string $privateNote;

    public const QUERY_MAPPING = [
        '[customerId]' => '[customerId]',
        // The orders are raw database rows in the query result
        '[ordersInformation][validOrders][@index][id_order]' => '[ordersInformation][validOrders][@index][orderId]',
        '[ordersInformation][validOrders][@index][total_paid_tax_incl]' => '[ordersInformation][validOrders][@index][totalPaidTaxIncl]',
        '[ordersInformation][validOrders][@index][conversion_rate]' => '[ordersInformation][validOrders][@index][conversionRate]',
        '[ordersInformation][invalidOrders][@index][id_order]' => '[ordersInformation][invalidOrders][@index][orderId]',
        '[ordersInformation][invalidOrders][@index][total_paid_tax_incl]' => '[ordersInformation][invalidOrders][@index][totalPaidTaxIncl]',
        '[ordersInformation][invalidOrders][@index][conversion_rate]' => '[ordersInformation][invalidOrders][@index][conversionRate]',
    ];
}
