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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CatalogPriceRule;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Command\BulkDeleteCatalogPriceRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Exception\CatalogPriceRuleException;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Exception\CatalogPriceRuleNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The bulk operations of the domain share this class.
 */
#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/catalog-price-rules/bulk-delete',
            CQRSCommand: BulkDeleteCatalogPriceRuleCommand::class,
            scopes: ['catalog_price_rule_write'],
            allowEmptyBody: false,
            openapi: new OpenApiOperation(
                summary: 'Delete several catalog price rules.',
                description: 'Deletes the listed catalog price rules one after the other. The core bulk command '
                    . 'does not collect the failures (no 207): the first unknown rule stops the deletion with a '
                    . '404, the rules listed before it are deleted and the ones after it are kept.',
            ),
        ),
    ],
    exceptionToStatus: [
        CatalogPriceRuleNotFoundException::class => Response::HTTP_NOT_FOUND,
        CatalogPriceRuleException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkCatalogPriceRules
{
    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['example' => [1, 3]])]
    #[Assert\NotBlank]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $catalogPriceRuleIds;
}
