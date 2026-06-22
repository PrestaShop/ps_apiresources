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
use PrestaShop\PrestaShop\Core\Domain\Language\Command\DeleteLanguageCommand;
use PrestaShop\PrestaShop\Core\Domain\Language\Command\ToggleLanguageStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\DefaultLanguageException;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageException;
use PrestaShop\PrestaShop\Core\Domain\Language\Exception\LanguageNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/languages/{languageId}/set-status',
            requirements: ['languageId' => '\d+'],
            // No output, 204 code
            output: false,
            read: false,
            CQRSCommand: ToggleLanguageStatusCommand::class,
            CQRSCommandMapping: [
                '[enabled]' => '[expectedStatus]',
            ],
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
        LanguageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Language
{
    #[ApiProperty(identifier: true)]
    public int $languageId;

    /**
     * Only used by the set-status operation, mapped to the command expected status.
     */
    public bool $enabled;
}
