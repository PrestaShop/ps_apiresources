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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Currency;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\BulkDeleteCurrenciesCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Command\BulkToggleCurrenciesStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\BulkDeleteCurrenciesException;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\BulkToggleCurrenciesException;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyConstraintException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The bulk operations of the domain share this class: the body of each operation is documented from its
 * command, so only the fields that command takes appear; the validation group keeps enabled optional for
 * the delete.
 *
 * The currency bulk handlers do not extend AbstractBulkCommandHandler: they collect the ids they could not
 * process and throw one exception for the whole batch, so a partial failure answers 422, not 207.
 */
#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/currencies/bulk-delete',
            CQRSCommand: BulkDeleteCurrenciesCommand::class,
            scopes: ['currency_write'],
            allowEmptyBody: false,
            openapi: new OpenApiOperation(
                summary: 'Delete several currencies.',
                description: 'Deletes the listed currencies. A currency that cannot be deleted (unknown, or the default '
                    . 'currency of a shop) does not stop the others, and the response is then a 422.',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/currencies/bulk-update-status',
            output: false,
            validationContext: ['groups' => ['Default', 'BulkUpdateStatus']],
            CQRSCommand: BulkToggleCurrenciesStatusCommand::class,
            CQRSCommandMapping: [
                '[enabled]' => '[expectedStatus]',
            ],
            scopes: ['currency_write'],
            openapi: new OpenApiOperation(
                summary: 'Enable or disable several currencies.',
                description: 'Sets the status of the listed currencies. A currency that already has the status is '
                    . 'left as is, so disabling an unknown currency succeeds; enabling an unknown currency, or '
                    . 'disabling the default currency of a shop, answers 422 once the others are processed.',
            ),
        ),
    ],
    exceptionToStatus: [
        // Raised once for the whole batch, listing the ids that failed (unknown, default currency...)
        BulkDeleteCurrenciesException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        BulkToggleCurrenciesException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // Raised by the CurrencyId value object of each listed id
        CurrencyConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkCurrencies
{
    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['example' => [1, 3]])]
    #[Assert\NotBlank]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $currencyIds;

    #[ApiProperty(openapiContext: ['description' => 'Status to set on every listed currency.'])]
    #[Assert\NotNull(groups: ['BulkUpdateStatus'])]
    public bool $enabled;
}
