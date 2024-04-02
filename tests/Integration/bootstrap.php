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
if (!defined('_PS_ROOT_DIR_')) {
    define('_PS_ROOT_DIR_', dirname(__DIR__, 4));
}
define('_PS_IN_TEST_', true);
define('_PS_MODULE_DIR_', _PS_ROOT_DIR_ . '/tests/Resources/modules/');
require_once _PS_ROOT_DIR_ . '/vendor/smarty/smarty/libs/functions.php';
require_once _PS_ROOT_DIR_ . '/admin-dev/bootstrap.php';

/*
 * Following code makes tests run under phpstorm
 * Else we get error : Class 'PHPUnit_Util_Configuration' not found
 * @see https://stackoverflow.com/questions/33299149/phpstorm-8-and-phpunit-problems-with-runinseparateprocess
 */
if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', dirname(__DIR__, 4) . '/vendor/autoload.php');
}

if (file_exists(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
}
