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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Language;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Language\Command\DeleteLanguageCommand;
use PrestaShop\PrestaShop\Core\Domain\Language\Command\EditLanguageCommand;
use PrestaShop\PrestaShop\Core\Domain\Language\Command\ToggleLanguageStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\CannotDisableDefaultLanguageException;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Language\Query\GetLanguageForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/languages/{languageId}',
            requirements: ['languageId' => '\d+'],
            CQRSQuery: GetLanguageForEditing::class,
            scopes: ['language_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/languages/{languageId}',
            requirements: ['languageId' => '\d+'],
            CQRSCommand: EditLanguageCommand::class,
            CQRSQuery: GetLanguageForEditing::class,
            scopes: ['language_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/languages/{languageId}',
            requirements: ['languageId' => '\d+'],
            CQRSCommand: DeleteLanguageCommand::class,
            scopes: ['language_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/languages/{languageId}/status',
            requirements: ['languageId' => '\d+'],
            read: false,
            CQRSCommand: ToggleLanguageStatusCommand::class,
            CQRSQuery: GetLanguageForEditing::class,
            scopes: ['language_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[active]' => '[expectedStatus]',
            ],
        ),
    ],
    exceptionToStatus: [
        LanguageConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        LanguageNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotDisableDefaultLanguageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Language
{
    #[ApiProperty(identifier: true)]
    public int $languageId;

    #[Assert\Length(max: 32)]
    public string $name;

    #[Assert\Length(min: 2, max: 2)]
    public string $isoCode;

    public string $tagIETF;

    public string $locale;

    public string $shortDateFormat;

    public string $fullDateFormat;

    public bool $isRtl;

    public bool $active;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    public array $shopIds;

    public const QUERY_MAPPING = [
        '[isActive]' => '[active]',
        '[shopAssociation]' => '[shopIds]',
    ];

    public const COMMAND_MAPPING = [
        '[active]' => '[isActive]',
        '[shopIds]' => '[shopAssociation]',
        '[isRtl]' => '[isRtl]',
    ];
}
