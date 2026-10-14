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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\ExtraPropertyDefinition;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Command\BulkDeleteExtraPropertyDefinitionCommand;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Exception\ExtraPropertyDefinitionNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\ExtraProperty\Exception\ProtectedModuleExtraPropertyDefinitionException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Deletes several definitions at once. A definition that cannot be deleted (unknown id,
 * module-owned) does not stop the others: the response is a 207 listing the per-item failures.
 */
#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/extra-property-definitions/bulk-delete',
            CQRSCommand: BulkDeleteExtraPropertyDefinitionCommand::class,
            scopes: ['extra_property_definition_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
            allowEmptyBody: false,
            extraProperties: ExtraPropertyDefinition::VERSION_GATE,
            openapiContext: [
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['extraPropertyDefinitionIds'],
                                'properties' => [
                                    'extraPropertyDefinitionIds' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'integer'],
                                    ],
                                    'dropColumn' => [
                                        'type' => 'boolean',
                                        'default' => false,
                                        'description' => 'Also drop the storage columns and every stored value of these properties.',
                                    ],
                                ],
                            ],
                            'example' => [
                                'extraPropertyDefinitionIds' => [1, 3],
                                'dropColumn' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ),
    ],
    exceptionToStatus: [
        ExtraPropertyDefinitionNotFoundException::class => Response::HTTP_NOT_FOUND,
        ProtectedModuleExtraPropertyDefinitionException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkDeleteExtraPropertyDefinitions
{
    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank]
    public array $extraPropertyDefinitionIds;

    /**
     * Also drop the storage columns (data loss); defaults to keeping them.
     */
    public ?bool $dropColumn;

    public const COMMAND_MAPPING = [
        '[extraPropertyDefinitionIds]' => '[ids]',
    ];
}
