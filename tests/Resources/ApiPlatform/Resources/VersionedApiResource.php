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

namespace PsApiResourcesTest\Resources\ApiPlatform\Resources;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Hook\Exception\HookNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Hook\Query\GetHook;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;

/**
 * Test resource for VersionedEndpointsTest: it is NOT part of the module resources, it is copied into the
 * module's src/ApiPlatform/Resources folder during the test only, then removed, so it never pollutes the
 * OpenApi documentation of a real shop.
 *
 * The version gating is declared via extraProperties on purpose: the minVersion/maxVersion named constructor
 * arguments only exist in the core operation classes since PrestaShop 9.2 while this resource must compile
 * on every supported core version.
 *
 * The operations reuse the existing hook_read scope (available on every supported core version), and the
 * version boundaries are chosen to produce a different filtering combination on each core version of the
 * CI matrix (9.0.x, 9.1.x, 9.2.x, develop).
 */
#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/test/module/versioned/always/hook/{hookId}',
            requirements: ['hookId' => '\d+'],
            exceptionToStatus: [HookNotFoundException::class => 404],
            CQRSQuery: GetHook::class,
            scopes: ['hook_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSGet(
            uriTemplate: '/test/module/versioned/min-91/hook/{hookId}',
            requirements: ['hookId' => '\d+'],
            exceptionToStatus: [HookNotFoundException::class => 404],
            CQRSQuery: GetHook::class,
            scopes: ['hook_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            extraProperties: ['minVersion' => '9.1.0'],
        ),
        new CQRSGet(
            uriTemplate: '/test/module/versioned/min-92/hook/{hookId}',
            requirements: ['hookId' => '\d+'],
            exceptionToStatus: [HookNotFoundException::class => 404],
            CQRSQuery: GetHook::class,
            scopes: ['hook_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            extraProperties: ['minVersion' => '9.2.0'],
        ),
        new CQRSGet(
            uriTemplate: '/test/module/versioned/max-91/hook/{hookId}',
            requirements: ['hookId' => '\d+'],
            exceptionToStatus: [HookNotFoundException::class => 404],
            CQRSQuery: GetHook::class,
            scopes: ['hook_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            extraProperties: ['maxVersion' => '9.1.9999'],
        ),
        new CQRSGet(
            uriTemplate: '/test/module/versioned/never/hook/{hookId}',
            requirements: ['hookId' => '\d+'],
            exceptionToStatus: [HookNotFoundException::class => 404],
            CQRSQuery: GetHook::class,
            scopes: ['hook_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            extraProperties: ['minVersion' => '99.99.99'],
        ),
    ],
)]
class VersionedApiResource
{
    #[ApiProperty(identifier: true)]
    public int $hookId;

    public bool $enabled;

    public string $name;

    public string $title;

    public string $description;

    protected const QUERY_MAPPING = [
        // Transforms the url hookId parameter into the $id parameter for GetHook
        '[hookId]' => '[id]',
        // Transforms the query result Hook::getId into the Api resource hookId
        '[id]' => '[hookId]',
        '[active]' => '[enabled]',
    ];
}
