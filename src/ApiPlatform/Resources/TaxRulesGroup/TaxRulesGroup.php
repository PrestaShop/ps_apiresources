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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\TaxRulesGroup;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\Command\AddTaxRulesGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\Command\DeleteTaxRulesGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\Command\EditTaxRulesGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\Exception\CannotAddTaxRulesGroupException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\Exception\TaxRulesGroupNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\Query\GetTaxRulesGroupForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/tax-rules-groups',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddTaxRulesGroupCommand::class,
            scopes: ['tax_rules_group_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/tax-rules-groups/{taxRulesGroupId}',
            requirements: ['taxRulesGroupId' => '\d+'],
            output: false,
            CQRSCommand: DeleteTaxRulesGroupCommand::class,
            scopes: ['tax_rules_group_write']
        ),
        new CQRSGet(
            uriTemplate: '/tax-rules-groups/{taxRulesGroupId}',
            requirements: ['taxRulesGroupId' => '\d+'],
            CQRSQuery: GetTaxRulesGroupForEditing::class,
            scopes: ['tax_rules_group_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/tax-rules-groups/{taxRulesGroupId}',
            requirements: ['taxRulesGroupId' => '\d+'],
            read: false,
            CQRSCommand: EditTaxRulesGroupCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetTaxRulesGroupForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['tax_rules_group_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        CannotAddTaxRulesGroupException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        TaxRulesGroupNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class TaxRulesGroup
{
    #[ApiProperty(identifier: true)]
    public int $taxRulesGroupId;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Length(min: 1, max: 64)]
    public string $name;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $enabled;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank(allowNull: true)]
    public array $shopIds;

    public const COMMAND_MAPPING = [
        '[shopIds]' => '[shopAssociation]',
    ];

    public const QUERY_MAPPING = [
        '[active]' => '[enabled]',
        '[shopAssociationIds]' => '[shopIds]',
    ];
}
