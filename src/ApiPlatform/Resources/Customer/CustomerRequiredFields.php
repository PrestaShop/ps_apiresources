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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\Customer\Command\SetRequiredFieldsForCustomerCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CannotSetRequiredFieldsForCustomerException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\InvalidCustomerRequiredFieldsException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Query\GetRequiredFieldsForCustomer;
use PrestaShop\PrestaShop\Core\Domain\Customer\ValueObject\RequiredField;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/customers/required-fields',
            extraProperties: [
                'minVersion' => '9.2.0',
            ],
            CQRSQuery: GetRequiredFieldsForCustomer::class,
            scopes: ['customer_read'],
            CQRSQueryMapping: ['[_queryResult]' => '[requiredFields]'],
            openapi: new OpenApiOperation(
                summary: 'Get the optional customer fields made required.',
                description: 'Lists the optional fields of the customer account that are required in the front office forms: '
                    . '"optin" for the partner offers subscription, "newsletter" for the newsletter subscription.',
            ),
        ),
        new CQRSUpdate(
            uriTemplate: '/customers/required-fields',
            // Returns the updated list, read through the same query as the GET operation, hence the same version
            extraProperties: [
                'minVersion' => '9.2.0',
            ],
            CQRSCommand: SetRequiredFieldsForCustomerCommand::class,
            CQRSQuery: GetRequiredFieldsForCustomer::class,
            CQRSQueryMapping: ['[_queryResult]' => '[requiredFields]'],
            scopes: ['customer_write'],
            openapi: new OpenApiOperation(
                summary: 'Set the optional customer fields made required.',
                description: 'Replaces the list of required optional fields ("optin" for the partner offers subscription, '
                    . '"newsletter" for the newsletter subscription) and returns it.',
            ),
        ),
    ],
    exceptionToStatus: [
        InvalidCustomerRequiredFieldsException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotSetRequiredFieldsForCustomerException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CustomerRequiredFields
{
    /**
     * The customer fields that should be required (e.g. "optin", "newsletter").
     *
     * @var string[]
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => RequiredField::ALLOWED_REQUIRED_FIELDS], 'example' => ['optin', 'newsletter']])]
    public array $requiredFields = [];
}
