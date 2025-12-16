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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Address;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\Address\Command\EditOrderAddressCommand;
use PrestaShop\PrestaShop\Core\Domain\Address\Exception\AddressConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Address\Query\GetCustomerAddressForEditing;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Country\ValueObject\CountryId;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\InvalidAddressTypeException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\State\Exception\StateConstraintException;
use PrestaShop\PrestaShop\Core\Domain\State\ValueObject\StateIdInterface;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/addresses/orders/{orderId}',
            CQRSCommand: EditOrderAddressCommand::class,
            CQRSQuery: GetCustomerAddressForEditing::class,
            scopes: [
                'address_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            validationContext: ['groups' => ['Default', 'Update']],
        ),
    ],
    exceptionToStatus: [
        AddressConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CountryConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        StateConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        InvalidAddressTypeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class OrderAddress
{
    // Identifiers from URI
    #[ApiProperty(identifier: true)]
    public int $orderId = 0;

    public string $addressType;

    // Optional address fields for update
    public ?string $addressAlias = null;

    public ?string $firstName = null;

    public ?string $lastName = null;

    #[TypedRegex([
        'type' => TypedRegex::TYPE_ADDRESS,
    ])]
    public ?string $address = null;

    #[TypedRegex([
        'type' => TypedRegex::TYPE_ADDRESS,
    ])]
    public ?string $address2 = null;

    #[TypedRegex([
        'type' => TypedRegex::TYPE_CITY_NAME,
    ])]
    public ?string $city = null;

    #[TypedRegex([
        'type' => TypedRegex::TYPE_POST_CODE,
    ])]
    public ?string $postCode = null;

    public ?CountryId $countryId = null;

    public ?StateIdInterface $stateId = null;

    #[TypedRegex([
        'type' => TypedRegex::TYPE_PHONE_NUMBER,
    ])]
    public ?string $homePhone = null;

    #[TypedRegex([
        'type' => TypedRegex::TYPE_PHONE_NUMBER,
    ])]
    public ?string $mobilePhone = null;

    public ?string $company = null;

    public ?string $vatNumber = null;

    public ?string $other = null;

    #[TypedRegex([
        'type' => TypedRegex::TYPE_DNI_LITE,
    ])]
    public ?string $dni = null;

    public const QUERY_MAPPING = [
        '[id]' => '[addressId]',
    ];

    public const COMMAND_MAPPING = [
        '[postCode]' => '[postcode]',
        '[homePhone]' => '[phone]',
        '[mobilePhone]' => '[phone_mobile]',
        '[vatNumber]' => '[vat_number]',
        '[stateId]' => '[id_state]',
    ];
}
