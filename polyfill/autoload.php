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

/*
 * The CQRS*Collection operations only exist from PrestaShop 9.2.1: on an older core, PHP fails to instantiate a resource
 * using them, which breaks the container build. These stand-ins are only loaded when the core lacks the class, the
 * autoloader is appended so the core one always wins.
 */
spl_autoload_register(static function (string $class): void {
    $polyfills = [
        'PrestaShopBundle\ApiPlatform\Metadata\CQRSCreateCollection' => __DIR__ . '/CQRSCreateCollection.php',
        'PrestaShopBundle\ApiPlatform\Metadata\CQRSDeleteCollection' => __DIR__ . '/CQRSDeleteCollection.php',
        'PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdateCollection' => __DIR__ . '/CQRSPartialUpdateCollection.php',
        'PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdateCollection' => __DIR__ . '/CQRSUpdateCollection.php',
        'PrestaShopBundle\ApiPlatform\Metadata\PolyfillOperationTrait' => __DIR__ . '/PolyfillOperationTrait.php',
    ];
    if (isset($polyfills[$class])) {
        require_once $polyfills[$class];
    }
});
