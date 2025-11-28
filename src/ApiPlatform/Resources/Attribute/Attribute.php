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
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Attribute\Command\AddAttributeCommand;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Attribute\Command\DeleteAttributeCommand;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Attribute\Command\EditAttributeCommand;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Attribute\Exception\AttributeConstraintException;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Attribute\Exception\AttributeNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Attribute\Query\GetAttributeForEditing;
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
            uriTemplate: '/attributes/attributes/{attributeId}',
            CQRSQuery: GetAttributeForEditing::class,
            scopes: [
                'attribute_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/attributes/attributes',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddAttributeCommand::class,
            CQRSQuery: GetAttributeForEditing::class,
            scopes: [
                'attribute_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/attributes/attributes/{attributeId}',
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditAttributeCommand::class,
            CQRSQuery: GetAttributeForEditing::class,
            scopes: [
                'attribute_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/attributes/attributes/{attributeId}',
            requirements: ['attributeId' => '\d+'],
            CQRSCommand: DeleteAttributeCommand::class,
            scopes: [
                'attribute_write',
            ],
        ),
    ],
    exceptionToStatus: [
        AttributeConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        AttributeNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Attribute
{
    #[ApiProperty(identifier: true)]
    public int $attributeId;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $attributeGroupId;

    #[LocalizedValue]
    #[Assert\NotBlank(groups: ['Create'])]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'names', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_CATALOG_NAME,
        ]),
    ])]
    public array $names;

    public string $color;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank(allowNull: true)]
    public array $shopIds;

    public const QUERY_MAPPING = [
        '[localizedNames]' => '[names]',
        '[name]' => '[names]',
        '[associatedShopIds]' => '[shopIds]',
    ];

    public const CREATE_COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[shopIds]' => '[associatedShopIds]',
    ];

    public const UPDATE_COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[shopIds]' => '[associatedShopIds]',
    ];
}
