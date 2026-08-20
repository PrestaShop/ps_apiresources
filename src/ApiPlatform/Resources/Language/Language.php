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
use PrestaShop\PrestaShop\Core\Domain\Language\Command\AddLanguageCommand;
use PrestaShop\PrestaShop\Core\Domain\Language\Command\DeleteLanguageCommand;
use PrestaShop\PrestaShop\Core\Domain\Language\Command\EditLanguageCommand;
use PrestaShop\PrestaShop\Core\Domain\Language\Command\ToggleLanguageStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\DefaultLanguageException;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageException;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Language\Query\GetLanguageForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The read and the write sides of a language are not exactly the same set of fields, which is
 * why they had ended up in separate classes: locale is computed by the core and never accepted
 * as input, while the flag and no-picture image paths are inputs the editing query does not
 * return. Both stay on this resource, with the direction they actually have.
 */
#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/languages/{languageId}',
            requirements: ['languageId' => '\d+'],
            CQRSQuery: GetLanguageForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['language_read'],
        ),
        new CQRSCreate(
            uriTemplate: '/languages',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddLanguageCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetLanguageForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['language_write'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/languages/{languageId}',
            requirements: ['languageId' => '\d+'],
            read: false,
            CQRSCommand: EditLanguageCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetLanguageForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['language_write'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/languages/{languageId}/set-status',
            requirements: ['languageId' => '\d+'],
            read: false,
            output: false,
            CQRSCommand: ToggleLanguageStatusCommand::class,
            CQRSCommandMapping: self::STATUS_COMMAND_MAPPING,
            scopes: ['language_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/languages/{languageId}',
            requirements: ['languageId' => '\d+'],
            CQRSCommand: DeleteLanguageCommand::class,
            scopes: ['language_write'],
        ),
    ],
    exceptionToStatus: [
        LanguageNotFoundException::class => Response::HTTP_NOT_FOUND,
        DefaultLanguageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        LanguageConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        LanguageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Language
{
    #[ApiProperty(identifier: true)]
    public int $languageId;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $name;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Length(min: 2, max: 2)]
    public string $isoCode;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $tagIETF;

    /**
     * Read only: the core derives the locale from the IETF tag, AddLanguageCommand takes no
     * locale argument.
     */
    #[ApiProperty(writable: false)]
    public string $locale;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $shortDateFormat;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $fullDateFormat;

    /**
     * Write only: GetLanguageForEditing does not return the image paths.
     */
    #[ApiProperty(readable: false)]
    public string $flagImagePath = '';

    #[ApiProperty(readable: false)]
    public string $noPictureImagePath = '';

    public bool $rtl = false;

    public bool $enabled = true;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $shopIds = [];

    // EditableLanguage exposes shopAssociation and isActive
    public const QUERY_MAPPING = [
        '[shopAssociation]' => '[shopIds]',
        '[active]' => '[enabled]',
    ];

    // Add/EditLanguageCommand expect isRtl, isActive and shopAssociation
    public const COMMAND_MAPPING = [
        '[rtl]' => '[isRtl]',
        '[enabled]' => '[isActive]',
        '[shopIds]' => '[shopAssociation]',
    ];

    // ToggleLanguageStatusCommand expects an $expectedStatus constructor argument
    public const STATUS_COMMAND_MAPPING = [
        '[enabled]' => '[expectedStatus]',
    ];
}
