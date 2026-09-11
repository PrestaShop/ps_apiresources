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
use PrestaShop\PrestaShop\Core\Domain\Attachment\Query\GetAttachmentForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
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
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/attachments/{attachmentId}',
            requirements: ['attachmentId' => '\d+'],
            read: false,
            CQRSCommand: EditAttachmentCommand::class,
            CQRSQuery: GetAttachmentForEditing::class,
            scopes: ['attachment_write'],
            inputFormats: ['multipart' => ['multipart/form-data']],
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
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
    ],
)]
class Attachment
{
    #[ApiProperty(identifier: true)]
    public int $attachmentId;

    #[LocalizedValue]
    #[Assert\NotBlank(groups: ['Create'])]
    public array $names;

    #[LocalizedValue]
    public array $descriptions;

    public ?File $file;

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
