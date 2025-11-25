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
        && $this->registerHook('loadApiResourcesCustomFields')
        && $this->registerHook('persistApiResourcesCustomFields')
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

    /**
     * Hook to load custom fields data for API Resources entities
     *
     * This hook is called by the API Resources module to load custom fields data
     * when serializing an entity to JSON. You must return the modified $customFields array (chained hook).
     *
     * @param array $params Hook parameters:
     *                      - 'entity' (string): Entity name (e.g., 'AttributeGroup', 'Product')
     *                      - 'entityId' (int): Entity ID
     *                      - 'customFields' (array): Custom fields data array to populate
     *
     * @return array The enriched customFields array
     */
    public function hookLoadApiResourcesCustomFields(array $params)
    {
        $entity = $params['entity'];
        $entityId = (int) $params['entityId'];
        $customFields = &$params['customFields'];

        switch ($entity) {
            case 'AttributeGroup':
                $customFields = array_merge($customFields, $this->loadAttributeGroupCustomFields($entityId));
                break;
        }

        // IMPORTANT: This is a chained hook, you MUST return the modified array
        return $customFields;
    }

    /**
     * Hook to persist custom fields data for API Resources entities
     *
     * This hook is called by the API Resources module after an entity has been created or updated.
     * You should persist the custom fields data to your database tables.
     *
     * @param array $params Hook parameters:
     *                      - 'entity' (string): Entity name (e.g., 'AttributeGroup', 'Product')
     *                      - 'entityId' (int): Entity ID (after creation/update)
     *                      - 'customFieldsData' (array): Custom fields data extracted from the request
     *                      - 'entityData' (array): Normalized entity data (base fields only, without custom fields)
     *                      This contains all the native entity properties that were just persisted.
     *                      Useful for implementing business logic that depends on entity state.
     *
     * @return void
     */
    public function hookPersistApiResourcesCustomFields(array $params)
    {
        $entity = $params['entity'];
        $entityId = (int) $params['entityId'];
        $customFieldsData = $params['customFieldsData'];
        $entityData = $params['entityData'] ?? [];

        switch ($entity) {
            case 'AttributeGroup':
                $this->persistAttributeGroupCustomFields($entityId, $customFieldsData, $entityData);
                break;
        }
    }

    /**
     * Load custom fields for AttributeGroup entity
     *
     * @param int $entityId Entity ID
     *
     * @return array Custom fields data
     */
    private function loadAttributeGroupCustomFields(int $entityId): array
    {
        $result = [];

        // Load base fields
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'attribute_group_extra`
                WHERE `id_attribute_group` = ' . (int) $entityId;
        $row = Db::getInstance()->getRow($sql);

        if ($row) {
            $result['stringField'] = (string) ($row['string_field'] ?? '');
            $result['intField'] = (int) ($row['int_field'] ?? 0);
            $result['boolField'] = (bool) ($row['bool_field'] ?? false);
            $result['enumField'] = (string) ($row['enum_field'] ?? '');
        }

        // Load lang fields
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'attribute_group_lang_extra`
                WHERE `id_attribute_group` = ' . (int) $entityId;
        $rows = Db::getInstance()->executeS($sql);

        $langFields = [];
        if ($rows) {
            foreach ($rows as $row) {
                $idLang = (int) ($row['id_lang'] ?? 0);
                if ($idLang <= 0) {
                    continue;
                }

                // Get locale for this language ID
                $locale = Language::getLocaleById($idLang);
                if (!$locale) {
                    continue;
                }

                $langFields['stringLangField'][$locale] = (string) ($row['string_lang_field'] ?? '');
            }
        }

        if (!empty($langFields)) {
            $result['attributeGroupLangExtra'] = $langFields;
        }

        // Load shop fields
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'attribute_group_shop_extra`
                WHERE `id_attribute_group` = ' . (int) $entityId;
        $rows = Db::getInstance()->executeS($sql);

        $shopFields = [];
        if ($rows) {
            foreach ($rows as $row) {
                $idShop = (int) ($row['id_shop'] ?? 0);
                if ($idShop <= 0) {
                    continue;
                }

                $shopFields['intShopField'][(string) $idShop] = (int) ($row['int_shop_field'] ?? 0);
            }
        }

        if (!empty($shopFields)) {
            $result['attributeGroupShopExtra'] = $shopFields;
        }

        return $result;
    }

    /**
     * Persist custom fields for AttributeGroup entity
     *
     * @param int $entityId Entity ID
     * @param array $customFieldsData Custom fields data from request
     * @param array $entityData Normalized entity data (base fields only)
     *
     * @return void
     */
    private function persistAttributeGroupCustomFields(int $entityId, array $customFieldsData, array $entityData): void
    {
        // Example: You can use entityData to implement conditional logic
        // For instance, only persist custom fields if the entity type is 'select'
        // if (isset($entityData['type']) && $entityData['type'] !== 'select') {
        //     return; // Skip persistence for non-select attribute groups
        // }

        // Persist base fields
        if (isset($customFieldsData['stringField']) || isset($customFieldsData['intField']) || isset($customFieldsData['boolField']) || isset($customFieldsData['enumField'])) {
            $columns = ['`id_attribute_group`'];
            $values = [(int) $entityId];
            $updates = [];

            if (isset($customFieldsData['stringField'])) {
                $columns[] = '`string_field`';
                $value = pSQL((string) $customFieldsData['stringField']);
                $values[] = '\'' . $value . '\'';
                $updates[] = '`string_field` = \'' . $value . '\'';
            }

            if (isset($customFieldsData['intField'])) {
                $columns[] = '`int_field`';
                $value = (int) $customFieldsData['intField'];
                $values[] = (int) $value;
                $updates[] = '`int_field` = ' . (int) $value;
            }

            if (isset($customFieldsData['boolField'])) {
                $columns[] = '`bool_field`';
                $value = (bool) $customFieldsData['boolField'] ? 1 : 0;
                $values[] = (int) $value;
                $updates[] = '`bool_field` = ' . (int) $value;
            }

            if (isset($customFieldsData['enumField'])) {
                $columns[] = '`enum_field`';
                $value = pSQL((string) $customFieldsData['enumField']);
                $values[] = '\'' . $value . '\'';
                $updates[] = '`enum_field` = \'' . $value . '\'';
            }

            if (!empty($updates)) {
                $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'attribute_group_extra` (' . implode(', ', $columns) . ')
                        VALUES (' . implode(', ', $values) . ')
                        ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
                Db::getInstance()->execute($sql);
            }
        }

        // Persist lang fields
        // The serializer converts locale-based format to idLang-based format:
        // Input: {"stringLangField": {"fr-FR": "value", "en-GB": "value"}}
        // Output: [{"idLang": 1, "stringLangField": "value"}, {"idLang": 2, "stringLangField": "value"}]
        if (isset($customFieldsData['attributeGroupLangExtra'])) {
            $langData = $customFieldsData['attributeGroupLangExtra'];

            // Check if it's in the converted format (array of objects with idLang)
            if (is_array($langData) && !empty($langData) && isset($langData[0]) && is_array($langData[0]) && isset($langData[0]['idLang'])) {
                // Format: [{"idLang": 1, "stringLangField": "value"}, ...]
                foreach ($langData as $langRow) {
                    $idLang = (int) ($langRow['idLang'] ?? 0);
                    if ($idLang <= 0) {
                        continue;
                    }

                    // Process each field in the row
                    foreach ($langRow as $fieldName => $value) {
                        if ($fieldName === 'idLang' || $fieldName === '_jsonKey') {
                            continue;
                        }

                        // Map field name to column name (here we know it's stringLangField -> string_lang_field)
                        if ($fieldName === 'stringLangField') {
                            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'attribute_group_lang_extra`
                                    (`id_attribute_group`, `id_lang`, `string_lang_field`)
                                    VALUES (' . (int) $entityId . ', ' . (int) $idLang . ', \'' . pSQL((string) $value) . '\')
                                    ON DUPLICATE KEY UPDATE `string_lang_field` = \'' . pSQL((string) $value) . '\'';
                            Db::getInstance()->execute($sql);
                        }
                    }
                }
            } else {
                // Fallback: original locale-based format (should not happen after serializer conversion)
                if (isset($langData['stringLangField']) && is_array($langData['stringLangField'])) {
                    foreach ($langData['stringLangField'] as $locale => $value) {
                        $idLang = (int) Language::getIdByLocale($locale);
                        if ($idLang <= 0) {
                            continue;
                        }

                        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'attribute_group_lang_extra`
                                (`id_attribute_group`, `id_lang`, `string_lang_field`)
                                VALUES (' . (int) $entityId . ', ' . (int) $idLang . ', \'' . pSQL((string) $value) . '\')
                                ON DUPLICATE KEY UPDATE `string_lang_field` = \'' . pSQL((string) $value) . '\'';
                        Db::getInstance()->execute($sql);
                    }
                }
            }
        }

        // Persist shop fields
        // The serializer converts shop ID-based format to idShop-based format:
        // Input: {"intShopField": {"1": 100, "2": 200}}
        // Output: [{"idShop": 1, "intShopField": 100}, {"idShop": 2, "intShopField": 200}]
        if (isset($customFieldsData['attributeGroupShopExtra'])) {
            $shopData = $customFieldsData['attributeGroupShopExtra'];

            // Check if it's in the converted format (array of objects with idShop)
            if (is_array($shopData) && !empty($shopData) && isset($shopData[0]) && is_array($shopData[0]) && isset($shopData[0]['idShop'])) {
                // Format: [{"idShop": 1, "intShopField": 100}, ...]
                foreach ($shopData as $shopRow) {
                    $idShop = (int) ($shopRow['idShop'] ?? 0);
                    if ($idShop <= 0) {
                        continue;
                    }

                    // Process each field in the row
                    foreach ($shopRow as $fieldName => $value) {
                        if ($fieldName === 'idShop' || $fieldName === '_jsonKey') {
                            continue;
                        }

                        // Map field name to column name (here we know it's intShopField -> int_shop_field)
                        if ($fieldName === 'intShopField') {
                            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'attribute_group_shop_extra`
                                    (`id_attribute_group`, `id_shop`, `int_shop_field`)
                                    VALUES (' . (int) $entityId . ', ' . (int) $idShop . ', ' . (int) $value . ')
                                    ON DUPLICATE KEY UPDATE `int_shop_field` = ' . (int) $value;
                            Db::getInstance()->execute($sql);
                        }
                    }
                }
            } else {
                // Fallback: original shop ID-based format (should not happen after serializer conversion)
                if (isset($shopData['intShopField']) && is_array($shopData['intShopField'])) {
                    foreach ($shopData['intShopField'] as $shopId => $value) {
                        $idShop = (int) $shopId;
                        if ($idShop <= 0) {
                            continue;
                        }

                        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'attribute_group_shop_extra`
                                (`id_attribute_group`, `id_shop`, `int_shop_field`)
                                VALUES (' . (int) $entityId . ', ' . (int) $idShop . ', ' . (int) $value . ')
                                ON DUPLICATE KEY UPDATE `int_shop_field` = ' . (int) $value;
                        Db::getInstance()->execute($sql);
                    }
                }
            }
        }
    }
}
