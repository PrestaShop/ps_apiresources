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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\CleanHtml;
use PrestaShop\PrestaShop\Core\Domain\Order\Invoice\Command\UpdateInvoiceNoteCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Invoice\Exception\InvoiceException;
use PrestaShop\PrestaShop\Core\Domain\Order\Invoice\Exception\InvoiceNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An invoice has its own identifier, so it lives under the domain root. No query reads an invoice by its identifier:
 * the operation answers 204 and the note is read back in the documents of GET /orders/{orderId}.
 */
#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/orders/invoices/{orderInvoiceId}',
            requirements: ['orderInvoiceId' => '\d+'],
            // No read, so that an omitted note reaches the NotNull constraint
            read: false,
            output: false,
            CQRSCommand: UpdateInvoiceNoteCommand::class,
            scopes: ['order_write'],
            validationContext: ['groups' => ['Default', 'Update']],
            openapi: new OpenApiOperation(
                summary: 'Set the note of an order invoice.',
                description: 'Replaces the note printed on an invoice (documentId of a document of type invoice, read in '
                    . 'GET /orders/{orderId}). No query reads an invoice alone: the response is a 204 and the note is '
                    . 'read back in the documents of the order.',
            ),
        ),
    ],
    exceptionToStatus: [
        // The first matching class wins: InvoiceNotFoundException extends InvoiceException
        InvoiceNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvoiceException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderInvoice
{
    #[ApiProperty(identifier: true)]
    public int $orderInvoiceId;

    // Nullable so that an explicit null reaches the NotNull constraint and gets the same 422 as an omitted field
    #[ApiProperty(openapiContext: ['description' => 'Note printed on the invoice, an empty string removes it.'])]
    #[Assert\NotNull(groups: ['Update'])]
    #[CleanHtml]
    public ?string $note;
}
