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
use PrestaShop\PrestaShop\Core\Domain\Feature\Command\AddFeatureValueCommand;
use PrestaShop\PrestaShop\Core\Domain\Feature\Command\DeleteFeatureValueCommand;
use PrestaShop\PrestaShop\Core\Domain\Feature\Command\EditFeatureValueCommand;
use PrestaShop\PrestaShop\Core\Domain\Feature\Exception\FeatureValueConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Feature\Exception\FeatureValueNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Feature\Query\GetFeatureValueForEditing;
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
            uriTemplate: '/features/values/{featureValueId}',
            CQRSQuery: GetFeatureValueForEditing::class,
            scopes: [
                'feature_value_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/features/values',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddFeatureValueCommand::class,
            CQRSQuery: GetFeatureValueForEditing::class,
            scopes: [
                'feature_value_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/features/values/{featureValueId}',
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditFeatureValueCommand::class,
            CQRSQuery: GetFeatureValueForEditing::class,
            scopes: [
                'feature_value_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/features/values/{featureValueId}',
            CQRSCommand: DeleteFeatureValueCommand::class,
            scopes: [
                'feature_value_write',
            ],
        ),
    ],
    exceptionToStatus: [
        FeatureValueConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        FeatureValueNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class FeatureValue
{
    #[ApiProperty(identifier: true)]
    public int $featureValueId;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $featureId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'values')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'values', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_CATALOG_NAME,
        ]),
    ])]
    public array $values;

    public int $position;

    public const QUERY_MAPPING = [
        '[value]' => '[values]',
        '[localizedValues]' => '[values]',
    ];

    public const COMMAND_MAPPING = [
        '[values]' => '[localizedValues]',
    ];
}
