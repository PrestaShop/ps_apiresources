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
use PrestaShop\PrestaShop\Core\Domain\CreditSlip\Query\GetCreditSlipIdsByDateRange;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use PrestaShop\PrestaShop\Core\Domain\CreditSlip\Exception\CreditSlipException;
use PrestaShop\PrestaShop\Core\Domain\CreditSlip\Exception\CreditSlipNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/credit-slip/{dateFrom}/{dateTo}',
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'dateFrom',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                    ],
                    [
                        'name' => 'dateTo',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                    ],
                ],
            ],
            CQRSQuery: GetCreditSlipIdsByDateRange::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['slip_read'],
        ),
    ],
    exceptionToStatus: [
        CreditSlipConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CreditSlipNotFoundException::class => Response::HTTP_NOT_FOUND,
        CreditSlipException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CreditSlip
{
    #[Assert\NotBlank]
    #[Assert\Date]
    public ?string $dateFrom = null;

    #[Assert\NotBlank]
    #[Assert\Date]
    public ?string $dateTo = null;

    public const QUERY_MAPPING = [
        '[dateFrom]' => '[dateTimeFrom]',
        '[dateTo]' => '[dateTimeTo]',
    ];
}
