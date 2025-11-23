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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Store;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Store\Command\DeleteStoreCommand;
use PrestaShop\PrestaShop\Core\Domain\Store\Command\ToggleStoreStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Store\Exception\StoreNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Store\Query\GetStoreForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/stores/{storeId}',
            requirements: ['storeId' => '\d+'],
            output: false,
            CQRSCommand: DeleteStoreCommand::class,
            scopes: ['store_write']
        ),
        new CQRSGet(
            uriTemplate: '/stores/{storeId}',
            requirements: ['storeId' => '\d+'],
            CQRSQuery: GetStoreForEditing::class,
            scopes: ['store_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSUpdate(
            uriTemplate: '/stores/{storeId}/toggle-status',
            requirements: ['storeId' => '\d+'],
            output: false,
            allowEmptyBody: true,
            CQRSCommand: ToggleStoreStatusCommand::class,
            scopes: ['store_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        StoreNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Store
{
    #[ApiProperty(identifier: true)]
    public int $storeId;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $enabled;

    public const COMMAND_MAPPING = [
        '[enabled]' => '[active]',
    ];

    public const QUERY_MAPPING = [
        '[active]' => '[enabled]',
    ];
}
