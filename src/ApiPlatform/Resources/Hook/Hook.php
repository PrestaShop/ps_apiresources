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
 * @author    Pascal Cescon <pascal.cescon@gmail.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Hook;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Hook\Command\UpdateHookStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Hook\Exception\CannotUpdateHookException;
use PrestaShop\PrestaShop\Core\Domain\Hook\Exception\HookNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Hook\Query\GetHook;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/hooks/{hookId}',
            requirements: ['hookId' => '\d+'],
            CQRSQuery: GetHook::class,
            scopes: ['hook_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSUpdate(
            uriTemplate: '/hooks/{hookId}/update-status',
            requirements: ['hookId' => '\d+'],
            output: false,
            CQRSCommand: UpdateHookStatusCommand::class,
            scopes: ['hook_write'],
        ),
    ],
    exceptionToStatus: [
        HookNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotUpdateHookException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Hook
{
    #[ApiProperty(identifier: true)]
    public int $hookId;

    public string $name;

    public string $title;

    public string $description;

    public bool $active;

    public const QUERY_MAPPING = [
        '[id]' => '[hookId]',
        '[isActive]' => '[active]',
    ];
}
