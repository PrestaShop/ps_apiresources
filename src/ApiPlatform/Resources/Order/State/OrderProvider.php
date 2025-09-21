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
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\OrderList;

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
     * @return OrderList[]|array
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

        // For regular order listing, fetch orders from database
        try {
            $orders = \Order::getOrdersWithInformations(1, 1, 100); // Get orders with limit
            $items = [];

            foreach ($orders as $orderData) {
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
                    'totalPaidTaxIncl' => $orderData['total_paid_tax_incl'],
                    'totalProductsTaxIncl' => $orderData['total_products_wt'],
                    'customerId' => (int) $orderData['id_customer'],
                    'dateAdd' => $orderData['date_add'],
                ];
            }

            return [
                'items' => $items,
                'totalItems' => count($items),
                'sortOrder' => 'ASC',
                'limit' => 100,
                'filters' => [],
            ];
        } catch (\Exception $e) {
            // If database operations fail, return empty structure with proper format
            return [
                'items' => [],
                'totalItems' => 0,
                'sortOrder' => 'ASC',
                'limit' => 100,
                'filters' => [],
            ];
        }
    }
}
