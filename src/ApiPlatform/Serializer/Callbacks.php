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
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Serializer;

final class Callbacks
{
    /**
     * Cast provided value to integer, signature matches Symfony Serializer callbacks.
     *
     * @param mixed $value
     * @param mixed $object
     * @param mixed $attribute
     * @param mixed $format
     * @param mixed $context
     *
     * @return int
     */
    public static function toInt($value, $object = null, $attribute = null, $format = null, $context = null): int
    {
        return (int) $value;
    }

    /**
     * Cast array of values to integers, signature matches Symfony Serializer callbacks.
     *
     * @param mixed $value
     * @param mixed $object
     * @param mixed $attribute
     * @param mixed $format
     * @param mixed $context
     *
     * @return int[]
     */
    public static function toIntArray($value, $object = null, $attribute = null, $format = null, $context = null): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map(static fn ($item) => (int) $item, $value);
    }

    /**
     * Convert associative array of order detail identifiers to cancellation payload.
     *
     * Example input: ["5" => 2] becomes [["orderDetailId" => 5, "quantity" => 2]].
     *
     * @param mixed $value
     * @param mixed $object
     * @param mixed $attribute
     * @param mixed $format
     * @param mixed $context
     *
     * @return array<int, array{orderDetailId:int, quantity:int}>
     */
    public static function toCancelledProducts($value, $object = null, $attribute = null, $format = null, $context = null): array
    {
        if (!is_array($value)) {
            return [];
        }

        $cancelled = [];
        foreach ($value as $orderDetailId => $quantity) {
            $cancelled[] = [
                'orderDetailId' => (int) $orderDetailId,
                'quantity' => (int) $quantity,
            ];
        }

        return $cancelled;
    }

    /**
     * Convert associative array of order detail identifiers into standard refund format.
     *
     * Example input: ["5" => 2] becomes [5 => ["quantity" => 2]].
     *
     * @param mixed $value
     * @param mixed $object
     * @param mixed $attribute
     * @param mixed $format
     * @param mixed $context
     *
     * @return array<int, array{quantity:int}>
     */
    public static function toOrderDetailRefunds($value, $object = null, $attribute = null, $format = null, $context = null): array
    {
        if (!is_array($value)) {
            return [];
        }

        $refunds = [];
        foreach ($value as $orderDetailId => $quantity) {
            if (is_array($quantity) && isset($quantity['quantity'])) {
                $quantityValue = $quantity['quantity'];
            } else {
                $quantityValue = $quantity;
            }

            $refunds[(int) $orderDetailId] = [
                'quantity' => (int) $quantityValue,
            ];
        }

        return $refunds;
    }
}


