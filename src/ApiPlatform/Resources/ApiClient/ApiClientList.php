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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\ApiClient;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Search\Filters\ApiClientFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/api-clients',
            scopes: [
                'api_client_read',
            ],
            ApiResourceMapping: [
                '[id_api_client]' => '[apiClientId]',
                '[client_id]' => '[clientId]',
                '[client_name]' => '[clientName]',
                '[external_issuer]' => '[externalIssuer]',
            ],
            gridDataFactory: 'prestashop.core.grid.data_factory.api_client',
            filtersClass: ApiClientFilters::class,
            filtersMapping: [
                '[apiClientId]' => '[id_api_client]',
                '[clientId]' => '[client_id]',
                '[clientName]' => '[client_name]',
                '[externalIssuer]' => '[external_issuer]',
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class ApiClientList
{
    #[ApiProperty(identifier: true)]
    public int $apiClientId;

    public string $clientId;

    public string $clientName;

    public string $description;

    public ?string $externalIssuer;

    public bool $enabled;

    public int $lifetime;
}
