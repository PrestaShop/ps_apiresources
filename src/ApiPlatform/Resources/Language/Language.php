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
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/languages',
            CQRSCommand: AddLanguageCommand::class,
            scopes: ['language_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        LanguageConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        LanguageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Language
{
    #[ApiProperty(identifier: true)]
    public int $languageId;

    #[Assert\NotBlank]
    public string $name;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 2)]
    public string $isoCode;

    #[Assert\NotBlank]
    public string $tagIETF;

    #[Assert\NotBlank]
    public string $shortDateFormat;

    #[Assert\NotBlank]
    public string $fullDateFormat;

    public string $flagImagePath = '';

    public string $noPictureImagePath = '';

    public bool $rtl = false;

    public bool $enabled = true;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $shopIds = [];

    public const COMMAND_MAPPING = [
        '[rtl]' => '[isRtl]',
        '[enabled]' => '[isActive]',
        '[shopIds]' => '[shopAssociation]',
    ];
}
