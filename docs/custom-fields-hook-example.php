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
 * Example module demonstrating how to add custom fields to API Resources entities
 *
 * This module shows how to:
 * - Register the 'addApiResourcesCustomFields' hook
 * - Declare custom fields metadata for an entity (AttributeGroup)
 * - Handle base fields, localized fields (lang), and shop-specific fields
 *
 * JSON Format Examples:
 *
 * POST /api/attribute-groups
 * {
 *   "names": {"fr-FR": "Couleurs", "en-GB": "Colors"},
 *   "publicNames": {"fr-FR": "Couleurs", "en-GB": "Colors"},
 *   "type": "select",
 *   "shopIds": [1],
 *   "position": 0,
 *   "stringField": "My custom string",
 *   "intField": 42,
 *   "boolField": true,
 *   "enumField": "value1",
 *   "attributeGroupLangExtra": {
 *     "stringLangField": {
 *       "fr-FR": "Champ personnalisé en français",
 *       "en-GB": "Custom field in English"
 *     }
 *   },
 *   "attributeGroupShopExtra": {
 *     "intShopField": {
 *       "1": 100,
 *       "2": 200
 *     }
 *   }
 * }
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class Ps_Apiresources_Extra extends Module
{
    public function __construct()
    {
        $this->name = 'ps_apiresources_extra';
        $this->displayName = $this->trans('Add custom fields to Admin API Resources', [], 'Modules.Psapiresourcesextra.Admin');
        $this->description = $this->trans('Add custom fields to Admin API Resources entities', [], 'Modules.Psapiresourcesextra.Admin');
        $this->author = 'PrestaShop';
        $this->version = '0.0.1';
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => '9.99.99'];
        $this->need_instance = 0;
        $this->tab = 'administration';

        parent::__construct();
    }

    public function install()
    {
        return parent::install()
        && $this->registerHook('addApiResourcesCustomFields')
        && $this->installTables();
    }

    public function uninstall()
    {
        return $this->uninstallTables()
        && parent::uninstall();
    }

    /**
     * Hook to add custom fields to API Resources entities
     *
     * This hook is called by the API Resources module to collect custom field metadata.
     * You must return the modified $customFields array (chained hook).
     *
     * @param array $params Hook parameters:
     *                      - 'entity' (string): Entity name (e.g., 'AttributeGroup', 'Product')
     *                      - 'customFields' (array): Metadata structure to populate
     *
     * @return array The enriched customFields array
     */
    public function hookAddApiResourcesCustomFields(array $params)
    {
        $entity = $params['entity'];
        $customFields = &$params['customFields'];

        switch ($entity) {
            case 'AttributeGroup':
                // ============================================
                // Entity metadata (REQUIRED)
                // ============================================
                // Define the primary key column name for the entity.
                // If not set, it will be deduced from entity name (e.g., AttributeGroup -> id_attribute_group)
                if (!isset($customFields['entity']['idColumn'])) {
                    $customFields['entity'] = [
                        'idColumn' => 'id_attribute_group', // Primary key column in the main entity table
                    ];
                }

                // ============================================
                // Base fields (stored in attribute_group_extra table)
                // ============================================
                // These fields are directly accessible in JSON: "stringField": "value"
                // Table name is the key, field names are nested keys
                $customFields['fields']['attribute_group_extra'] = [
                    // String field example
                    'stringField' => [
                        'type' => 'string',              // Data type: 'string', 'integer', 'boolean', 'enum', 'date', 'datetime'
                        'column' => 'string_field',       // Database column name
                        'nullable' => false,               // Whether the field can be null (optional, defaults to false)
                    ],
                    // Integer field example
                    'intField' => [
                        'type' => 'integer',
                        'column' => 'int_field',
                        'nullable' => false,
                    ],
                    // Boolean field example
                    'boolField' => [
                        'type' => 'boolean',
                        'column' => 'bool_field',
                        'nullable' => false,
                    ],
                    // Enum field example (with validation)
                    'enumField' => [
                        'type' => 'enum',
                        'column' => 'enum_field',
                        'nullable' => false,
                        'validation' => ['value1', 'value2', 'value3'], // Allowed values for enum type
                    ],
                ];

                // ============================================
                // Localized fields (stored in attribute_group_lang_extra table)
                // ============================================
                // These fields are language-specific and use locales in JSON:
                // "attributeGroupLangExtra": {
                //   "stringLangField": {
                //     "fr-FR": "Valeur en français",
                //     "en-GB": "Value in English"
                //   }
                // }
                $customFields['lang']['attribute_group_lang_extra'] = [
                    // JSON key name in API responses/requests (camelCase)
                    '_jsonKey' => 'attributeGroupLangExtra',
                    // REQUIRED: Junction key to link with language table
                    'idLang' => ['type' => 'int', 'column' => 'id_lang'],
                    // Localized string field
                    'stringLangField' => [
                        'type' => 'string',
                        'column' => 'string_lang_field',
                        'nullable' => true, // This field can be null
                    ],
                ];

                // ============================================
                // Shop-specific fields (stored in attribute_group_shop_extra table)
                // ============================================
                // These fields are shop-specific and use shop IDs as keys in JSON:
                // "attributeGroupShopExtra": {
                //   "intShopField": {
                //     "1": 100,
                //     "2": 200
                //   }
                // }
                $customFields['shop']['attribute_group_shop_extra'] = [
                    // JSON key name in API responses/requests (camelCase)
                    '_jsonKey' => 'attributeGroupShopExtra',
                    // REQUIRED: Junction key to link with shop table
                    'idShop' => ['type' => 'int', 'column' => 'id_shop'],
                    // Shop-specific integer field
                    'intShopField' => [
                        'type' => 'integer',
                        'column' => 'int_shop_field',
                        'nullable' => false,
                    ],
                ];

                break;
        }

        // IMPORTANT: This is a chained hook, you MUST return the modified array
        return $customFields;
    }

    /**
     * Install custom fields tables
     *
     * Creates the SQL tables to store custom field data.
     * Table naming convention: {entity_table}_extra, {entity_table}_lang_extra, {entity_table}_shop_extra
     *
     * @return bool True if all tables were created successfully
     */
    private function installTables(): bool
    {
        $sql = [];

        // ============================================
        // Table for base custom fields
        // ============================================
        // Stores non-localized, non-shop-specific custom fields
        // Primary key: id_attribute_group (links to ps_attribute_group table)
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'attribute_group_extra` (
            `id_attribute_group` INT(10) UNSIGNED NOT NULL,
            `string_field` VARCHAR(128) NOT NULL DEFAULT "",
            `int_field` INT(11) NOT NULL DEFAULT 0,
            `bool_field` TINYINT(1) NOT NULL DEFAULT 0,
            `enum_field` VARCHAR(128) NOT NULL DEFAULT "",
            PRIMARY KEY (`id_attribute_group`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

        // ============================================
        // Table for localized custom fields
        // ============================================
        // Stores language-specific custom fields
        // Composite primary key: (id_attribute_group, id_lang)
        // One row per entity + language combination
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'attribute_group_lang_extra` (
            `id_attribute_group` INT(10) UNSIGNED NOT NULL,
            `id_lang` INT(10) UNSIGNED NOT NULL,
            `string_lang_field` VARCHAR(128) NOT NULL DEFAULT "",
            PRIMARY KEY (`id_attribute_group`, `id_lang`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

        // ============================================
        // Table for shop-specific custom fields
        // ============================================
        // Stores shop-specific custom fields
        // Composite primary key: (id_attribute_group, id_shop)
        // One row per entity + shop combination
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'attribute_group_shop_extra` (
            `id_attribute_group` INT(10) UNSIGNED NOT NULL,
            `id_shop` INT(10) UNSIGNED NOT NULL,
            `int_shop_field` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_attribute_group`, `id_shop`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Uninstall custom fields tables
     *
     * @return bool
     */
    private function uninstallTables(): bool
    {
        $tables = [
            'attribute_group_extra',
            'attribute_group_lang_extra',
            'attribute_group_shop_extra',
        ];

        foreach ($tables as $table) {
            $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $table . '`';
            if (!Db::getInstance()->execute($sql)) {
                return false;
            }
        }

        return true;
    }
}
