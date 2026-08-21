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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Attachment;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Exception\AttachmentNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Query\GetAttachmentInformation as GetAttachmentInformationQuery;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/attachments/{attachmentId}/information',
            requirements: ['attachmentId' => '\d+'],
            CQRSQuery: GetAttachmentInformationQuery::class,
            scopes: [
                'attachment_read',
            ],
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
        ),
    ],
    exceptionToStatus: [
        AttachmentNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class AttachmentInformation
{
    #[ApiProperty(identifier: true)]
    public int $attachmentId;

    #[LocalizedValue]
    public array $names;

    #[LocalizedValue]
    public array $descriptions;

    public string $fileName;

    public string $mimeType;

    public int $fileSize;

    public const API_RESOURCE_MAPPING = [
        '[localizedNames]' => '[names]',
        '[localizedDescriptions]' => '[descriptions]',
    ];
}
