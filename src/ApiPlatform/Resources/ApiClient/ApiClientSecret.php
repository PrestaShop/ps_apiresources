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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\ApiClient;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\ApiClient\Command\ForceApiClientSecretCommand;
use PrestaShop\PrestaShop\Core\Domain\ApiClient\Command\GenerateApiClientSecretCommand;
use PrestaShop\PrestaShop\Core\Domain\ApiClient\Exception\ApiClientConstraintException;
use PrestaShop\PrestaShop\Core\Domain\ApiClient\Exception\ApiClientNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The secret of an API client, which both operations below share the URI of.
 *
 * The secret is stored hashed, so there is no read side: it can only ever be known at the
 * moment it is set. That is why the PUT (which lets the core generate a random secret) is
 * the only operation returning content — it is the caller's single chance to read it. The
 * PATCH sets a secret the caller already knows, so it answers an empty 204.
 */
#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/api-clients/{apiClientId}/secrets',
            requirements: ['apiClientId' => '\d+'],
            read: false,
            allowEmptyBody: true,
            CQRSCommand: GenerateApiClientSecretCommand::class,
            scopes: ['api_client_write'],
            ApiResourceMapping: self::RESOURCE_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/api-clients/{apiClientId}/secrets',
            requirements: ['apiClientId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Force']],
            read: false,
            output: false,
            CQRSCommand: ForceApiClientSecretCommand::class,
            scopes: ['api_client_write'],
        ),
    ],
    exceptionToStatus: [
        ApiClientNotFoundException::class => Response::HTTP_NOT_FOUND,
        ApiClientConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ApiClientSecret
{
    #[ApiProperty(identifier: true)]
    public int $apiClientId;

    /**
     * Required by the PATCH, ignored by the PUT which generates it. The core value object
     * additionally constrains its length (32 to 72 characters).
     */
    #[Assert\NotBlank(groups: ['Force'])]
    public string $secret;

    public const RESOURCE_MAPPING = [
        // GenerateApiClientSecretCommand returns the generated secret as a scalar,
        // wrapped as _commandResult by the core.
        '[_commandResult]' => '[secret]',
    ];
}
