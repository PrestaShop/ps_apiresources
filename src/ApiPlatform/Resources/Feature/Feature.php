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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Feature;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\Feature\Command\AddFeatureCommand;
use PrestaShop\PrestaShop\Core\Domain\Feature\Command\DeleteFeatureCommand;
use PrestaShop\PrestaShop\Core\Domain\Feature\Command\EditFeatureCommand;
use PrestaShop\PrestaShop\Core\Domain\Feature\Exception\FeatureConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Feature\Exception\FeatureNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Feature\Query\GetFeatureForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/features/{featureId}',
            CQRSQuery: GetFeatureForEditing::class,
            scopes: [
                'feature_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/features',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddFeatureCommand::class,
            CQRSQuery: GetFeatureForEditing::class,
            scopes: [
                'feature_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/features/{featureId}',
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditFeatureCommand::class,
            CQRSQuery: GetFeatureForEditing::class,
            scopes: [
                'feature_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/features/{featureId}',
            requirements: ['featureId' => '\d+'],
            CQRSCommand: DeleteFeatureCommand::class,
            scopes: [
                'feature_write',
            ],
        ),
    ],
    exceptionToStatus: [
        FeatureConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        FeatureNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Feature
{
    #[ApiProperty(identifier: true)]
    public int $featureId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'names', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_CATALOG_NAME,
        ]),
    ])]
    public array $names;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank(allowNull: true)]
    public array $shopIds;

    public int $position;

    public const QUERY_MAPPING = [
        '[name]' => '[names]',
        '[shopAssociation]' => '[shopIds]',
    ];

    public const COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[shopIds]' => '[shopAssociation]',
    ];
}
