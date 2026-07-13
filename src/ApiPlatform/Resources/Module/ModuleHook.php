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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Hook\Command\EditHookedModuleCommand;
use PrestaShop\PrestaShop\Core\Domain\Hook\Command\HookModuleCommand;
use PrestaShop\PrestaShop\Core\Domain\Hook\Exception\HookException;
use PrestaShop\PrestaShop\Core\Domain\Hook\Exception\HookNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/modules/{moduleId}/hooks',
            requirements: ['moduleId' => '\d+'],
            CQRSCommand: HookModuleCommand::class,
            scopes: ['module_write'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/modules/{moduleId}/hooks/{hookId}',
            requirements: ['moduleId' => '\d+', 'hookId' => '\d+'],
            read: false,
            CQRSCommand: EditHookedModuleCommand::class,
            scopes: ['module_write'],
        ),
    ],
    exceptionToStatus: [
        HookNotFoundException::class => Response::HTTP_NOT_FOUND,
        HookException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ModuleHook
{
    public int $moduleId;

    #[ApiProperty(identifier: true)]
    public int $hookId;

    public ?int $newHookId = null;

    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $exceptions = [];
}
