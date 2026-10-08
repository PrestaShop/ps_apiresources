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
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\InvalidProductTypeException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Query\GetProductForEditing;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Command\AddVirtualProductFileCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Command\DeleteVirtualProductFileCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Command\UpdateVirtualProductFileCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Exception\CannotAddVirtualProductFileException;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Exception\CannotDeleteVirtualProductFileException;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Exception\CannotUpdateVirtualProductFileException;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Exception\VirtualProductFileConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Exception\VirtualProductFileNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\Query\GetVirtualProductFileForEditing;
use PrestaShop\PrestaShop\Core\Domain\Product\VirtualProductFile\VirtualProductFileSettings;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/products/{productId}/virtual-files',
            requirements: ['productId' => '\d+'],
            // The file is uploaded with the request, so the payload can only be sent as multipart form data.
            // Form data values are all strings, hence the disabled type enforcement.
            inputFormats: ['multipart' => ['multipart/form-data']],
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            read: false,
            // The class identifier is virtualProductFileId, so the productId URI
            // variable must be explicitly bound
            uriVariables: [
                'productId' => new Link(
                    identifiers: ['productId'],
                ),
            ],
            CQRSCommand: AddVirtualProductFileCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            // GetVirtualProductFileForEditing would be cheaper here, the command returns the created
            // VirtualProductFileId and the whole product would not be fetched, but that query only exists since
            // PrestaShop 9.3. The product query keeps this operation available on the older supported cores, where
            // it is the only way to attach a file since the update is filtered out. Once the module requires 9.3
            // the query can be switched, the response contract is identical either way.
            CQRSQuery: GetProductForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            validationContext: ['groups' => ['Default', 'Create']],
            scopes: ['product_write'],
            // Two POST operations share this resource, so both describe explicitly what they do
            // instead of relying on the generated "Creates a …" summary
            openapi: new OpenApiOperation(
                summary: 'Add a virtual product file.',
                description: 'Attaches the downloadable file of a virtual product and returns it. The file is '
                    . 'uploaded in the multipart `file` part along with the other fields, so the request can only '
                    . 'be sent as multipart form data.',
            ),
        ),
        new CQRSGet(
            uriTemplate: '/products/virtual-files/{virtualProductFileId}',
            requirements: ['virtualProductFileId' => '\d+'],
            // Reading a file on its own requires GetVirtualProductFileForEditing, which only exists since
            // PrestaShop 9.3: on older cores this operation is filtered out of the API, and the file of a product
            // is read from the virtualProductFile property of the product itself.
            extraProperties: [
                'minVersion' => '9.3.0',
            ],
            CQRSQuery: GetVirtualProductFileForEditing::class,
            CQRSQueryMapping: self::FILE_QUERY_MAPPING,
            scopes: ['product_read'],
        ),
        // The update is a POST and not a PATCH because a file can only be uploaded through a POST request: PHP fills
        // the uploaded files of the request for that method only. It still updates the provided fields only.
        new CQRSUpdate(
            method: CQRSUpdate::METHOD_POST,
            uriTemplate: '/products/virtual-files/{virtualProductFileId}',
            requirements: ['virtualProductFileId' => '\d+'],
            inputFormats: self::INPUT_FORMATS,
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            status: Response::HTTP_OK,
            read: false,
            // The update command returns nothing, so the response is built by a query fed with the file id. Reading
            // the file on its own requires GetVirtualProductFileForEditing, which only exists since PrestaShop 9.3:
            // on older cores this operation is filtered out of the API, and a file is replaced by deleting and
            // adding it again.
            extraProperties: [
                'minVersion' => '9.3.0',
            ],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: UpdateVirtualProductFileCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetVirtualProductFileForEditing::class,
            CQRSQueryMapping: self::FILE_QUERY_MAPPING,
            scopes: ['product_write'],
            openapi: new OpenApiOperation(
                summary: 'Update a virtual product file.',
                description: 'Updates an existing virtual product file and returns it. Only the fields present in '
                    . 'the payload are modified, the other ones are left unchanged. The payload is sent as JSON to '
                    . 'keep the current file, or as multipart form data with a `file` part to replace it. This '
                    . 'operation relies on POST and not on PATCH because a file can only be uploaded through a POST '
                    . 'request, but it never creates a file. It requires PrestaShop 9.3: on older cores it is '
                    . 'filtered out of the API, and a file is replaced by deleting it and adding a new one.',
            ),
        ),
        new CQRSDelete(
            uriTemplate: '/products/virtual-files/{virtualProductFileId}',
            requirements: ['virtualProductFileId' => '\d+'],
            CQRSCommand: DeleteVirtualProductFileCommand::class,
            scopes: ['product_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        VirtualProductFileNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotAddVirtualProductFileException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotDeleteVirtualProductFileException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateVirtualProductFileException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        InvalidProductTypeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        VirtualProductFileConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class VirtualProductFile
{
    public int $productId;

    #[ApiProperty(identifier: true)]
    public int $virtualProductFileId;

    /**
     * Write-only: the downloadable file, sent as the `file` part of a multipart request. It is kept out
     * of the documented schemas, which describe the stored file through fileName, and only appears as the
     * binary part of the multipart request body. It is
     * required to add a file; an update sent as JSON keeps the current file, an update sent as
     * multipart form data replaces it. The file is stored in the protected download directory
     * under a generated name, exposed by the responses as fileName. The maximum size is the PHP
     * upload limit, like in the BO form.
     */
    #[ApiProperty(readable: false, writable: false)]
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\File]
    public File $file;

    /**
     * Name of the stored file in the download directory (read-only).
     */
    public ?string $fileName = null;

    #[Assert\NotBlank(groups: ['Create'])]
    #[TypedRegex(['type' => TypedRegex::TYPE_GENERIC_NAME])]
    #[Assert\Length(max: VirtualProductFileSettings::MAX_DISPLAY_FILENAME_LENGTH)]
    public string $displayName;

    #[Assert\LessThanOrEqual(VirtualProductFileSettings::MAX_ACCESSIBLE_DAYS_LIMIT)]
    public ?int $accessDays = null;

    #[Assert\LessThanOrEqual(VirtualProductFileSettings::MAX_DOWNLOAD_TIMES_LIMIT)]
    public ?int $downloadTimesLimit = null;

    public ?\DateTimeImmutable $expirationDate = null;

    /**
     * Shared by the create and update operations so both return the same
     * full-state representation based on GetProductForEditing.
     */
    public const QUERY_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[_context][langId]' => '[displayLanguageId]',
        '[virtualProductFile][id]' => '[virtualProductFileId]',
        '[virtualProductFile][fileName]' => '[fileName]',
        '[virtualProductFile][displayName]' => '[displayName]',
        '[virtualProductFile][accessDays]' => '[accessDays]',
        '[virtualProductFile][downloadTimesLimit]' => '[downloadTimesLimit]',
        '[virtualProductFile][expirationDate]' => '[expirationDate]',
    ];

    /**
     * The get and update operations read the file on its own, so the query result is already the file: only its id
     * has to be renamed, every other field matches the resource property names.
     */
    public const FILE_QUERY_MAPPING = [
        '[id]' => '[virtualProductFileId]',
    ];

    /**
     * The file is uploaded as a file, and the commands expect its path, which the File exposes as pathName.
     */
    public const COMMAND_MAPPING = [
        '[file].pathName' => '[filePath]',
    ];

    /**
     * The update operation accepts a JSON payload, and a multipart one when the file is replaced with it.
     */
    public const INPUT_FORMATS = [
        'json' => ['application/json'],
        'multipart' => ['multipart/form-data'],
    ];
}
