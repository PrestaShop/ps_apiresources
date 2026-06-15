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
        new CQRSCreate(
            uriTemplate: '/image-types',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddImageTypeCommand::class,
            scopes: ['image_type_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/image-types/{imageTypeId}',
            requirements: ['imageTypeId' => '\d+'],
            output: false,
            CQRSCommand: DeleteImageTypeCommand::class,
            scopes: ['image_type_write'],
        ),
        new CQRSGet(
            uriTemplate: '/image-types/{imageTypeId}',
            requirements: ['imageTypeId' => '\d+'],
            CQRSQuery: GetImageTypeForEditing::class,
            scopes: ['image_type_read'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/image-types/{imageTypeId}',
            requirements: ['imageTypeId' => '\d+'],
            read: false,
            CQRSCommand: EditImageTypeCommand::class,
            CQRSQuery: GetImageTypeForEditing::class,
            scopes: ['image_type_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        ImageTypeNotFoundException::class => Response::HTTP_NOT_FOUND,
        ImageTypeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ImageType
{
    #[ApiProperty(identifier: true)]
    public int $imageTypeId;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $name;

    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Positive(groups: ['Create'])]
    public int $width;

    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Positive(groups: ['Create'])]
    public int $height;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $products;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $categories;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $manufacturers;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $suppliers;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $stores;
}
