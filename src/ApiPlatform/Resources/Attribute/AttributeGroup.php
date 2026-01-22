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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Attribute;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Command\AddAttributeGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Command\DeleteAttributeGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Command\EditAttributeGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Exception\AttributeGroupConstraintException;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Exception\AttributeGroupNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Query\GetAttributeGroupForEditing;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\ValueObject\AttributeGroupType;
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
            uriTemplate: '/attributes/groups/{attributeGroupId}',
            CQRSQuery: GetAttributeGroupForEditing::class,
            scopes: [
                'attribute_group_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/attributes/groups',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddAttributeGroupCommand::class,
            CQRSQuery: GetAttributeGroupForEditing::class,
            scopes: [
                'attribute_group_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/attributes/groups/{attributeGroupId}',
            requirements: ['attributeGroupId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditAttributeGroupCommand::class,
            CQRSQuery: GetAttributeGroupForEditing::class,
            scopes: [
                'attribute_group_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/attributes/groups/{attributeGroupId}',
            requirements: ['attributeGroupId' => '\d+'],
            CQRSCommand: DeleteAttributeGroupCommand::class,
            scopes: [
                'attribute_group_write',
            ],
        ),
    ],
    exceptionToStatus: [
        AttributeGroupConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        AttributeGroupNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class AttributeGroup
{
    #[ApiProperty(identifier: true)]
    public int $attributeGroupId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'names', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_CATALOG_NAME,
        ]),
    ])]
    public array $names;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'publicNames')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'publicNames', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_CATALOG_NAME,
        ]),
    ])]
    public array $publicNames;

    #[Assert\Choice(choices: [AttributeGroupType::ATTRIBUTE_GROUP_TYPE_COLOR, AttributeGroupType::ATTRIBUTE_GROUP_TYPE_SELECT, AttributeGroupType::ATTRIBUTE_GROUP_TYPE_RADIO])]
    public string $type;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank(allowNull: true)]
    public array $shopIds;

    public int $position;

    public const QUERY_MAPPING = [
        '[name]' => '[names]',
        '[publicName]' => '[publicNames]',
        '[associatedShopIds]' => '[shopIds]',
    ];

    public const COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[publicNames]' => '[localizedPublicNames]',
        '[shopIds]' => '[associatedShopIds]',
    ];
}
