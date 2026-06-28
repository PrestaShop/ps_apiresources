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
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\AddCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\DeleteCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\EditCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Query\GetCarrierForEditing;
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
            uriTemplate: '/carriers/{carrierId}',
            requirements: ['carrierId' => '\d+'],
            CQRSQuery: GetCarrierForEditing::class,
            scopes: ['carrier_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/carriers',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCarrierCommand::class,
            CQRSQuery: GetCarrierForEditing::class,
            scopes: ['carrier_write'],
            inputFormats: ['multipart' => ['multipart/form-data']],
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/carriers/{carrierId}',
            requirements: ['carrierId' => '\d+'],
            read: false,
            CQRSCommand: EditCarrierCommand::class,
            CQRSQuery: GetCarrierForEditing::class,
            scopes: ['carrier_write'],
            inputFormats: ['multipart' => ['multipart/form-data']],
            denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/carriers/{carrierId}',
            requirements: ['carrierId' => '\d+'],
            CQRSCommand: DeleteCarrierCommand::class,
            scopes: ['carrier_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        CarrierNotFoundException::class => Response::HTTP_NOT_FOUND,
        CarrierConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Carrier
{
    #[ApiProperty(identifier: true)]
    public int $carrierId;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $name;

    #[LocalizedValue]
    #[Assert\NotBlank(groups: ['Create'])]
    public array $delay;

    public int $grade;

    public string $trackingUrl;

    public bool $active;

    public array $associatedGroupIds;

    public bool $hasAdditionalHandlingFee;

    public bool $isFree;

    public int $shippingMethod;

    public int $rangeBehavior;

    #[Assert\Count(min: 1, groups: ['Create'])]
    public array $zones;

    public array $associatedShopIds;

    public int $maxWidth;

    public int $maxHeight;

    public int $maxDepth;

    public float $maxWeight;

    public int $taxRuleGroupId;

    public ?File $logoFile;

    public ?string $logoPath;

    public int $position;

    public const QUERY_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[localizedDelay]' => '[delay]',
        '[idTaxRuleGroup]' => '[taxRuleGroupId]',
    ];

    // AddCarrierCommand takes most fields as constructor arguments matching these property names;
    // only the renamed/uploaded ones need an explicit mapping.
    public const CREATE_COMMAND_MAPPING = [
        '[delay]' => '[localizedDelay]',
        '[maxWidth]' => '[max_width]',
        '[maxHeight]' => '[max_height]',
        '[maxDepth]' => '[max_depth]',
        '[maxWeight]' => '[max_weight]',
        '[logoFile].pathName' => '[logoPathName]',
    ];

    // EditCarrierCommand exposes the fields through setters (setMaxWidth, setLocalizedDelay,
    // setLogoPathName, setTaxRuleGroupId, setAdditionalHandlingFee...).
    public const UPDATE_COMMAND_MAPPING = [
        '[delay]' => '[localizedDelay]',
        '[maxWidth]' => '[max_width]',
        '[maxHeight]' => '[max_height]',
        '[maxDepth]' => '[max_depth]',
        '[maxWeight]' => '[max_weight]',
        '[hasAdditionalHandlingFee]' => '[additionalHandlingFee]',
        '[logoFile].pathName' => '[logoPathName]',
    ];
}
