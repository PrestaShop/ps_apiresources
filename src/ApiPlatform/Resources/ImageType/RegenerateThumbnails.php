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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\ImageType;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\ImageSettings\Command\RegenerateThumbnailsCommand;
use PrestaShop\PrestaShop\Core\Domain\ImageSettings\Exception\ImageTypeNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\ImageSettings\Exception\RegenerateThumbnailsException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/image-types/regenerate-thumbnails',
            read: false,
            output: false,
            CQRSCommand: RegenerateThumbnailsCommand::class,
            scopes: [
                'image_type_write',
            ],
        ),
    ],
    exceptionToStatus: [
        ImageTypeNotFoundException::class => Response::HTTP_NOT_FOUND,
        RegenerateThumbnailsException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class RegenerateThumbnails
{
    /**
     * Image domain to regenerate: all, categories, manufacturers, suppliers, products, stores.
     */
    public string $image;

    /**
     * Restrict to a single image type id, or 0 to regenerate every image type of the domain.
     */
    public int $imageTypeId;

    public bool $erasePreviousImages;
}
