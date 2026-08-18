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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Carrier;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\CleanHtml;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\AddCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\EditCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\SetCarrierTaxRuleGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CannotAddCarrierException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CannotUpdateCarrierException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Query\GetCarrierForEditing;
use PrestaShop\PrestaShop\Core\Domain\Carrier\ValueObject\OutOfRangeBehavior;
use PrestaShop\PrestaShop\Core\Domain\Carrier\ValueObject\ShippingMethod;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\Exception\TaxRulesGroupNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
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
            uriTemplate: '/carriers/{carrierId}',
            requirements: ['carrierId' => '\d+'],
            CQRSQuery: GetCarrierForEditing::class,
            scopes: ['carrier_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/carriers',
            // Both formats are accepted: JSON when the payload has no logo, multipart when it uploads one. Form data
            // values are all strings, hence the disabled type enforcement.
            inputFormats: self::INPUT_FORMATS,
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCarrierCommand::class,
            CQRSQuery: GetCarrierForEditing::class,
            scopes: ['carrier_write'],
            // The default values are the ones the BO form applies when the fields are left untouched. They are
            // declared as an extra property, and not with the dedicated operation argument, so that the resource is
            // still parsed by the PrestaShop versions that don't know this argument yet (they simply ignore it, and
            // the fields remain required there).
            extraProperties: [
                'defaultValues' => self::CREATE_DEFAULT_VALUES,
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
            openapi: new OpenApiOperation(
                summary: 'Create a carrier.',
                description: 'Creates a carrier and returns it. The payload can be sent as JSON, or as a multipart '
                    . 'request when a logo is uploaded along with the other fields.',
            ),
        ),
        // The update is a POST and not a PATCH because a file can only be uploaded through a POST request: PHP fills
        // the uploaded files of the request for that method only. It still updates the provided fields only.
        new CQRSCreate(
            uriTemplate: '/carriers/{carrierId}',
            requirements: ['carrierId' => '\d+'],
            inputFormats: self::INPUT_FORMATS,
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            status: Response::HTTP_OK,
            read: false,
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditCarrierCommand::class,
            CQRSQuery: GetCarrierForEditing::class,
            scopes: ['carrier_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
            // The generated summary of a POST operation is a creation one, which is wrong here, so both POST
            // operations describe explicitly what they do to avoid two identical "create a carrier" summaries
            openapi: new OpenApiOperation(
                summary: 'Update a carrier.',
                description: 'Updates an existing carrier and returns it. Only the fields present in the payload are '
                    . 'modified, the other ones are left unchanged. This operation relies on POST and not on PATCH '
                    . 'because a logo can only be uploaded through a POST request, but it never creates a carrier.',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/carriers/{carrierId}/set-tax-rule-group',
            requirements: ['carrierId' => '\d+'],
            validationContext: ['groups' => ['Default', 'SetTaxRuleGroup']],
            CQRSCommand: SetCarrierTaxRuleGroupCommand::class,
            CQRSQuery: GetCarrierForEditing::class,
            scopes: ['carrier_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::SET_TAX_RULE_GROUP_COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        CarrierNotFoundException::class => Response::HTTP_NOT_FOUND,
        CarrierConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotAddCarrierException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateCarrierException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        TaxRulesGroupNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Carrier
{
    #[ApiProperty(identifier: true)]
    public int $carrierId;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Length(min: 1, max: 64)]
    #[CleanHtml]
    public string $name;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'delays')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'delays', allowNull: true)]
    #[Assert\All(constraints: [new CleanHtml()])]
    public array $delays;

    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Range(min: 0, max: 9)]
    public int $grade;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Url]
    public string $trackingUrl;

    public int $position;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $enabled;

    #[Assert\PositiveOrZero]
    public int $maxWidth = 0;

    #[Assert\PositiveOrZero]
    public int $maxHeight = 0;

    #[Assert\PositiveOrZero]
    public int $maxDepth = 0;

    public DecimalNumber $maxWeight;

    /**
     * A carrier without group cannot be selected by any customer, so the BO form rejects an empty list and this
     * endpoint does the same: the list is required on creation, and it cannot be emptied by an update.
     */
    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Count(min: 1)]
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $associatedGroupIds;

    public bool $additionalHandlingFee;

    public bool $free;

    /**
     * The accepted values are the ones of the value object, so the shipping methods available on the running core
     * version: the 0 value, which falls back to the shipping method of the shop configuration, only exists since
     * PrestaShop 9.2.0 (PrestaShop/PrestaShop#42022).
     */
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Choice(choices: ShippingMethod::AVAILABLE_VALUES)]
    public int $shippingMethod;

    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Choice(choices: OutOfRangeBehavior::AVAILABLE_VALUES)]
    public int $rangeBehavior;

    /**
     * Not writable by the create and update operations: it is set exclusively through
     * PATCH /carriers/{carrierId}/set-tax-rule-group, which returns the updated carrier.
     */
    #[Assert\NotNull(groups: ['SetTaxRuleGroup'])]
    public int $taxRuleGroupId;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Count(min: 1)]
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $zones;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Count(min: 1)]
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $associatedShopIds;

    public int $ordersCount;

    /**
     * Write-only: JPEG image sent with a multipart create or update request. The resulting logo is exposed by the
     * carriers list, as the logoUrl of the carrier. The accepted format and size are the ones of the BO form.
     */
    #[Assert\File(maxSize: '8M', mimeTypes: ['image/jpeg'], mimeTypesMessage: 'Please upload a valid jpeg file')]
    public ?File $logo = null;

    /**
     * Values used by the create operation when the payload doesn't provide them, so the same fields can be omitted
     * here and left untouched in the BO form. The API resource properties cannot carry those defaults: the payload is
     * denormalized into the CQRS command, not into this class.
     */
    public const CREATE_DEFAULT_VALUES = [
        'additionalHandlingFee' => false,
        'free' => false,
        'shippingMethod' => ShippingMethod::BY_PRICE,
        'rangeBehavior' => OutOfRangeBehavior::USE_HIGHEST_RANGE,
    ];

    public const QUERY_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[active]' => '[enabled]',
        '[localizedDelay]' => '[delays]',
        '[hasAdditionalHandlingFee]' => '[additionalHandlingFee]',
        '[isFree]' => '[free]',
        '[idTaxRuleGroup]' => '[taxRuleGroupId]',
    ];

    /**
     * The logo is uploaded as a file, and the commands expect its path, which the File exposes as pathName.
     */
    public const CREATE_COMMAND_MAPPING = [
        '[delays]' => '[localizedDelay]',
        '[enabled]' => '[active]',
        '[additionalHandlingFee]' => '[hasAdditionalHandlingFee]',
        '[free]' => '[isFree]',
        '[maxWidth]' => '[max_width]',
        '[maxHeight]' => '[max_height]',
        '[maxDepth]' => '[max_depth]',
        '[maxWeight]' => '[max_weight]',
        '[logo].pathName' => '[logoPathName]',
    ];

    public const UPDATE_COMMAND_MAPPING = [
        '[delays]' => '[localizedDelay]',
        '[enabled]' => '[active]',
        '[free]' => '[isFree]',
        '[logo].pathName' => '[logoPathName]',
    ];

    /**
     * SetCarrierTaxRuleGroupCommand rejects every shop constraint but the all shops one, while the
     * API context builds a single shop constraint whenever multistore is disabled. Only the
     * strictness of that constraint is mapped: with no shopId, shopGroupId nor shopIds in the mapped
     * data, the core ShopConstraintNormalizer falls back to ShopConstraint::allShops(), the only
     * constraint the command accepts. The association is then written for every shop of the carrier,
     * which is what the Core does with that constraint.
     */
    public const SET_TAX_RULE_GROUP_COMMAND_MAPPING = [
        '[_context][shopConstraint][isStrict]' => '[shopConstraint][isStrict]',
        '[taxRuleGroupId]' => '[carrierTaxRuleGroupId]',
    ];

    /**
     * The create and update operations accept a JSON payload, and a multipart one when a logo is uploaded with it.
     */
    public const INPUT_FORMATS = [
        'json' => ['application/json'],
        'multipart' => ['multipart/form-data'],
    ];
}
