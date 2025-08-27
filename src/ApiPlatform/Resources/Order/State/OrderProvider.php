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
 * @author    PrestaShop SA and Contributors
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\Order as OrderResource;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\OrderList as OrderListResource;

/**
 * State provider for Orders resources (list and item),
 * used as a fallback when a Core CQRS query is not available.
 */
class OrderProvider implements ProviderInterface
{
    /**
     * @param Operation $operation Api Platform operation metadata
     * @param array $uriVariables URI variables (expects 'orderId' for item)
     * @param array $context Api Platform context (uses 'filters' and 'pagination')
     *
     * @return iterable|OrderView|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable|OrderResource|null
    {
        if (isset($uriVariables['orderId'])) {
            $orderId = (int) $uriVariables['orderId'];
            $order = new \Order($orderId);
            if (!\Validate::isLoadedObject($order)) {
                return null;
            }

            return $this->mapOrder($order, false);
        }

        // Collection endpoint
        $filters = $context['filters'] ?? [];
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $statusId = isset($filters['status_id']) ? (int) $filters['status_id'] : null;
        $query = $filters['q'] ?? null; // email or order reference

        $where = [];
        if ($dateFrom) {
            $where[] = "o.date_add >= '" . pSQL($dateFrom) . "'";
        }
        if ($dateTo) {
            $where[] = "o.date_add <= '" . pSQL($dateTo) . "'";
        }
        if ($statusId) {
            $where[] = 'o.current_state = ' . (int) $statusId;
        }
        if ($query) {
            $where[] = "(o.reference = '" . pSQL($query) . "' OR c.email LIKE '%" . pSQL($query) . "%')";
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $page = max(1, (int) ($context['pagination']['page'] ?? 1));
        $itemsPerPage = max(1, (int) ($context['pagination']['itemsPerPage'] ?? 30));
        $offset = ($page - 1) * $itemsPerPage;

        $sql = 'SELECT o.id_order FROM ' . _DB_PREFIX_ . 'orders o '
            . 'INNER JOIN ' . _DB_PREFIX_ . 'customer c ON (c.id_customer = o.id_customer) '
            . $whereSql . ' '
            . 'ORDER BY o.id_order DESC '
            . 'LIMIT ' . (int) $itemsPerPage . ' OFFSET ' . (int) $offset;

        $rows = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) ?: [];

        $collection = [];
        foreach ($rows as $row) {
            $order = new \Order((int) $row['id_order']);
            if (\Validate::isLoadedObject($order)) {
                $collection[] = $this->mapOrder($order, true);
            }
        }

        return $collection;
    }

    /**
     * Map legacy Order into API View.
     */
    private function mapOrder(\Order $order, bool $forList): OrderResource|OrderListResource
    {
        $view = $forList ? new OrderListResource() : new OrderResource();
        // Use id property name consistent with DTO
        $view->orderId = (int) $order->id;
        $view->reference = (string) $order->reference;
        $view->statusId = (int) $order->current_state;

        $state = new \OrderState($order->current_state, (int) $order->id_lang);
        $view->status = \Validate::isLoadedObject($state) ? (string) $state->name : '';

        $currency = new \Currency((int) $order->id_currency);
        $view->currencyIso = \Validate::isLoadedObject($currency) ? (string) $currency->iso_code : '';

        $view->totalPaidTaxIncl = (string) $order->total_paid_tax_incl;
        $view->totalProductsTaxIncl = (string) $order->total_products_wt;

        if (property_exists($view, 'customerId')) {
            $view->customerId = (int) $order->id_customer;
        }
        if (property_exists($view, 'deliveryAddressId')) {
            $view->deliveryAddressId = (int) $order->id_address_delivery;
        }
        if (property_exists($view, 'invoiceAddressId')) {
            $view->invoiceAddressId = (int) $order->id_address_invoice;
        }

        $view->dateAdd = (new \DateTime($order->date_add))->format(DATE_ATOM);

        if (!$forList) {
            foreach ($order->getProducts() as $p) {
                $view->items[] = [
                    'orderDetailId' => (int) ($p['id_order_detail'] ?? 0),
                    'productId' => (int) ($p['product_id'] ?? 0),
                    'productAttributeId' => isset($p['product_attribute_id']) ? (int) $p['product_attribute_id'] : null,
                    'name' => (string) ($p['product_name'] ?? ''),
                    'reference' => $p['product_reference'] ?? null,
                    'quantity' => (int) ($p['product_quantity'] ?? 0),
                    'unitPriceTaxIncl' => (string) ($p['unit_price_tax_incl'] ?? '0'),
                ];
            }
        }

        return $view;
    }
}
