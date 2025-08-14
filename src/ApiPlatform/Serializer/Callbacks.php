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
}


