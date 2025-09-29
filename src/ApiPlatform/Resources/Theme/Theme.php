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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Theme;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Module\APIResources\ApiPlatform\Serializer\Theme as ThemeSerializer;
use PrestaShop\PrestaShop\Core\Domain\Theme\Command\AdaptThemeToRTLLanguagesCommand;
use PrestaShop\PrestaShop\Core\Domain\Theme\Command\DeleteThemeCommand;
use PrestaShop\PrestaShop\Core\Domain\Theme\Command\EnableThemeCommand;
use PrestaShop\PrestaShop\Core\Domain\Theme\Command\ResetThemeLayoutsCommand;
use PrestaShop\PrestaShop\Core\Domain\Theme\Exception\CannotEnableThemeException;
use PrestaShop\PrestaShop\Core\Domain\Theme\Exception\ThemeConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Theme\ValueObject\ThemeName;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/themes/{themeName}',
            output: false,
            CQRSCommand: DeleteThemeCommand::class,
            scopes: ['theme_write'],
            denormalizationContext: [
                'callbacks' => [
                    'themeName' => [ThemeSerializer::class, 'toThemeName'],
                ],
            ]
        ),
        new CQRSUpdate(
            uriTemplate: '/themes/{themeName}/adapt-to-rtl',
            output: false,
            allowEmptyBody: true,
            CQRSCommand: AdaptThemeToRTLLanguagesCommand::class,
            scopes: ['theme_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/themes/{themeName}/enable',
            output: false,
            allowEmptyBody: true,
            CQRSCommand: EnableThemeCommand::class,
            scopes: ['theme_write'],
            exceptionToStatus: [
                CannotEnableThemeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
                ThemeConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/themes/{themeName}/reset',
            output: false,
            allowEmptyBody: true,
            CQRSCommand: ResetThemeLayoutsCommand::class,
            scopes: ['theme_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    denormalizationContext: [
        'callbacks' => [
            'themeName' => [ThemeSerializer::class, 'toThemeName'],
        ],
    ],
)]
class Theme
{
    public ThemeName $themeName;
}
