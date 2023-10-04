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

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class Ps_Apiresources extends Module
{
    public function __construct()
    {
        $this->name = 'ps_apiresources';
        $this->displayName = $this->trans('PrestaShop API Resources', [], 'Modules.Apiresources.Admin');
        $this->description = $this->trans('Includes the resources allowing using the API for the PrestaShop domain, all endpoints are based on CQRS commands/queries from the Core and we APIPlatform framework is used as a base.', [], 'Modules.Apiresources.Admin');
        $this->author = 'PrestaShop';
        $this->version = '0.1.0';
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
        $this->need_instance = 0;
        $this->tab = 'administration';
        parent::__construct();
    }
}
