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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Language;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\Language\Command\AddLanguageCommand;
use PrestaShop\PrestaShop\Core\Domain\Language\Command\DeleteLanguageCommand;
use PrestaShop\PrestaShop\Core\Domain\Language\Command\EditLanguageCommand;
use PrestaShop\PrestaShop\Core\Domain\Language\Command\ToggleLanguageStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\DefaultLanguageException;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageException;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Language\Query\GetLanguageForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

/*
 * Every operation of the domain requires PrestaShop 9.3.0: before PrestaShop/PrestaShop#42852, the core declares its
 * own Language resource on /languages/{languageId} and answers 404 (NotExposedHttpException) on that route, so the
 * language cannot be read back there, and the write operations return it through the same query.
 */
#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/languages/{languageId}',
            requirements: ['languageId' => '\d+'],
            CQRSQuery: GetLanguageForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['language_read'],
            extraProperties: [
                'minVersion' => '9.3.0',
            ],
        ),
        new CQRSCreate(
            uriTemplate: '/languages',
            // The flag and the "no picture" image are required, like in the BO form, so the payload can only be sent
            // as multipart form data. Form data values are all strings, hence the disabled type enforcement.
            inputFormats: ['multipart' => ['multipart/form-data']],
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddLanguageCommand::class,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
            CQRSQuery: GetLanguageForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['language_write'],
            extraProperties: [
                'minVersion' => '9.3.0',
                'defaultValues' => self::CREATE_DEFAULT_VALUES,
            ],
            openapi: new OpenApiOperation(
                summary: 'Create a language.',
                description: 'Creates a language and returns it. The flag (`flagImage`) and the "no picture" image '
                    . '(`noPictureImage`) are required, so the request is sent as multipart form data with the other '
                    . 'fields as form values (booleans as 1 / 0, the shops as `shopIds[]`).',
            ),
        ),
        // The update with images is a POST and not a PATCH because a file can only be uploaded through a POST
        // request: PHP fills the uploaded files of the request for that method only. It still updates the provided
        // fields only; the JSON PATCH below updates the fields without the images.
        new CQRSUpdate(
            method: CQRSUpdate::METHOD_POST,
            uriTemplate: '/languages/{languageId}',
            requirements: ['languageId' => '\d+'],
            inputFormats: ['multipart' => ['multipart/form-data']],
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            status: Response::HTTP_OK,
            read: false,
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditLanguageCommand::class,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
            CQRSQuery: GetLanguageForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['language_write'],
            extraProperties: [
                'minVersion' => '9.3.0',
            ],
            openapi: new OpenApiOperation(
                summary: 'Update a language with its images.',
                description: 'Updates an existing language and returns it. Only the fields present in the payload are '
                    . 'modified. The request is sent as multipart form data, to replace the flag (`flagImage`) and/or '
                    . 'the "no picture" image (`noPictureImage`). This operation relies on POST and not on PATCH '
                    . 'because a file can only be uploaded through a POST request, but it never creates a language; '
                    . 'the JSON PATCH on the same URI updates the other fields.',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/languages/{languageId}',
            requirements: ['languageId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditLanguageCommand::class,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
            CQRSQuery: GetLanguageForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['language_write'],
            extraProperties: [
                'minVersion' => '9.3.0',
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/languages/{languageId}/set-status',
            requirements: ['languageId' => '\d+'],
            validationContext: ['groups' => ['Default', 'SetStatus']],
            CQRSCommand: ToggleLanguageStatusCommand::class,
            CQRSCommandMapping: self::STATUS_COMMAND_MAPPING,
            CQRSQuery: GetLanguageForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['language_write'],
            extraProperties: [
                'minVersion' => '9.3.0',
            ],
        ),
        new CQRSDelete(
            uriTemplate: '/languages/{languageId}',
            requirements: ['languageId' => '\d+'],
            CQRSCommand: DeleteLanguageCommand::class,
            scopes: ['language_write'],
            extraProperties: [
                'minVersion' => '9.3.0',
            ],
        ),
    ],
    exceptionToStatus: [
        LanguageNotFoundException::class => Response::HTTP_NOT_FOUND,
        DefaultLanguageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        LanguageConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        LanguageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Language
{
    #[ApiProperty(identifier: true)]
    public int $languageId;

    // Same rules as the BO form, and the 32 characters of Language::$definition
    #[Assert\NotBlank(groups: ['Create'])]
    #[TypedRegex(['type' => TypedRegex::TYPE_GENERIC_NAME])]
    #[Assert\Length(max: 32)]
    public string $name;

    #[Assert\NotBlank(groups: ['Create'])]
    #[TypedRegex(['type' => TypedRegex::TYPE_LANGUAGE_ISO_CODE])]
    public string $isoCode;

    #[Assert\NotBlank(groups: ['Create'])]
    #[TypedRegex(['type' => TypedRegex::TYPE_LANGUAGE_CODE])]
    public string $tagIETF;

    #[ApiProperty(
        writable: false,
        openapiContext: ['description' => 'Read only: derived by the core from the ISO code, empty when it is unknown.'],
    )]
    public string $locale;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Regex(pattern: '/^[^<>]+$/')]
    public string $shortDateFormat;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Regex(pattern: '/^[^<>]+$/')]
    public string $fullDateFormat;

    public bool $rtl;

    #[Assert\NotNull(groups: ['SetStatus'])]
    public bool $enabled;

    /**
     * @var int[]
     */
    public array $shopIds;

    /**
     * Write-only: the flag of the language, sent as the `flagImage` part of a multipart create or update request.
     * Required on creation, like in the BO form; an update without it keeps the current flag. It is stored as
     * img/l/{languageId}.jpg and is not exposed by the responses.
     */
    #[ApiProperty(readable: false, writable: false)]
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Image]
    public ?File $flagImage = null;

    /**
     * Write-only: the image displayed when a product, category or brand has no picture, sent as the
     * `noPictureImage` part of a multipart create or update request. Required on creation, like in the BO form; an
     * update without it keeps the current image. It is stored as img/{p,c,m}/{isoCode}.jpg with its thumbnails and is
     * not exposed by the responses.
     */
    #[ApiProperty(readable: false, writable: false)]
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Image]
    public ?File $noPictureImage = null;

    /**
     * Values used by the create operation when the payload doesn't provide them, the ones of the BO form. The API
     * resource properties cannot carry those defaults: the payload is denormalized into the CQRS command.
     */
    public const CREATE_DEFAULT_VALUES = [
        'rtl' => false,
        'enabled' => true,
        // The shops of the request context, so the single shop of the installation when multistore is disabled
        'shopIds' => '[_context][shopIds]',
    ];

    public const QUERY_MAPPING = [
        '[shopAssociation]' => '[shopIds]',
        '[active]' => '[enabled]',
    ];

    /**
     * The images are uploaded as files, and the commands expect their paths, which the File exposes as pathName.
     */
    public const CREATE_COMMAND_MAPPING = [
        '[rtl]' => '[isRtl]',
        '[enabled]' => '[isActive]',
        '[shopIds]' => '[shopAssociation]',
        '[flagImage].pathName' => '[flagImagePath]',
        '[noPictureImage].pathName' => '[noPictureImagePath]',
    ];

    public const UPDATE_COMMAND_MAPPING = [
        '[rtl]' => '[isRtl]',
        '[enabled]' => '[isActive]',
        '[shopIds]' => '[shopAssociation]',
        '[flagImage].pathName' => '[flagImagePath]',
        '[noPictureImage].pathName' => '[noPictureImagePath]',
    ];

    public const STATUS_COMMAND_MAPPING = [
        '[enabled]' => '[expectedStatus]',
    ];
}
