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
use PrestaShop\PrestaShop\Core\Domain\Profile\Command\DeleteProfileCommand;
use PrestaShop\PrestaShop\Core\Domain\Profile\Command\EditProfileCommand;
use PrestaShop\PrestaShop\Core\Domain\Profile\Exception\CannotDeleteSuperAdminProfileException;
use PrestaShop\PrestaShop\Core\Domain\Profile\Exception\FailedToDeleteProfileException;
use PrestaShop\PrestaShop\Core\Domain\Profile\Exception\ProfileConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Profile\Exception\ProfileNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Profile\Query\GetProfileForEditing;
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
        new CQRSPartialUpdate(
            uriTemplate: '/profiles/{profileId}',
            requirements: ['profileId' => '\d+'],
            CQRSCommand: EditProfileCommand::class,
            CQRSQuery: GetProfileForEditing::class,
            scopes: ['profile_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/profiles/{profileId}',
            requirements: ['profileId' => '\d+'],
            CQRSCommand: DeleteProfileCommand::class,
            scopes: ['profile_write'],
        ),
    ],
    exceptionToStatus: [
        ProfileConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ProfileNotFoundException::class => Response::HTTP_NOT_FOUND,
        FailedToDeleteProfileException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotDeleteSuperAdminProfileException::class => Response::HTTP_FORBIDDEN,
    ],
)]
class Profile
{
    #[ApiProperty(identifier: true)]
    public int $profileId;

    #[LocalizedValue]
    #[Assert\NotBlank]
    public array $names;

    public ?string $avatarUrl;

    public const QUERY_MAPPING = [
        '[localizedNames]' => '[names]',
    ];

    public const COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
    ];
}
