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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\ImageSettings;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\ImageSettings\Command\EditImageSettingsCommand;
use PrestaShop\PrestaShop\Core\Domain\ImageSettings\Exception\ImageTypeException;
use PrestaShop\PrestaShop\Core\Domain\ImageSettings\Query\GetImageSettingsForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/image-settings',
            openapiContext: ['summary' => 'Get image settings', 'description' => 'Retrieves current image settings configuration'],
            CQRSQuery: GetImageSettingsForEditing::class,
            scopes: [
                'image_settings_read',
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/image-settings',
            openapiContext: ['summary' => 'Edit image settings', 'description' => 'Updates image settings configuration'],
            CQRSCommand: EditImageSettingsCommand::class,
            CQRSQuery: GetImageSettingsForEditing::class,
            scopes: [
                'image_settings_write',
            ],
        ),
    ],
    exceptionToStatus: [
        ImageTypeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ImageSettings
{
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['jpg', 'webp', 'avif']])]
    public array $formats;

    public string $baseFormat;

    public int $avifQuality;

    public int $jpegQuality;

    public int $pngQuality;

    public int $webpQuality;

    public int $generationMethod;

    public int $pictureMaxSize;

    public int $pictureMaxWidth;

    public int $pictureMaxHeight;
}
