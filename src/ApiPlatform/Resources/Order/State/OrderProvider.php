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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

/**
 * Provider for Order resources.
 * Handles both regular order listing and write-scope operations.
 */
class OrderProvider implements ProviderInterface
{
    /**
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     *
     * @return array{items: array, totalItems: int, sortOrder: string, limit: int, filters: array}
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // For write-scope endpoint, return empty array (internal endpoint)
        if (str_contains($operation->getUriTemplate() ?? '', '_write-scope')) {
            return [
                'items' => [],
                'totalItems' => 0,
                'sortOrder' => 'ASC',
                'limit' => 100,
                'filters' => [],
            ];
        }

        // Extract pagination parameters from context
        $page = $context['filters']['page'] ?? 1;
        $limit = $context['filters']['itemsPerPage'] ?? 100;

        // Ensure page and limit are valid
        $page = max(1, (int) $page);
        $limit = max(1, min(1000, (int) $limit)); // Max 1000 items per page

        // Calculate offset for pagination
        $offset = ($page - 1) * $limit;

        // For regular order listing, fetch orders from database
        try {
            // Get all orders first to calculate total (this is not optimal but matches current implementation)
            $allOrders = \Order::getOrdersWithInformations();
            $totalItems = count($allOrders);

            // Apply pagination manually since getOrdersWithInformations doesn't support offset
            $paginatedOrders = array_slice($allOrders, $offset, $limit);

            $items = [];

            foreach ($paginatedOrders as $orderData) {
                $order = new \Order($orderData['id_order']);
                $customer = new \Customer($order->id_customer);

                $items[] = [
                    'orderId' => (int) $orderData['id_order'],
                    'reference' => $orderData['reference'],
                    'status' => $orderData['order_state'],
                    'statusId' => (int) $orderData['current_state'],
                    'shopId' => (int) $orderData['id_shop'],
                    'langId' => (int) $orderData['id_lang'],
                    'currencyIso' => $orderData['iso_code'] ?? 'EUR',
                    'totalPaidTaxIncl' => (float) $orderData['total_paid_tax_incl'],
                    'totalProductsTaxIncl' => (float) $orderData['total_products_wt'],
                    'customerId' => (int) $orderData['id_customer'],
                    'dateAdd' => $orderData['date_add'],
                ];
            }

            return [
                'items' => $items,
                'totalItems' => $totalItems,
                'sortOrder' => 'ASC',
                'limit' => $limit,
                'filters' => [],
            ];
        } catch (\Exception $e) {
            // If database operations fail, return empty structure with proper format
            return [
                'items' => [],
                'totalItems' => 0,
                'sortOrder' => 'ASC',
                'limit' => $limit,
                'filters' => [],
            ];
        }
    }
}
