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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Language;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Language\Command\EditLanguageCommand;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageException;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/languages/{languageId}',
            requirements: ['languageId' => '\d+'],
            read: false,
            CQRSCommand: EditLanguageCommand::class,
            scopes: ['language_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        LanguageNotFoundException::class => Response::HTTP_NOT_FOUND,
        LanguageConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        LanguageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class EditLanguage
{
    #[ApiProperty(identifier: true)]
    public int $languageId;

    public ?string $name = null;

    public ?string $isoCode = null;

    public ?string $tagIETF = null;

    public ?string $shortDateFormat = null;

    public ?string $fullDateFormat = null;

    public ?string $flagImagePath = null;

    public ?string $noPictureImagePath = null;

    public ?bool $rtl = null;

    public ?bool $enabled = null;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public ?array $shopIds = null;

    public const COMMAND_MAPPING = [
        '[rtl]' => '[isRtl]',
        '[enabled]' => '[isActive]',
        '[shopIds]' => '[shopAssociation]',
    ];
}
