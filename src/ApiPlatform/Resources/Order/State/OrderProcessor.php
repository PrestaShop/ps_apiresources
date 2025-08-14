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
use ApiPlatform\State\ProcessorInterface;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\OrderStatus;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\OrderTracking;

/**
 * State processor handling order actions (status and tracking updates),
 * used as a fallback when a Core CQRS command is not available.
 */
class OrderProcessor implements ProcessorInterface
{
    /**
     * @param mixed $data Deserialized input DTO (PatchOrderInput or TrackingInput)
     * @param Operation $operation Api Platform operation metadata
     * @param array $uriVariables URI variables (expects 'orderId')
     * @param array $context Api Platform context
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $orderId = isset($uriVariables['orderId']) ? (int) $uriVariables['orderId'] : null;
        if (!$orderId) {
            return null;
        }

        if ($data instanceof OrderStatus) {
            $this->updateOrderStatus($orderId, $data->statusId, $data->statusCode);
        } elseif ($data instanceof OrderTracking) {
            $this->updateOrderTracking($orderId, $data->number ?? null, $data->url ?? null);
        }

        // No content by default for PATCH
        return null;
    }

    /**
     * Update order status using legacy OrderHistory.
     */
    private function updateOrderStatus(int $orderId, ?int $statusId, ?string $statusCode): void
    {
        $order = new \Order($orderId);
        if (!\Validate::isLoadedObject($order)) {
            throw new \RuntimeException('Order not found');
        }

        $targetStatusId = $statusId;
        if ($targetStatusId === null && $statusCode !== null) {
            // Mapping statusCode not implemented in MVP
            throw new \InvalidArgumentException('statusCode mapping not implemented');
        }
        if ($targetStatusId === null) {
            throw new \InvalidArgumentException('Missing statusId');
        }

        $state = new \OrderState((int) $targetStatusId);
        if (!\Validate::isLoadedObject($state)) {
            throw new \InvalidArgumentException('Invalid statusId');
        }
        if ((int) $order->current_state === (int) $targetStatusId) {
            return;
        }

        $history = new \OrderHistory();
        $history->id_order = (int) $order->id;
        $history->id_employee = 0;
        $history->changeIdOrderState((int) $targetStatusId, $order, true);
        if (!$history->add()) {
            throw new \RuntimeException('Failed to change order status');
        }
    }

    /**
     * Update order tracking information using legacy Order object.
     */
    private function updateOrderTracking(int $orderId, ?string $number, ?string $url): void
    {
        $order = new \Order($orderId);
        if (!\Validate::isLoadedObject($order)) {
            throw new \RuntimeException('Order not found');
        }
        if (!$number) {
            throw new \InvalidArgumentException('Tracking number required');
        }

        $order->shipping_number = (string) $number;
        if (property_exists($order, 'url_tracking') && $url) {
            $order->url_tracking = (string) $url;
        }
        if (false === $order->update()) {
            throw new \RuntimeException('Failed to update tracking');
        }
    }
}


