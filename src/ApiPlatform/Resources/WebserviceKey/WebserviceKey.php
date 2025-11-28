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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\WebserviceKey;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Webservice\Command\AddWebserviceKeyCommand;
use PrestaShop\PrestaShop\Core\Domain\Webservice\Command\EditWebserviceKeyCommand;
use PrestaShop\PrestaShop\Core\Domain\Webservice\Exception\WebserviceConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Webservice\Exception\WebserviceKeyNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Webservice\Query\GetWebserviceKeyForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\Form\Admin\Type\FormattedTextareaType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/webservice-keys',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddWebserviceKeyCommand::class,
            scopes: ['webservice_key_write'],
            CQRSCommandMapping: [
                '[shopIds]' => '[associatedShops]',
                '[enabled]' => '[status]',
            ],
        ),
        new CQRSGet(
            uriTemplate: '/webservice-keys/{webserviceKeyId}',
            requirements: ['webserviceKeyId' => '\d+'],
            CQRSQuery: GetWebserviceKeyForEditing::class,
            scopes: ['webservice_key_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/webservice-keys/{webserviceKeyId}',
            read: false,
            CQRSCommand: EditWebserviceKeyCommand::class,
            CQRSQuery: GetWebserviceKeyForEditing::class,
            scopes: ['webservice_key_write'],
            CQRSCommandMapping: [
                '[shopIds]' => '[shopAssociation]',
                '[enabled]' => '[status]',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        WebserviceKeyNotFoundException::class => Response::HTTP_NOT_FOUND,
        WebserviceConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class WebserviceKey
{
    #[ApiProperty(identifier: true)]
    public int $webserviceKeyId;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Length(min: 32, max: 32)]
    public string $key;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Length(min: 1, max: FormattedTextareaType::LIMIT_MEDIUMTEXT_UTF8_MB4)]
    public string $description;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $enabled;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'array']])]
    #[Assert\Collection(
        fields: [
            'DELETE' => new Assert\All(
                constraints: [
                    new Assert\Type(type: 'string'),
                ],
                groups: ['Create']
            ),
            'GET' => new Assert\All(
                constraints: [
                    new Assert\Type(type: 'string'),
                ],
                groups: ['Create']
            ),
            'HEAD' => new Assert\All(
                constraints: [
                    new Assert\Type(type: 'string'),
                ],
                groups: ['Create']
            ),
            'PATCH' => new Assert\All(
                constraints: [
                    new Assert\Type(type: 'string'),
                ],
                groups: ['Create']
            ),
            'PUT' => new Assert\All(
                constraints: [
                    new Assert\Type(type: 'string'),
                ],
                groups: ['Create']
            ),
            'POST' => new Assert\All(
                constraints: [
                    new Assert\Type(type: 'string'),
                ],
                groups: ['Create']
            ),
        ],
        allowMissingFields: true,
        groups: ['Create'])]
    public array $permissions;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank(allowNull: true)]
    public array $shopIds;

    public const QUERY_MAPPING = [
        '[status]' => '[enabled]',
        '[resourcePermissions]' => '[permissions]',
        '[associatedShops]' => '[shopIds]',
    ];

    public function setEnabled(string|bool $enabled): self
    {
        $this->enabled = (bool) $enabled;

        return $this;
    }
}
