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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\ExtraPropertyDefinition;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Command\AddExtraPropertyDefinitionCommand;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Command\DeleteExtraPropertyDefinitionCommand;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Command\UpdateExtraPropertyDefinitionCommand;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Exception\ExtraPropertyConstraintException;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Exception\ExtraPropertyDefinitionNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Exception\ExtraPropertyRegistrationFailureException;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Exception\ProtectedModuleExtraPropertyDefinitionException;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Query\GetExtraPropertyDefinitionForEditing;
use PrestaShop\PrestaShop\Core\ExtraProperty\Exception\InvalidExtraPropertyDefinitionException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Registry of the "extra property" definitions: the typed fields a module (or the merchant, through
 * this API) adds to native entities. Definitions created here are core-owned (no moduleName);
 * module-owned definitions are readable but only their shop association can be modified.
 *
 * The extra property VALUES themselves are exposed on each entity's own endpoints (the
 * "extraProperties" sub-object on items, inline "extra_<module>_<property>" keys on lists), not here.
 */
#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/extra-property-definitions/{extraPropertyDefinitionId}',
            requirements: ['extraPropertyDefinitionId' => '\d+'],
            CQRSQuery: GetExtraPropertyDefinitionForEditing::class,
            scopes: ['extra_property_definition_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            extraProperties: self::VERSION_GATE,
        ),
        new CQRSCreate(
            uriTemplate: '/extra-property-definitions',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddExtraPropertyDefinitionCommand::class,
            CQRSQuery: GetExtraPropertyDefinitionForEditing::class,
            scopes: ['extra_property_definition_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            // The defaults are declared as an extra property, not with the dedicated operation argument,
            // so that older cores still parse this class (they ignore it and the fields stay required
            // there). Create only: a partial update must never apply them.
            extraProperties: self::VERSION_GATE + ['defaultValues' => self::CREATE_DEFAULT_VALUES],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/extra-property-definitions/{extraPropertyDefinitionId}',
            requirements: ['extraPropertyDefinitionId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: UpdateExtraPropertyDefinitionCommand::class,
            CQRSQuery: GetExtraPropertyDefinitionForEditing::class,
            scopes: ['extra_property_definition_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            extraProperties: self::VERSION_GATE,
        ),
        new CQRSDelete(
            uriTemplate: '/extra-property-definitions/{extraPropertyDefinitionId}',
            requirements: ['extraPropertyDefinitionId' => '\d+'],
            CQRSCommand: DeleteExtraPropertyDefinitionCommand::class,
            scopes: ['extra_property_definition_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
            extraProperties: self::VERSION_GATE,
            // The body is optional: without it the storage column (and its data) is kept, like the
            // back office "Delete" action; {"dropColumn": true} also drops the column.
            openapiContext: [
                'requestBody' => [
                    'required' => false,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'dropColumn' => [
                                        'type' => 'boolean',
                                        'default' => false,
                                        'description' => 'Also drop the storage column and every stored value of this property.',
                                    ],
                                ],
                            ],
                            'example' => ['dropColumn' => false],
                        ],
                    ],
                ],
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        ExtraPropertyDefinitionNotFoundException::class => Response::HTTP_NOT_FOUND,
        // Input format (invalid constraints DSL, invalid id).
        ExtraPropertyConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // Registry refusals: unknown entity, scope conflict, destructive change, invalid default
        // value, constraints that cannot be stored, unknown shop, form options that build no field.
        ExtraPropertyRegistrationFailureException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // Module-owned definitions only accept a shop association change.
        ProtectedModuleExtraPropertyDefinitionException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // Identifier rules of the definition value object (entity/property names, sizes).
        InvalidExtraPropertyDefinitionException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ExtraPropertyDefinition
{
    /**
     * The definition CQRS layer only exists since PrestaShop 9.2.0: the operations are filtered out
     * of the API on older cores (declared as an extra property so those cores can still parse this
     * class — the named argument only exists on 9.2+).
     */
    public const VERSION_GATE = ['minVersion' => '9.2.0'];

    public const TYPES = ['int', 'bool', 'string', 'float', 'date', 'html', 'json', 'choice'];
    public const SCOPES = ['common', 'lang', 'shop'];
    public const SQL_INDEXES = ['none', 'key', 'unique'];

    /**
     * Mirrors the constructor defaults of AddExtraPropertyDefinitionCommand, so a payload that omits
     * these fields behaves exactly like a back office form left untouched. They are documented as
     * `default` in the schema and no longer listed as required.
     */
    public const CREATE_DEFAULT_VALUES = [
        'type' => 'string',
        'scope' => 'common',
        'sqlIndex' => 'none',
        'displayFront' => false,
        'required' => false,
        'nullable' => true,
    ];

    #[ApiProperty(identifier: true)]
    public int $extraPropertyDefinitionId;

    /**
     * Logical entity name (product, customer, combination, order...).
     */
    #[Assert\NotBlank(groups: ['Create'])]
    public string $entityName;

    /**
     * Owning module technical name; null for core-owned definitions (every definition created
     * through this API). Read-only: module-owned definitions cannot be created or edited here,
     * except for their shop association.
     */
    public ?string $moduleName;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $propertyName;

    #[ApiProperty(openapiContext: ['type' => 'string', 'enum' => self::TYPES, 'default' => 'string'])]
    #[Assert\Choice(choices: self::TYPES)]
    public string $type;

    /**
     * common = one value for every shop and language, lang = one value per language, shop = one
     * value per shop.
     */
    #[ApiProperty(openapiContext: ['type' => 'string', 'enum' => self::SCOPES, 'default' => 'common'])]
    #[Assert\Choice(choices: self::SCOPES)]
    public string $scope;

    #[ApiProperty(openapiContext: ['type' => 'string', 'enum' => self::SQL_INDEXES, 'default' => 'none'])]
    #[Assert\Choice(choices: self::SQL_INDEXES)]
    public string $sqlIndex;

    /**
     * Whether front-office presenters expose the value.
     */
    public bool $displayFront;

    /**
     * Marks the field as required in the back office form and in the OpenAPI schema of the entity
     * endpoints; it adds no server-side validation (declare NotBlank in the constraints for that).
     */
    public bool $required;

    /**
     * Whether the storage column accepts NULL.
     */
    public bool $nullable;

    /**
     * VARCHAR size for string properties (null = 255).
     */
    public ?int $size;

    /**
     * Default value served for entities without a stored value, typed like the property (a float
     * property returns a JSON number, a bool property a JSON boolean). A float default travels as
     * a DecimalNumber; the scalar members keep their own type (the core CQRS normalizer tries the
     * scalar members of a union before the class ones).
     */
    #[ApiProperty(openapiContext: ['oneOf' => [['type' => 'integer'], ['type' => 'number'], ['type' => 'string'], ['type' => 'boolean']], 'nullable' => true])]
    public int|string|bool|DecimalNumber|null $defaultValue;

    /**
     * Allowed values of a choice property.
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'string'], 'nullable' => true])]
    public ?array $enumValues;

    public ?string $labelWording;

    public ?string $labelDomain;

    public ?string $descriptionWording;

    public ?string $descriptionDomain;

    /**
     * Validation constraints applied to each written value, in the extra property constraint DSL:
     * one Symfony constraint per line (or comma-separated), e.g. "NotBlank", "Length(min: 2, max: 64)",
     * "Choice(['a', 'b'])", "All[ Url ]". Read back in its canonical form (one per line). Null or
     * empty = no validation.
     */
    #[ApiProperty(openapiContext: [
        'type' => 'string',
        'nullable' => true,
        'description' => 'One constraint per line: Name, Name(value), Name(option: value, ...) or Composite[ ... ] — e.g. NotBlank, Length(min: 2, max: 64), Choice([\'a\', \'b\']), All[ Url ].',
        'example' => "NotBlank\nLength(min: 2, max: 64)",
    ])]
    public ?string $constraints;

    /**
     * Symfony form type FQCN used by the back office entity form (null = text input).
     */
    public ?string $formType;

    /**
     * Options passed to the back office form type.
     */
    #[ApiProperty(openapiContext: ['type' => 'object', 'additionalProperties' => true, 'nullable' => true])]
    public ?array $formOptions;

    /**
     * Back office form placements: "formId[:path[:before|after]]".
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'string'], 'nullable' => true, 'example' => ['product', 'category:seo:after']])]
    public ?array $associatedForms;

    /**
     * Back office grid placements: "gridId[:columnId[:before|after]]".
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'string'], 'nullable' => true, 'example' => ['product:reference:after']])]
    public ?array $associatedGrids;

    /**
     * Admin API endpoints exposing the value: URI templates with an optional ":METHOD" filter.
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'string'], 'nullable' => true, 'example' => ['/products', '/products/{productId}:GET,PATCH']])]
    public ?array $associatedApis;

    /**
     * Shops the definition is restricted to. Null = no explicit restriction (core-owned: every
     * shop, module-owned: the module's shops); an empty list on update reverts to that fallback.
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'nullable' => true])]
    public ?array $shopIds;

    public const QUERY_MAPPING = [
        // URI parameter → GetExtraPropertyDefinitionForEditing(int $id)
        '[extraPropertyDefinitionId]' => '[id]',
        // Query result → API resource
        '[id]' => '[extraPropertyDefinitionId]',
        '[associatedShopIds]' => '[shopIds]',
    ];

    public const COMMAND_MAPPING = [
        // URI parameter → Update/DeleteExtraPropertyDefinitionCommand(int $id)
        '[extraPropertyDefinitionId]' => '[id]',
        '[shopIds]' => '[associatedShopIds]',
    ];
}
