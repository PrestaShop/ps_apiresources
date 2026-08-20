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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Shop;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Exception\FileUploadException;
use PrestaShop\PrestaShop\Core\Domain\Shop\Command\UploadLogosCommand;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\NotSupportedFaviconExtensionException;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\NotSupportedLogoImageExtensionException;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\NotSupportedMailAndInvoiceImageExtensionException;
use PrestaShop\PrestaShop\Core\Domain\Shop\Query\GetLogosPaths;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * The logos of the shop. This is a singleton resource: it has no identifier and the GET and
 * the PUT share the same URI.
 *
 * Read and write are not the same shape here — the write side takes uploaded files, the read
 * side returns the paths the core stored them at — so the uploaded* properties are declared
 * readable: false and stay out of the responses.
 *
 * The upload answers an empty 204 rather than replaying GetLogosPaths: the result of an upload
 * is read back with the GET on the same URI.
 */
#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/shops/logos',
            CQRSQuery: GetLogosPaths::class,
            scopes: ['shop_read'],
        ),
        new CQRSUpdate(
            uriTemplate: '/shops/logos',
            read: false,
            CQRSCommand: UploadLogosCommand::class,
            // Replays the query of the GET, so the upload answers with the resulting paths
            CQRSQuery: GetLogosPaths::class,
            scopes: ['shop_write'],
            inputFormats: ['multipart' => ['multipart/form-data']],
            // Form data values are all strings/files, so disable type enforcement.
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
        ),
    ],
    exceptionToStatus: [
        NotSupportedLogoImageExtensionException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        NotSupportedMailAndInvoiceImageExtensionException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        NotSupportedFaviconExtensionException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        FileUploadException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ShopLogos
{
    /**
     * The four paths are readable on both operations and are never sent in: the upload takes
     * files, not paths. They are deliberately not marked writable: false — that flag also
     * keeps a property out of the responses in this stack, which emptied GET /shops/logos.
     * The two places on dev that use it (AttributeList, FeatureValueList) pair it with
     * readable: false to hide a property in both directions.
     */
    public string $headerLogoPath;

    public string $mailLogoPath;

    public string $invoiceLogoPath;

    public string $faviconPath;

    #[ApiProperty(readable: false)]
    public ?File $uploadedHeaderLogo;

    #[ApiProperty(readable: false)]
    public ?File $uploadedMailLogo;

    #[ApiProperty(readable: false)]
    public ?File $uploadedInvoiceLogo;

    #[ApiProperty(readable: false)]
    public ?File $uploadedFavicon;
}
