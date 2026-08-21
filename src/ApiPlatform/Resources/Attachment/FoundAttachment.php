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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Attachment;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Exception\EmptySearchException;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Exception\EmptySearchInputException;
use PrestaShop\PrestaShop\Core\Domain\Attachment\Query\SearchAttachment;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/attachments/search',
            CQRSQuery: SearchAttachment::class,
            scopes: [
                'attachment_read',
            ],
            CQRSQueryMapping: [
                '[phrase]' => '[searchPhrase]',
            ],
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            parameters: new Parameters([
                new QueryParameter(
                    key: 'phrase',
                    required: true,
                    description: 'Search phrase to find attachments by name'
                ),
            ]),
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'phrase',
                        'in' => 'query',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                        'description' => 'Search phrase to find attachments by name',
                    ],
                ],
            ],
        ),
    ],
    exceptionToStatus: [
        EmptySearchInputException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        EmptySearchException::class => Response::HTTP_NOT_FOUND,
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class FoundAttachment
{
    #[ApiProperty(identifier: true)]
    public int $attachmentId;

    #[LocalizedValue]
    public array $names;

    #[LocalizedValue]
    public array $descriptions;

    public string $fileName;

    public string $mimeType;

    public int $fileSize;

    public const API_RESOURCE_MAPPING = [
        '[localizedNames]' => '[names]',
        '[localizedDescriptions]' => '[descriptions]',
    ];
}
