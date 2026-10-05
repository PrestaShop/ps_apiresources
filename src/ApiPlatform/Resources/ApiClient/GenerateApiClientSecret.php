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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\ApiClient;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\ApiClient\Command\GenerateApiClientSecretCommand;
use PrestaShop\PrestaShop\Core\Domain\ApiClient\Exception\ApiClientNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/api-clients/{apiClientId}/secrets',
            requirements: ['apiClientId' => '\d+'],
            read: false,
            allowEmptyBody: true,
            CQRSCommand: GenerateApiClientSecretCommand::class,
            scopes: [
                'api_client_write',
            ],
            ApiResourceMapping: self::RESOURCE_MAPPING,
        ),
    ],
    exceptionToStatus: [
        ApiClientNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class GenerateApiClientSecret
{
    #[ApiProperty(identifier: true)]
    public int $apiClientId;

    public string $secret;

    public const RESOURCE_MAPPING = [
        // The command returns the generated secret as a scalar, wrapped as _commandResult by the core.
        '[_commandResult]' => '[secret]',
    ];
}
