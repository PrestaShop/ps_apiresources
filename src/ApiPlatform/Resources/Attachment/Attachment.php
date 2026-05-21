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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Attachment;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Command\AddAttachmentCommand;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Command\DeleteAttachmentCommand;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Command\EditAttachmentCommand;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Exception\AttachmentConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Exception\AttachmentNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Query\GetAttachmentForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/attachments/{attachmentId}',
            requirements: ['attachmentId' => '\d+'],
            CQRSQuery: GetAttachmentForEditing::class,
            scopes: [
                'attachment_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/attachments',
            inputFormats: ['multipart' => ['multipart/form-data']],
            // Form data value are all string so we disable type enforcement
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddAttachmentCommand::class,
            CQRSQuery: GetAttachmentForEditing::class,
            scopes: [
                'attachment_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSUpdate(
            // We have to force POST request, because we cannot use PUT with files AND data
            method: CQRSUpdate::METHOD_POST,
            uriTemplate: '/attachments/{attachmentId}',
            requirements: ['attachmentId' => '\d+'],
            inputFormats: ['multipart' => ['multipart/form-data']],
            status: Response::HTTP_OK,
            validationContext: ['groups' => ['Default', 'Update']],
            // Form data value are all string so we disable type enforcement
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            CQRSCommand: EditAttachmentCommand::class,
            CQRSQuery: GetAttachmentForEditing::class,
            scopes: [
                'attachment_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/attachments/{attachmentId}',
            requirements: ['attachmentId' => '\d+'],
            CQRSCommand: DeleteAttachmentCommand::class,
            scopes: [
                'attachment_write',
            ],
        ),
    ],
    exceptionToStatus: [
        AttachmentConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        AttachmentNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Attachment
{
    #[ApiProperty(identifier: true)]
    public int $attachmentId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'names', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_CATALOG_NAME,
        ]),
    ])]
    public array $names;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'descriptions')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'descriptions', allowNull: true)]
    public array $descriptions;

    public ?File $attachment = null;

    public const QUERY_MAPPING = [
        '[name]' => '[names]',
        '[description]' => '[descriptions]',
    ];

    public const COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[descriptions]' => '[localizedDescriptions]',
        '[attachment].pathName' => '[pathName]',
        '[attachment].size' => '[fileSize]',
        '[attachment].mimeType' => '[mimeType]',
        '[attachment].originalName' => '[originalName]',
        '[attachment].clientOriginalName' => '[originalName]',
    ];
}
