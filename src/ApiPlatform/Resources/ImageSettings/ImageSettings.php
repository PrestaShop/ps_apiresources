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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\ImageSettings\Command\EditImageSettingsCommand;
use PrestaShop\PrestaShop\Core\Domain\ImageSettings\Query\GetImageSettingsForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/image-settings',
            CQRSQuery: GetImageSettingsForEditing::class,
            scopes: ['image_settings_read'],
            openapi: new OpenApiOperation(
                summary: 'Get the image settings.',
                description: 'Returns the global image generation settings (Design > Image settings in the back office). '
                    . 'This is a singleton resource: it has no identifier.',
            ),
        ),
        new CQRSUpdate(
            uriTemplate: '/image-settings',
            CQRSCommand: EditImageSettingsCommand::class,
            CQRSQuery: GetImageSettingsForEditing::class,
            scopes: ['image_settings_write'],
            openapi: new OpenApiOperation(
                summary: 'Replace the image settings.',
                description: 'Replaces all the image generation settings and returns them. Every field is required: formats, '
                    . 'baseFormat, avifQuality, jpegQuality, pngQuality, webpQuality, generationMethod, pictureMaxSize, '
                    . 'pictureMaxWidth and pictureMaxHeight. jpg is always added to formats.',
            ),
        ),
    ],
)]
class ImageSettings
{
    /**
     * @var string[]
     */
    #[ApiProperty(openapiContext: [
        'description' => 'The image formats to generate: jpg is the base image (encoded as baseFormat says, always with a .jpg extension), always generated and added when missing; webp and avif are optional.',
        'example' => ['jpg', 'webp'],
    ])]
    #[Assert\NotNull]
    #[Assert\All([new Assert\Choice(['jpg', 'webp', 'avif'])])]
    public ?array $formats = null;

    #[ApiProperty(openapiContext: ['description' => 'Encoding of the base image: jpg always JPEG, png PNG only when the source image is a PNG, png_all always PNG.'])]
    #[Assert\NotNull]
    #[Assert\Choice(['jpg', 'png', 'png_all'])]
    public ?string $baseFormat = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 100)]
    public ?int $avifQuality = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 100)]
    public ?int $jpegQuality = null;

    #[ApiProperty(openapiContext: ['description' => 'Lossless PNG compression level, from 0 (biggest file) to 9 (smallest file, slowest decompression).'])]
    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 9)]
    public ?int $pngQuality = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 100)]
    public ?int $webpQuality = null;

    #[ApiProperty(openapiContext: ['description' => 'The side of the source image the thumbnails are based on: 0 automatic (longest side), 1 width, 2 height.'])]
    #[Assert\NotNull]
    #[Assert\Choice([0, 1, 2])]
    public ?int $generationMethod = null;

    #[ApiProperty(openapiContext: ['description' => 'Maximum file size of the product customization pictures customers upload, in bytes.'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    public ?int $pictureMaxSize = null;

    #[ApiProperty(openapiContext: ['description' => 'Width of the product customization pictures customers upload, in pixels.'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    public ?int $pictureMaxWidth = null;

    #[ApiProperty(openapiContext: ['description' => 'Height of the product customization pictures customers upload, in pixels.'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    public ?int $pictureMaxHeight = null;
}
