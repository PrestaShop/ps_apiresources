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
 * @author    Pascal Cescon <pascal.cescon@gmail.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\AttributeGroup;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Command\AddAttributeGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Command\DeleteAttributeGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Command\EditAttributeGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Exception\AttributeGroupConstraintException;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Exception\AttributeGroupNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Exception\CannotAddAttributeGroupException;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Exception\CannotUpdateAttributeGroupException;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Exception\DeleteAttributeGroupException;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Query\GetAttributeGroupForEditing;
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
            uriTemplate: '/attribute-groups/{attributeGroupId}',
            requirements: ['attributeGroupId' => '\d+'],
            CQRSQuery: GetAttributeGroupForEditing::class,
            scopes: ['attribute_group_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/attribute-groups',
            CQRSCommand: AddAttributeGroupCommand::class,
            CQRSQuery: GetAttributeGroupForEditing::class,
            scopes: ['attribute_group_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/attribute-groups/{attributeGroupId}',
            requirements: ['attributeGroupId' => '\d+'],
            CQRSCommand: EditAttributeGroupCommand::class,
            CQRSQuery: GetAttributeGroupForEditing::class,
            scopes: ['attribute_group_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/attribute-groups/{attributeGroupId}',
            requirements: ['attributeGroupId' => '\d+'],
            CQRSCommand: DeleteAttributeGroupCommand::class,
            scopes: ['attribute_group_write'],
        ),
    ],
    exceptionToStatus: [
        AttributeGroupConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        AttributeGroupNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotAddAttributeGroupException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateAttributeGroupException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        DeleteAttributeGroupException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class AttributeGroup
{
    #[ApiProperty(identifier: true)]
    public int $attributeGroupId;

    #[LocalizedValue]
    #[Assert\NotBlank]
    public array $names;

    #[LocalizedValue]
    #[Assert\NotBlank]
    public array $publicNames;

    #[Assert\Choice(choices: ['select', 'color', 'radio'])]
    public string $type;

    /**
     * @var int[]
     */
    public array $shopIds;

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
