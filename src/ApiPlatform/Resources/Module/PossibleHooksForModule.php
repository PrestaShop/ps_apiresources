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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Module;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Hook\Query\GetPossibleHooksForModule;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/modules/{moduleId}/possible-hooks',
            // GetPossibleHooksForModule was introduced in 9.2.0
            extraProperties: [
                'minVersion' => '9.2.0',
            ],
            CQRSQuery: GetPossibleHooksForModule::class,
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            scopes: ['module_read'],
        ),
    ],
)]
class PossibleHooksForModule
{
    /**
     * HookableInfo exposes the hook id as "id", which ApiPlatform would otherwise take for the
     * identifier of this resource — and then fail to resolve it from a URI that only carries
     * {moduleId}, answering 404 "Invalid identifier value or configuration".
     */
    public const API_RESOURCE_MAPPING = [
        '[id]' => '[hookId]',
    ];

    /**
     * The module the hooks are listed for, taken from the URI. Declared like ProductImageList
     * does for productId.
     */
    public int $moduleId;

    public int $hookId;

    public string $name;

    public string $title;

    /**
     * Whether the module is already hooked there.
     */
    public bool $registered;
}
