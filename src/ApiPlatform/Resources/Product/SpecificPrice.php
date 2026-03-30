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
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Command\AddSpecificPriceCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Command\DeleteSpecificPriceCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Command\EditSpecificPriceCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Exception\CannotAddSpecificPriceException;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Exception\CannotDeleteSpecificPriceException;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Exception\CannotUpdateSpecificPriceException;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Exception\SpecificPriceConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Exception\SpecificPriceNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\Query\GetSpecificPriceForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{specificPriceId}',
            requirements: ['specificPriceId' => '\d+'],
            CQRSQuery: GetSpecificPriceForEditing::class,
            scopes: ['product_read'],
        ),
        new CQRSCreate(
            uriTemplate: '/products',
            CQRSCommand: AddSpecificPriceCommand::class,
            CQRSQuery: GetSpecificPriceForEditing::class,
            scopes: ['product_write'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/products/{specificPriceId}',
            requirements: ['specificPriceId' => '\d+'],
            CQRSCommand: EditSpecificPriceCommand::class,
            CQRSQuery: GetSpecificPriceForEditing::class,
            scopes: ['product_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/products/{specificPriceId}',
            requirements: ['specificPriceId' => '\d+'],
            CQRSCommand: DeleteSpecificPriceCommand::class,
            scopes: ['product_write'],
        ),
    ],
    exceptionToStatus: [
        SpecificPriceNotFoundException::class => Response::HTTP_NOT_FOUND,
        SpecificPriceConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotAddSpecificPriceException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateSpecificPriceException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotDeleteSpecificPriceException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class SpecificPrice
{
    #[ApiProperty(identifier: true)]
    public int $specificPriceId;

    #[Assert\NotBlank]
    public int $productId;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['amount', 'percentage'])]
    public string $reductionType;

    #[Assert\NotBlank]
    public string $reductionValue;

    public bool $includeTax = true;

    public string $fixedPrice = '-1';

    public int $fromQuantity = 1;

    public ?string $dateTimeFrom = null;

    public ?string $dateTimeTo = null;

    public ?int $shopId = null;

    public ?int $combinationId = null;

    public ?int $currencyId = null;

    public ?int $countryId = null;

    public ?int $groupId = null;

    public ?int $customerId = null;
}
