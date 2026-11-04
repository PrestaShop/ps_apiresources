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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\SqlRequestSettings;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Command\SaveSqlRequestSettingsCommand;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Exception\SqlRequestSettingsConstraintException;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Query\GetSqlRequestSettings;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Settings applied to the files exported by the SQL manager. This is a singleton resource:
 * it has no identifier, the GET and the PUT share the same URI and expose the very same
 * properties, and the PUT returns the updated settings by replaying the GET query.
 */
#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/sql-request-settings',
            CQRSQuery: GetSqlRequestSettings::class,
            scopes: ['sql_management_read'],
        ),
        new CQRSUpdate(
            uriTemplate: '/sql-request-settings',
            read: false,
            CQRSCommand: SaveSqlRequestSettingsCommand::class,
            CQRSQuery: GetSqlRequestSettings::class,
            scopes: ['sql_management_write'],
        ),
    ],
    exceptionToStatus: [
        SqlRequestSettingsConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class SqlRequestSettings
{
    /**
     * One of utf-8, iso-8859-1.
     */
    #[Assert\NotBlank]
    public string $fileEncoding;

    #[Assert\NotBlank]
    public string $fileSeparator;
}
