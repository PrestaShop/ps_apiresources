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

namespace PrestaShop\Module\APIResources\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\OrderProductList;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderProductsForViewing;
use PrestaShop\PrestaShop\Core\Domain\Order\QueryResult\OrderProductsForViewing;
use PrestaShopBundle\ApiPlatform\NormalizationMapper;
use PrestaShopBundle\ApiPlatform\Pagination\PaginationElements;
use PrestaShopBundle\ApiPlatform\Serializer\CQRSApiSerializer;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * GetOrderProductsForViewingHandler returns a single OrderProductsForViewing
 * wrapper (rows are behind ->getProducts()), which the generic QueryListProvider
 * can't consume — it expects a paginated {items, count} shape. This provider
 * dispatches the query, unwraps ->getProducts(), and applies the offset / limit
 * pagination in memory.
 */
class OrderProductListProvider implements ProviderInterface
{
    private const DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly CommandBusInterface $queryBus,
        private readonly CQRSApiSerializer $domainSerializer,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return PaginationElements
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $request = $this->requestStack->getMainRequest();
        $productsSorting = $request?->query->get('productsSorting', 'ASC');
        $offset = (int) ($request?->query->get('offset', 0) ?? 0);
        $limit = (int) ($request?->query->get('limit', self::DEFAULT_LIMIT) ?? self::DEFAULT_LIMIT);

        $sorting = \strtoupper((string) $productsSorting) === 'DESC' ? 'DESC' : 'ASC';
        // GetOrderProductsForViewing has a private ctor + named constructors.
        // Use ::all() to fetch the whole list, then slice for pagination — the
        // wrapper doesn't expose a total count so we can't rely on ::paginated().
        $query = GetOrderProductsForViewing::all((int) $uriVariables['orderId'], $sorting);

        /** @var OrderProductsForViewing $result */
        $result = $this->queryBus->handle($query);
        $products = $result->getProducts();
        $count = \count($products);

        $paginated = \array_slice($products, $offset, $limit);
        $items = [];
        foreach ($paginated as $key => $product) {
            $normalized = $this->domainSerializer->normalize($product);
            $items[$key] = $this->domainSerializer->denormalize(
                \array_merge($normalized, $uriVariables),
                OrderProductList::class,
                null,
                [NormalizationMapper::NORMALIZATION_MAPPING => $operation->getExtraProperties()['ApiResourceMapping'] ?? null]
            );
        }

        return new PaginationElements(
            $count,
            null,
            \strtoupper((string) $productsSorting) === 'DESC' ? 'desc' : 'asc',
            $limit,
            $offset,
            [],
            $items
        );
    }
}
