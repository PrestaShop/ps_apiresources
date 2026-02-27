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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Tax;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Tax\Command\AddTaxCommand;
use PrestaShop\PrestaShop\Core\Domain\Tax\Command\DeleteTaxCommand;
use PrestaShop\PrestaShop\Core\Domain\Tax\Command\EditTaxCommand;
use PrestaShop\PrestaShop\Core\Domain\Tax\Exception\TaxNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Tax\Query\GetTaxForEditing;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/taxes',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddTaxCommand::class,
            scopes: ['tax_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/taxes/{taxId}',
            requirements: ['taxId' => '\d+'],
            output: false,
            CQRSCommand: DeleteTaxCommand::class,
            scopes: ['tax_write']
        ),
        new CQRSGet(
            uriTemplate: '/taxes/{taxId}',
            requirements: ['taxId' => '\d+'],
            CQRSQuery: GetTaxForEditing::class,
            scopes: ['tax_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/taxes/{taxId}',
            requirements: ['taxId' => '\d+'],
            read: false,
            CQRSCommand: EditTaxCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetTaxForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['tax_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        TaxNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Tax
{
    #[ApiProperty(identifier: true)]
    public int $taxId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'names', allowNull: true)]
    public array $names;

    #[Assert\NotNull(groups: ['Create'])]
    public DecimalNumber $rate;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $enabled;

    public const COMMAND_MAPPING = [
        '[enabled]' => '[active]',
        '[names]' => '[localizedNames]',
    ];

    public const QUERY_MAPPING = [
        '[active]' => '[enabled]',
        '[localizedNames]' => '[names]',
    ];
}
