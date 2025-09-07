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
        $updatedFrom = $filters['updated_from'] ?? null;
        $updatedTo = $filters['updated_to'] ?? null;
        $statusId = isset($filters['status_id']) ? (int) $filters['status_id'] : null;
        $query = $filters['q'] ?? null; // email or order reference

        $page = max(1, (int) ($context['pagination']['page'] ?? 1));
        $itemsPerPage = max(1, (int) ($context['pagination']['itemsPerPage'] ?? 30));
        $offset = ($page - 1) * $itemsPerPage;

        $connection = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getConnection();
        $qb = $connection
            ->createQueryBuilder()
            ->select('o.id_order')
            ->from(_DB_PREFIX_ . 'orders', 'o')
            ->innerJoin('o', _DB_PREFIX_ . 'customer', 'c', 'c.id_customer = o.id_customer');

        if ($dateFrom) {
            $qb->andWhere('o.date_add >= :date_from');
            $qb->setParameter('date_from', $dateFrom);
        }
        if ($dateTo) {
            $qb->andWhere('o.date_add <= :date_to');
            $qb->setParameter('date_to', $dateTo);
        }
        if ($updatedFrom) {
            $qb->andWhere('o.date_upd >= :updated_from');
            $qb->setParameter('updated_from', $updatedFrom);
        }
        if ($updatedTo) {
            $qb->andWhere('o.date_upd <= :updated_to');
            $qb->setParameter('updated_to', $updatedTo);
        }
        if ($statusId) {
            $qb->andWhere('o.current_state = :status_id');
            $qb->setParameter('status_id', $statusId);
        }
        if ($query) {
            $qb->andWhere('(o.reference = :query_ref OR c.email LIKE :query_email)');
            $qb->setParameter('query_ref', $query);
            $qb->setParameter('query_email', '%' . $query . '%');
        }

        $qb
            ->orderBy('o.id_order', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($itemsPerPage);

        $rows = $qb->execute()->fetchAllAssociative();

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

        $view->shopId = (int) $order->id_shop;
        $view->langId = (int) $order->id_lang;

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
