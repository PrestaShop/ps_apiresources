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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\ImageType;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\ImageSettings\Command\AddImageTypeCommand;
use PrestaShop\PrestaShop\Core\Domain\ImageSettings\Command\DeleteImageTypeCommand;
use PrestaShop\PrestaShop\Core\Domain\ImageSettings\Command\EditImageTypeCommand;
use PrestaShop\PrestaShop\Core\Domain\ImageSettings\Exception\ImageTypeException;
use PrestaShop\PrestaShop\Core\Domain\ImageSettings\Exception\ImageTypeNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\ImageSettings\Query\GetImageTypeForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/image-types/{imageTypeId}',
            requirements: ['imageTypeId' => '\d+'],
            CQRSQuery: GetImageTypeForEditing::class,
            scopes: ['image_type_read'],
        ),
        new CQRSCreate(
            uriTemplate: '/image-types',
            CQRSCommand: AddImageTypeCommand::class,
            CQRSQuery: GetImageTypeForEditing::class,
            scopes: ['image_type_write'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/image-types/{imageTypeId}',
            requirements: ['imageTypeId' => '\d+'],
            CQRSCommand: EditImageTypeCommand::class,
            CQRSQuery: GetImageTypeForEditing::class,
            scopes: ['image_type_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/image-types/{imageTypeId}',
            requirements: ['imageTypeId' => '\d+'],
            CQRSCommand: DeleteImageTypeCommand::class,
            scopes: ['image_type_write'],
        ),
    ],
    exceptionToStatus: [
        ImageTypeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ImageTypeNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ImageType
{
    #[ApiProperty(identifier: true)]
    public int $imageTypeId;

    #[Assert\NotBlank]
    public string $name;

    public int $width;

    public int $height;

    public bool $products;

    public bool $categories;

    public bool $manufacturers;

    public bool $suppliers;

    public bool $stores;
}
