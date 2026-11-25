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
use PrestaShop\PrestaShop\Core\Domain\Attachment\Command\AddAttachmentCommand;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Command\DeleteAttachmentCommand;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Command\EditAttachmentCommand;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Exception\AttachmentConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Exception\AttachmentNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Exception\EmptyFileException;
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
            scopes: ['attachment_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            extraProperties: [
                // GetAttachmentForEditing accepts the attachmentId the API builds it from only from PrestaShop 9.2.1
                // (PrestaShop/PrestaShop#42124), older cores answer 500
                'minVersion' => '9.2.1',
            ],
        ),
        new CQRSCreate(
            uriTemplate: '/attachments',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddAttachmentCommand::class,
            CQRSQuery: GetAttachmentForEditing::class,
            scopes: ['attachment_write'],
            inputFormats: ['multipart' => ['multipart/form-data']],
            // Form data values are all strings so we disable type enforcement.
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
            extraProperties: [
                // GetAttachmentForEditing accepts the attachmentId the API builds it from only from PrestaShop 9.2.1
                // (PrestaShop/PrestaShop#42124), older cores answer 500
                'minVersion' => '9.2.1',
            ],
        ),
        // A POST, since PHP only parses the multipart body of a POST request. EditAttachmentHandler writes the
        // names and the descriptions whatever was sent, so both are required, the file is optional.
        new CQRSUpdate(
            method: CQRSUpdate::METHOD_POST,
            uriTemplate: '/attachments/{attachmentId}',
            requirements: ['attachmentId' => '\d+'],
            status: Response::HTTP_OK,
            read: false,
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditAttachmentCommand::class,
            CQRSQuery: GetAttachmentForEditing::class,
            scopes: ['attachment_write'],
            inputFormats: ['multipart' => ['multipart/form-data']],
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
            extraProperties: [
                // GetAttachmentForEditing accepts the attachmentId the API builds it from only from PrestaShop 9.2.1
                // (PrestaShop/PrestaShop#42124), older cores answer 500
                'minVersion' => '9.2.1',
            ],
        ),
        new CQRSDelete(
            uriTemplate: '/attachments/{attachmentId}',
            requirements: ['attachmentId' => '\d+'],
            CQRSCommand: DeleteAttachmentCommand::class,
            scopes: ['attachment_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        AttachmentNotFoundException::class => Response::HTTP_NOT_FOUND,
        AttachmentConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        EmptyFileException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Attachment
{
    #[ApiProperty(identifier: true)]
    public int $attachmentId;

    #[LocalizedValue]
    #[Assert\NotBlank(groups: ['Create', 'Update'])]
    public array $names;

    #[LocalizedValue]
    #[Assert\NotNull(groups: ['Create', 'Update'])]
    public array $descriptions;

    /**
     * Write-only: the file, sent as the `file` part of the multipart request. Required to add an attachment,
     * optional on the update, which keeps the current file without it. The responses expose the stored file
     * through fileName. Not nullable, like VirtualProductFile::$file: an initialized null is normalized into the
     * response despite readable: false.
     */
    #[ApiProperty(readable: false, writable: false)]
    #[Assert\NotNull(groups: ['Create'])]
    public File $file;

    public string $fileName;

    public const QUERY_MAPPING = [
        '[name]' => '[names]',
        '[description]' => '[descriptions]',
    ];

    // AddAttachmentCommand takes the localized names/descriptions as constructor arguments and the
    // file information through the multi-parameter setter setFileInformation(pathName, fileSize,
    // mimeType, originalName). The mapper fills that setter from a nested "fileInformation" object
    // whose keys match the parameter names, reading them from the uploaded file.
    public const CREATE_COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[descriptions]' => '[localizedDescriptions]',
        '[file].pathName' => '[fileInformation][pathName]',
        '[file].size' => '[fileInformation][fileSize]',
        '[file].mimeType' => '[fileInformation][mimeType]',
        '[file].clientOriginalName' => '[fileInformation][originalName]',
    ];

    // EditAttachmentCommand exposes the file through setFileInfo(pathName, mimeType, originalFileName,
    // fileSize) - a different setter name and parameter order than the add command.
    public const UPDATE_COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[descriptions]' => '[localizedDescriptions]',
        '[file].pathName' => '[fileInfo][pathName]',
        '[file].mimeType' => '[fileInfo][mimeType]',
        '[file].clientOriginalName' => '[fileInfo][originalFileName]',
        '[file].size' => '[fileInfo][fileSize]',
    ];
}
