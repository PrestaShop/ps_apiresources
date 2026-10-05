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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CreditSlip;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\PrestaShop\Core\Domain\CreditSlip\Exception\CreditSlipNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\CreditSlip\Query\GetCreditSlipIdsByDateRange;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/credit-slips/ids',
            CQRSQuery: GetCreditSlipIdsByDateRange::class,
            scopes: ['credit_slip_read'],
            CQRSQueryMapping: [
                '[@index][creditSlipId]' => '[creditSlipIds][@index]',
            ],
            parameters: new Parameters([
                new QueryParameter(
                    key: 'dateTimeFrom',
                    required: true,
                    description: 'Start date (Y-m-d)'
                ),
                new QueryParameter(
                    key: 'dateTimeTo',
                    required: true,
                    description: 'End date (Y-m-d)'
                ),
            ]),
            openapiContext: [
                'parameters' => [
                    ['name' => 'dateTimeFrom', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'date']],
                    ['name' => 'dateTimeTo', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'date']],
                ],
            ],
        ),
    ],
    exceptionToStatus: [
        CreditSlipNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CreditSlipIdList
{
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'description' => 'List of credit slip IDs in the date range',
        'items' => ['type' => 'integer'],
    ])]
    public array $creditSlipIds;
}
