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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Profile\Command\AddProfileCommand;
use PrestaShop\PrestaShop\Core\Domain\Profile\Query\GetProfileForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/profiles/{profileId}',
            requirements: ['profileId' => '\d+'],
            CQRSQuery: GetProfileForEditing::class,
            scopes: ['profile_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/profiles',
            CQRSCommand: AddProfileCommand::class,
            CQRSQuery: GetProfileForEditing::class,
            scopes: ['profile_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
    ]
)]
class Profile
{
    #[ApiProperty(identifier: true)]
    public int $profileId;

    #[LocalizedValue]
    public array $names;

    public ?string $avatarUrl = null;

    protected const QUERY_MAPPING = [
        '[localizedNames]' => '[names]',
    ];

    protected const COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
    ];
}
