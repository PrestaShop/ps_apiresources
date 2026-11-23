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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Module\APIResources\ApiPlatform\Metadata\CQRSDeleteCollection;
use PrestaShop\Module\APIResources\ApiPlatform\Metadata\CQRSUpdateCollection;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Product\Dto\ProductCustomizationField;
use PrestaShop\PrestaShop\Core\Domain\Product\Customization\Command\RemoveAllCustomizationFieldsFromProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Customization\Command\SetProductCustomizationFieldsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Customization\Exception\CustomizationException;
use PrestaShop\PrestaShop\Core\Domain\Product\Customization\Exception\CustomizationFieldNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Customization\Query\GetProductCustomizationFields;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/{productId}/customization-fields',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'List the customization fields of a product.',
                description: 'The labels are read in the shop of the context. The list is empty for a product '
                    . 'without customization fields, and for an unknown product.',
            ),
            // The query doesn't check the product: no 404 on this operation
            exceptionToStatus: [
                ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            CQRSQuery: GetProductCustomizationFields::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::RESOURCE_MAPPING,
            scopes: ['product_read'],
        ),
        new CQRSUpdateCollection(
            uriTemplate: '/products/{productId}/customization-fields',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Set the customization fields of a product.',
                description: 'Requires PrestaShop 9.2.2. Sets the customization fields of the product and answers '
                    . 'them like the GET: a field with a customizationFieldId is updated (its labels in the shop of '
                    . 'the context), a field without one is added to every shop of the product, and the fields left '
                    . 'out are removed.',
            ),
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: SetProductCustomizationFieldsCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetProductCustomizationFields::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::RESOURCE_MAPPING,
            scopes: ['product_write'],
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
        ),
        new CQRSDeleteCollection(
            uriTemplate: '/products/{productId}/customization-fields',
            requirements: ['productId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'Remove all the customization fields of a product.',
                description: 'Requires PrestaShop 9.2.2. Answers the customization fields of the product, an empty '
                    . 'list.',
            ),
            // The command only checks the product: none of the errors of the body of the PUT
            exceptionToStatus: [
                ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
                ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
            ],
            CQRSCommand: RemoveAllCustomizationFieldsFromProductCommand::class,
            CQRSQuery: GetProductCustomizationFields::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::RESOURCE_MAPPING,
            scopes: ['product_write'],
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        // The first matching class wins: CustomizationFieldNotFoundException extends CustomizationException
        CustomizationFieldNotFoundException::class => Response::HTTP_NOT_FOUND,
        CustomizationException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProductCustomizationFieldList
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    public int $customizationFieldId;

    /**
     * 0 for a file field, 1 for a text field.
     */
    public int $type;

    /**
     * The label of the field, by locale.
     *
     * @var string[]
     */
    #[LocalizedValue]
    public array $names;

    public bool $required;

    public bool $addedByModule;

    /**
     * The customization fields to set, only read by the update.
     *
     * @var ProductCustomizationField[]|null
     */
    #[ApiProperty(readable: false)]
    #[Assert\NotBlank(groups: ['Update'])]
    #[Assert\Valid]
    public ?array $customizationFields = null;

    public const QUERY_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
    ];

    public const RESOURCE_MAPPING = [
        '[localizedNames]' => '[names]',
    ];

    public const COMMAND_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[customizationFields][@index][customizationFieldId]' => '[customizationFields][@index][id]',
        '[customizationFields][@index][names]' => '[customizationFields][@index][localized_names]',
        '[customizationFields][@index][required]' => '[customizationFields][@index][is_required]',
        '[customizationFields][@index][addedByModule]' => '[customizationFields][@index][added_by_module]',
    ];
}
