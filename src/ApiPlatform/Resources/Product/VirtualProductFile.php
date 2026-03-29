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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Command\AddVirtualProductFileCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Command\DeleteVirtualProductFileCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Command\UpdateVirtualProductFileCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Exception\VirtualProductFileConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Exception\VirtualProductFileNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/products/{productId}/virtual-product-file',
            requirements: ['productId' => '\d+'],
            output: false,
            CQRSCommand: AddVirtualProductFileCommand::class,
            scopes: ['product_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/virtual-product-files/{virtualProductFileId}',
            requirements: ['virtualProductFileId' => '\d+'],
            output: false,
            CQRSCommand: UpdateVirtualProductFileCommand::class,
            scopes: ['product_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/virtual-product-files/{virtualProductFileId}',
            requirements: ['virtualProductFileId' => '\d+'],
            CQRSCommand: DeleteVirtualProductFileCommand::class,
            scopes: ['product_write'],
        ),
    ],
    exceptionToStatus: [
        VirtualProductFileNotFoundException::class => Response::HTTP_NOT_FOUND,
        VirtualProductFileConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class VirtualProductFile
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    public int $virtualProductFileId;

    #[Assert\NotBlank]
    public string $filename;

    public int $expirationDays = 0;

    public int $downloadsAllowed = 0;

    public string $accessDays = '';
}
