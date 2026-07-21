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
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Language\Query\GetLanguageForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/languages/{languageId}/details',
            requirements: ['languageId' => '\d+'],
            CQRSQuery: GetLanguageForEditing::class,
            scopes: ['language_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
    ],
    exceptionToStatus: [
        LanguageNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class LanguageForEditing
{
    #[ApiProperty(identifier: true)]
    public int $languageId;

    public string $name;

    public string $isoCode;

    public string $tagIETF;

    public string $locale;

    public string $shortDateFormat;

    public string $fullDateFormat;

    public bool $rtl;

    public bool $enabled;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $shopIds;

    public const QUERY_MAPPING = [
        '[shopAssociation]' => '[shopIds]',
        '[active]' => '[enabled]',
    ];
}
