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

namespace PrestaShop\Module\APIResources\ApiPlatform\Serializer;

class Callbacks
{
    /**
     * Convert value to integer
     */
    public static function toInt($value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Convert array of values to array of integers
     */
    public static function toIntArray($values): ?array
    {
        if (null === $values || !is_array($values)) {
            return null;
        }

        return array_map('intval', $values);
    }

    /**
     * Convert order detail refunds data structure
     */
    public static function toOrderDetailRefunds($value): ?array
    {
        if (null === $value || !is_array($value)) {
            return null;
        }

        if (empty($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $orderDetailId => $quantity) {
            // Validate that both key and value are convertible to integers
            if (!is_numeric($orderDetailId) || (!is_numeric($quantity) && null !== $quantity)) {
                throw new \InvalidArgumentException('Order detail refunds must contain numeric order detail IDs and quantities');
            }

            $orderDetailIdInt = (int) $orderDetailId;

            // Handle null quantities by converting to 0 or positive int
            $quantityInt = null !== $quantity ? (int) $quantity : 1;

            // Validate that quantity is positive (let domain handle specific business rules)
            if ($quantityInt <= 0) {
                $quantityInt = 1; // Use default quantity of 1 for invalid values
            }

            $result[$orderDetailIdInt] = $quantityInt;
        }

        return $result;
    }

    /**
     * Convert cancelled products data structure
     */
    public static function toCancelledProducts($value): ?array
    {
        if (null === $value || !is_array($value)) {
            return null;
        }

        if (empty($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $orderDetailId => $quantity) {
            // Validate that both key and value are convertible to integers
            if (!is_numeric($orderDetailId) || !is_numeric($quantity)) {
                throw new \InvalidArgumentException('Cancelled products must contain numeric order detail IDs and quantities');
            }

            $orderDetailIdInt = (int) $orderDetailId;
            $quantityInt = (int) $quantity;

            // Validate that quantity is positive
            if ($quantityInt <= 0) {
                throw new \InvalidArgumentException('Cancellation quantity must be positive');
            }

            $result[$orderDetailIdInt] = $quantityInt;
        }

        return $result;
    }

    /**
     * Convert boolean value
     */
    public static function toBool($value): ?bool
    {
        if (null === $value) {
            return null;
        }

        return (bool) $value;
    }

    /**
     * Convert to float
     */
    public static function toFloat($value): ?float
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Convert to string
     */
    public static function toString($value): ?string
    {
        if (null === $value) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Convert value to DecimalNumber
     */
    public static function toDecimalNumber($value): ?\PrestaShop\Decimal\DecimalNumber
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof \PrestaShop\Decimal\DecimalNumber) {
            return $value;
        }

        try {
            return new \PrestaShop\Decimal\DecimalNumber((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('Invalid decimal number format: ' . $value);
        }
    }

    /**
     * Convert cart rule name, providing default if null
     */
    public static function toCartRuleName($value): string
    {
        if (null === $value || '' === $value) {
            return 'Cart Rule';
        }

        return (string) $value;
    }

    /**
     * Convert cart rule type, providing default if null
     */
    public static function toCartRuleType($value): int
    {
        if (null === $value) {
            return 0; // Default cart rule type
        }

        return (int) $value;
    }
}
