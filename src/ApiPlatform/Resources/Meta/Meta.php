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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Meta;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Meta\Command\AddMetaCommand;
use PrestaShop\PrestaShop\Core\Domain\Meta\Command\EditMetaCommand;
use PrestaShop\PrestaShop\Core\Domain\Meta\Exception\CannotAddMetaException;
use PrestaShop\PrestaShop\Core\Domain\Meta\Exception\CannotEditMetaException;
use PrestaShop\PrestaShop\Core\Domain\Meta\Exception\MetaConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Meta\Exception\MetaNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Meta\Query\GetMetaForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/metas/{metaId}',
            requirements: ['metaId' => '\d+'],
            CQRSQuery: GetMetaForEditing::class,
            scopes: ['meta_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/metas',
            CQRSCommand: AddMetaCommand::class,
            CQRSQuery: GetMetaForEditing::class,
            scopes: ['meta_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/metas/{metaId}',
            requirements: ['metaId' => '\d+'],
            CQRSCommand: EditMetaCommand::class,
            CQRSQuery: GetMetaForEditing::class,
            scopes: ['meta_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::EDIT_COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        MetaConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        MetaNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotAddMetaException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotEditMetaException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Meta
{
    #[ApiProperty(identifier: true)]
    public int $metaId;

    #[Assert\NotBlank]
    public string $pageName;

    #[LocalizedValue]
    public array $pageTitles;

    #[LocalizedValue]
    public array $metaDescriptions;

    #[LocalizedValue]
    public array $urlRewrites;

    public const QUERY_MAPPING = [
        '[localisedPageTitles]' => '[pageTitles]',
        '[localisedMetaDescriptions]' => '[metaDescriptions]',
        '[localisedUrlRewrites]' => '[urlRewrites]',
    ];

    public const COMMAND_MAPPING = [
        '[pageTitles]' => '[localisedPageTitle]',
        '[metaDescriptions]' => '[localisedMetaDescription]',
        '[urlRewrites]' => '[localisedRewriteUrls]',
    ];

    public const EDIT_COMMAND_MAPPING = [
        '[pageTitles]' => '[localisedPageTitles]',
        '[metaDescriptions]' => '[localisedMetaDescriptions]',
        '[urlRewrites]' => '[localisedRewriteUrls]',
    ];
}
