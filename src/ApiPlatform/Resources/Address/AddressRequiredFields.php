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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Address;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\Address\Command\SetRequiredFieldsForAddressCommand;
use PrestaShop\PrestaShop\Core\Domain\Address\Exception\AddressConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Address\Exception\CannotSetRequiredFieldsForAddressException;
use PrestaShop\PrestaShop\Core\Domain\Address\Query\GetRequiredFieldsForAddress;
use PrestaShop\PrestaShop\Core\Domain\Address\ValueObject\RequiredFields;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/addresses/required-fields',
            CQRSQuery: GetRequiredFieldsForAddress::class,
            scopes: ['address_read'],
            CQRSQueryMapping: ['[_queryResult]' => '[requiredFields]'],
            openapi: new OpenApiOperation(
                summary: 'Get the address fields made required.',
                description: 'Lists the optional address fields that are required in the front office address form.',
            ),
        ),
        new CQRSUpdate(
            uriTemplate: '/addresses/required-fields',
            // Returns the updated list, read through the same query as the GET operation
            extraProperties: [
                'minVersion' => '9.2.0',
            ],
            CQRSCommand: SetRequiredFieldsForAddressCommand::class,
            CQRSQuery: GetRequiredFieldsForAddress::class,
            CQRSQueryMapping: ['[_queryResult]' => '[requiredFields]'],
            scopes: ['address_write'],
            openapi: new OpenApiOperation(
                summary: 'Set the address fields made required.',
                description: 'Replaces the list of optional address fields that are required in the front office '
                    . 'address form and returns it.',
            ),
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        AddressConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotSetRequiredFieldsForAddressException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class AddressRequiredFields
{
    /**
     * @var string[]
     */
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'items' => ['type' => 'string', 'enum' => RequiredFields::ALLOWED_REQUIRED_FIELDS],
            'example' => ['phone', 'company'],
        ]
    )]
    public array $requiredFields = [];
}
