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
use PrestaShop\PrestaShop\Core\Domain\Theme\Command\ImportThemeCommand;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/themes/import',
            output: false,
            CQRSCommand: ImportThemeCommand::class,
            CQRSCommandMapping: [
                '[importSource]' => '[importSource]',
            ],
            normalizationContext: [
                'skip_null_values' => false,
            ],
            denormalizationContext: [
                'disable_type_enforcement' => true,
                'allow_extra_attributes' => true,
                'callbacks' => [
                    'importSource' => [ThemeSerializer::class, 'toThemeImportSource'],
                ],
            ],
        ),
    ],
)]
class ThemeImport
{
    public $importSource;
}
