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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\ShowcaseCard;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\ShowcaseCard\Command\CloseShowcaseCardCommand;
use PrestaShop\PrestaShop\Core\Domain\ShowcaseCard\Query\GetShowcaseCardIsClosed;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/showcase-cards/{showcaseCardName}/{employeeId}',
            requirements: [
                'showcaseCardName' => '[a-z_-]+',
                'employeeId' => '\d+',
            ],
            CQRSQuery: GetShowcaseCardIsClosed::class,
            CQRSQueryMapping: [
                '[_queryResult]' => '[closed]',
            ],
            scopes: ['showcase_card_read'],
        ),
        new CQRSUpdate(
            uriTemplate: '/showcase-cards/{showcaseCardName}/{employeeId}/close',
            requirements: [
                'showcaseCardName' => '[a-z_-]+',
                'employeeId' => '\d+',
            ],
            allowEmptyBody: true,
            CQRSCommand: CloseShowcaseCardCommand::class,
            CQRSQuery: GetShowcaseCardIsClosed::class,
            CQRSQueryMapping: [
                '[_queryResult]' => '[closed]',
            ],
            scopes: ['showcase_card_write'],
        ),
    ],
)]
class ShowcaseCard
{
    public string $showcaseCardName;

    public int $employeeId;

    public bool $closed;
}
