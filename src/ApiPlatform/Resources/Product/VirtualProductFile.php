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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\InvalidProductTypeException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Command\AddVirtualProductFileCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Command\UpdateVirtualProductFileCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Exception\VirtualProductFileConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Exception\VirtualProductFileNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/products/{productId}/virtual-files',
            requirements: ['productId' => '\d+'],
            CQRSCommand: AddVirtualProductFileCommand::class,
            scopes: ['product_write'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/products/virtual-files/{virtualProductFileId}',
            requirements: ['virtualProductFileId' => '\d+'],
            read: false,
            CQRSCommand: UpdateVirtualProductFileCommand::class,
            scopes: ['product_write'],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        VirtualProductFileNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvalidProductTypeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        VirtualProductFileConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class VirtualProductFile
{
    public int $productId;

    #[ApiProperty(identifier: true)]
    public int $virtualProductFileId;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $filePath;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $displayName;

    public ?int $accessDays = null;

    public ?int $downloadTimesLimit = null;

    public ?\DateTimeImmutable $expirationDate = null;
}
