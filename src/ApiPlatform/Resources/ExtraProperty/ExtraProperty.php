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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\ExtraProperty;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Command\AddExtraPropertyDefinitionCommand;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Command\DeleteExtraPropertyDefinitionCommand;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Command\UpdateExtraPropertyDefinitionCommand;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Exception\ExtraPropertyConstraintException;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Exception\ExtraPropertyDefinitionNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Query\GetExtraPropertyDefinitionForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/extra-properties/{extraPropertyId}',
            requirements: ['extraPropertyId' => '\d+'],
            CQRSQuery: GetExtraPropertyDefinitionForEditing::class,
            scopes: [
                'extra_property_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/extra-properties',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddExtraPropertyDefinitionCommand::class,
            CQRSQuery: GetExtraPropertyDefinitionForEditing::class,
            scopes: [
                'extra_property_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/extra-properties/{extraPropertyId}',
            requirements: ['extraPropertyId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: UpdateExtraPropertyDefinitionCommand::class,
            CQRSQuery: GetExtraPropertyDefinitionForEditing::class,
            scopes: [
                'extra_property_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/extra-properties/{extraPropertyId}',
            requirements: ['extraPropertyId' => '\d+'],
            CQRSCommand: DeleteExtraPropertyDefinitionCommand::class,
            scopes: [
                'extra_property_write',
            ],
            CQRSCommandMapping: [
                '[extraPropertyId]' => '[id]',
            ],
        ),
    ],
    exceptionToStatus: [
        ExtraPropertyConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ExtraPropertyDefinitionNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ExtraProperty
{
    #[ApiProperty(identifier: true)]
    public int $extraPropertyId;

    /**
     * Entity table name (e.g. 'product', 'customer'). Immutable after creation.
     */
    #[Assert\NotBlank(groups: ['Create'])]
    public string $entityName;

    /**
     * Property identifier (e.g. 'internal_code'). Immutable after creation.
     */
    #[Assert\NotBlank(groups: ['Create'])]
    public string $propertyName;

    /**
     * Null for core-owned definitions; non-null when a module owns the definition (read-only).
     */
    public ?string $moduleName = null;

    /**
     * One of the ExtraPropertyType enum values (e.g. 'string', 'int', 'bool', 'choice').
     */
    public string $fieldType;

    /**
     * One of the ExtraPropertyScope enum values (e.g. 'common').
     */
    public string $fieldScope;

    /**
     * One of the ExtraPropertySqlIndex enum values (e.g. 'none', 'index', 'unique').
     */
    public string $sqlIndex;

    public bool $nullable;

    public ?int $size = null;

    public ?string $defaultValue = null;

    /**
     * @var list<string>|null
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'string']])]
    public ?array $enumValues = null;

    public bool $displayFront;

    public bool $required;

    public ?string $labelWording = null;

    public ?string $labelDomain = null;

    public ?string $descriptionWording = null;

    public ?string $descriptionDomain = null;

    public ?string $formType = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ApiProperty(openapiContext: ['type' => 'object'])]
    public ?array $formOptions = null;

    /**
     * @var list<string>|null Form placement entries (e.g. "product:reference:after")
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'string']])]
    public ?array $associatedForms = null;

    /**
     * @var list<string>|null Grid placement entries (e.g. "product:reference:after")
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'string']])]
    public ?array $associatedGrids = null;

    /**
     * @var list<string>|null Admin API placement entries (e.g. "/products:GET")
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'string']])]
    public ?array $associatedApis = null;

    public const QUERY_MAPPING = [
        // Read (query result → DTO): EditableExtraPropertyDefinition::getId() → DTO::$extraPropertyId
        '[id]' => '[extraPropertyId]',
        // Write from URI param (GET/PATCH/DELETE): {extraPropertyId} → GetExtraPropertyDefinitionForEditing::$id
        '[extraPropertyId]' => '[id]',
        // Write after Create: AddHandler returns an ExtraPropertyDefinitionId VO that normalizes as
        // {extraPropertyDefinitionId: N} → bridge to the query constructor arg $id.
        '[extraPropertyDefinitionId]' => '[id]',
    ];

    public const CREATE_COMMAND_MAPPING = [];

    public const UPDATE_COMMAND_MAPPING = [
        '[extraPropertyId]' => '[id]',
    ];
}
