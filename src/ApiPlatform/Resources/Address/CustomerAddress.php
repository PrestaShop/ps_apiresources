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
use PrestaShop\PrestaShop\Core\Domain\Address\Command\AddCustomerAddressCommand;
use PrestaShop\PrestaShop\Core\Domain\Address\Command\EditCustomerAddressCommand;
use PrestaShop\PrestaShop\Core\Domain\Address\Exception\AddressConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Address\Exception\AddressNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Address\Query\GetCustomerAddressForEditing;
use PrestaShop\PrestaShop\Core\Domain\Country\ValueObject\CountryId;
use PrestaShop\PrestaShop\Core\Domain\State\ValueObject\StateIdInterface;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/addresses/customers/{addressId}',
            CQRSQuery: GetCustomerAddressForEditing::class,
            scopes: [
                'address_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/addresses/customers',
            CQRSCommand: AddCustomerAddressCommand::class,
            CQRSQuery: GetCustomerAddressForEditing::class,
            scopes: [
                'address_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            validationContext: ['groups' => ['Default', 'Create']],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/addresses/customers/{addressId}',
            CQRSCommand: EditCustomerAddressCommand::class,
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
        AddressNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CustomerAddress
{
    #[ApiProperty(identifier: true)]
    public int $addressId;

    public int $customerId;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $addressAlias;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $firstName;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $lastName;

    #[Assert\NotBlank(groups: ['Create'])]
    #[TypedRegex([
        'type' => TypedRegex::TYPE_ADDRESS,
    ])]
    public string $address;

    #[TypedRegex([
        'type' => TypedRegex::TYPE_ADDRESS,
    ])]
    public ?string $address2;

    #[Assert\NotBlank(groups: ['Create'])]
    #[TypedRegex([
        'type' => TypedRegex::TYPE_CITY_NAME,
    ])]
    public string $city;

    #[TypedRegex([
        'type' => TypedRegex::TYPE_POST_CODE,
    ])]
    public string $postCode;

    #[Assert\NotBlank(groups: ['Create'])]
    public CountryId $countryId;

    #[TypedRegex([
        'type' => TypedRegex::TYPE_DNI_LITE,
    ])]
    public ?string $dni;

    public ?string $company;

    public ?string $vatNumber;

    public StateIdInterface $stateId;

    #[TypedRegex([
        'type' => TypedRegex::TYPE_PHONE_NUMBER,
    ])]
    public ?string $homePhone;

    #[TypedRegex([
        'type' => TypedRegex::TYPE_PHONE_NUMBER,
    ])]
    public ?string $mobilePhone;

    public ?string $other;

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
