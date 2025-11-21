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

namespace PrestaShop\Module\APIResources\CustomFields;

/**
 * Service to persist custom fields to database
 */
class CustomFieldsPersistenceService
{
    public function __construct(
        private readonly CustomFieldsMetadataProvider $metadataProvider,
    ) {
    }

    /**
     * Persist custom fields for an entity
     *
     * @param string $entityName Entity name
     * @param int $entityId Entity ID
     * @param array $customFieldsData Custom fields data extracted from the request
     *
     * @return bool
     */
    public function persistCustomFields(string $entityName, int $entityId, array $customFieldsData): bool
    {
        $metadata = $this->metadataProvider->getCustomFieldsMetadata($entityName);

        // Persist base fields
        if (!empty($metadata['fields'])) {
            foreach ($metadata['fields'] as $tableName => $fields) {
                $this->persistFieldsTable($tableName, $entityId, $fields, $customFieldsData, $entityName);
            }
        }

        // Persist lang fields
        if (!empty($metadata['lang'])) {
            foreach ($metadata['lang'] as $tableName => $fields) {
                $jsonKey = $fields['_jsonKey'] ?? $tableName;
                $this->persistLangFieldsTable($tableName, $entityId, $fields, $customFieldsData, $entityName, $jsonKey);
            }
        }

        // Persist shop fields
        if (!empty($metadata['shop'])) {
            foreach ($metadata['shop'] as $tableName => $fields) {
                $jsonKey = $fields['_jsonKey'] ?? $tableName;
                $this->persistShopFieldsTable($tableName, $entityId, $fields, $customFieldsData, $entityName, $jsonKey);
            }
        }

        return true;
    }

    /**
     * Persist base fields for a table
     *
     * @param string $tableName Table name
     * @param int $entityId Entity ID
     * @param array $fields Fields metadata
     * @param array $customFieldsData Custom fields data
     * @param string $entityName Entity name
     *
     * @return bool
     */
    private function persistFieldsTable(string $tableName, int $entityId, array $fields, array $customFieldsData, string $entityName): bool
    {
        $metadata = $this->metadataProvider->getCustomFieldsMetadata($entityName);
        $idColumn = $this->getIdColumn($metadata, $entityName);

        $columns = ['`' . bqSQL($idColumn) . '`'];
        $values = [(int) $entityId];
        $updates = [];

        foreach ($fields as $fieldName => $fieldMetadata) {
            $column = $fieldMetadata['column'];
            $columns[] = '`' . bqSQL($column) . '`';

            $value = $customFieldsData[$fieldName] ?? $this->getDefaultValue($fieldMetadata);
            $value = $this->castValue($value, $fieldMetadata['type']);

            $values[] = $this->escapeValue($value, $fieldMetadata['type']);
            $updates[] = '`' . bqSQL($column) . '` = ' . $this->escapeValue($value, $fieldMetadata['type']);
        }

        $sql = 'INSERT INTO `' . _DB_PREFIX_ . bqSQL($tableName) . '` (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')
                ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

        return \Db::getInstance()->execute($sql);
    }

    /**
     * Get ID column name from metadata or deduce from entity name
     *
     * @param array $metadata Entity metadata
     * @param string $entityName Entity name (e.g., 'AttributeGroup')
     *
     * @return string
     */
    private function getIdColumn(array $metadata, string $entityName): string
    {
        if (isset($metadata['entity']['idColumn'])) {
            return $metadata['entity']['idColumn'];
        }

        // Deduce from entity name: AttributeGroup -> id_attribute_group
        $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $entityName));

        return 'id_' . $snakeCase;
    }

    /**
     * Persist lang fields for a table
     *
     * @param string $tableName Table name
     * @param int $entityId Entity ID
     * @param array $fields Fields metadata
     * @param array $customFieldsData Custom fields data
     * @param string $entityName Entity name
     * @param string $jsonKey JSON key name for this table
     *
     * @return bool
     */
    private function persistLangFieldsTable(string $tableName, int $entityId, array $fields, array $customFieldsData, string $entityName, string $jsonKey): bool
    {
        $metadata = $this->metadataProvider->getCustomFieldsMetadata($entityName);
        $idColumn = $this->getIdColumn($metadata, $entityName);

        // Extract lang data using the JSON key
        $langData = $customFieldsData[$jsonKey] ?? [];

        if (empty($langData)) {
            return true;
        }

        // Insert or update records (only update fields that are provided)
        foreach ($langData as $langRow) {
            $columns = ['`' . bqSQL($idColumn) . '`'];
            $values = [(int) $entityId];
            $updates = [];

            foreach ($fields as $fieldName => $fieldMetadata) {
                if ($fieldName === 'idLang' || $fieldName === '_jsonKey') {
                    continue;
                }

                $column = $fieldMetadata['column'];
                $columns[] = '`' . bqSQL($column) . '`';

                // Only include field if it's provided in the request
                if (isset($langRow[$fieldName])) {
                    $value = $this->castValue($langRow[$fieldName], $fieldMetadata['type']);
                    $values[] = $this->escapeValue($value, $fieldMetadata['type']);
                    $updates[] = '`' . bqSQL($column) . '` = ' . $this->escapeValue($value, $fieldMetadata['type']);
                } else {
                    // Use existing value or default for INSERT, but don't update if not provided
                    $value = $this->getDefaultValue($fieldMetadata);
                    $values[] = $this->escapeValue($value, $fieldMetadata['type']);
                }
            }

            // Add id_lang
            $idLang = (int) ($langRow['idLang'] ?? 0);
            $columns[] = '`id_lang`';
            $values[] = (int) $idLang;

            $sql = 'INSERT INTO `' . _DB_PREFIX_ . bqSQL($tableName) . '` (' . implode(', ', $columns) . ')
                    VALUES (' . implode(', ', $values) . ')';

            if (!empty($updates)) {
                $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
            }

            \Db::getInstance()->execute($sql);
        }

        return true;
    }

    /**
     * Persist shop fields for a table
     *
     * @param string $tableName Table name
     * @param int $entityId Entity ID
     * @param array $fields Fields metadata
     * @param array $customFieldsData Custom fields data
     * @param string $entityName Entity name
     * @param string $jsonKey JSON key name for this table
     *
     * @return bool
     */
    private function persistShopFieldsTable(string $tableName, int $entityId, array $fields, array $customFieldsData, string $entityName, string $jsonKey): bool
    {
        $metadata = $this->metadataProvider->getCustomFieldsMetadata($entityName);
        $idColumn = $this->getIdColumn($metadata, $entityName);

        // Extract shop data using the JSON key
        $shopData = $customFieldsData[$jsonKey] ?? [];

        if (empty($shopData)) {
            return true;
        }

        // Insert or update records (only update fields that are provided)
        foreach ($shopData as $shopRow) {
            $columns = ['`' . bqSQL($idColumn) . '`'];
            $values = [(int) $entityId];
            $updates = [];

            foreach ($fields as $fieldName => $fieldMetadata) {
                if ($fieldName === 'idShop' || $fieldName === '_jsonKey') {
                    continue;
                }

                $column = $fieldMetadata['column'];
                $columns[] = '`' . bqSQL($column) . '`';

                // Only include field if it's provided in the request
                if (isset($shopRow[$fieldName])) {
                    $value = $this->castValue($shopRow[$fieldName], $fieldMetadata['type']);
                    $values[] = $this->escapeValue($value, $fieldMetadata['type']);
                    $updates[] = '`' . bqSQL($column) . '` = ' . $this->escapeValue($value, $fieldMetadata['type']);
                } else {
                    // Use existing value or default for INSERT, but don't update if not provided
                    $value = $this->getDefaultValue($fieldMetadata);
                    $values[] = $this->escapeValue($value, $fieldMetadata['type']);
                }
            }

            // Add id_shop
            $idShop = (int) ($shopRow['idShop'] ?? 0);
            $columns[] = '`id_shop`';
            $values[] = (int) $idShop;

            $sql = 'INSERT INTO `' . _DB_PREFIX_ . bqSQL($tableName) . '` (' . implode(', ', $columns) . ')
                    VALUES (' . implode(', ', $values) . ')';

            if (!empty($updates)) {
                $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
            }

            \Db::getInstance()->execute($sql);
        }

        return true;
    }

    /**
     * Load custom fields from database
     *
     * @param string $entityName Entity name
     * @param int $entityId Entity ID
     *
     * @return array Custom fields data
     */
    public function loadCustomFields(string $entityName, int $entityId): array
    {
        $metadata = $this->metadataProvider->getCustomFieldsMetadata($entityName);
        $result = [];

        $idColumn = $this->getIdColumn($metadata, $entityName);

        // Load base fields
        if (!empty($metadata['fields'])) {
            foreach ($metadata['fields'] as $tableName => $fields) {
                $sql = 'SELECT * FROM `' . _DB_PREFIX_ . bqSQL($tableName) . '`
                        WHERE `' . bqSQL($idColumn) . '` = ' . (int) $entityId;
                $row = \Db::getInstance()->getRow($sql);

                if ($row) {
                    foreach ($fields as $fieldName => $fieldMetadata) {
                        $column = $fieldMetadata['column'];
                        $value = $row[$column] ?? null;
                        $result[$fieldName] = $this->castValue($value, $fieldMetadata['type']);
                    }
                }
            }
        }

        // Load lang fields
        if (!empty($metadata['lang'])) {
            foreach ($metadata['lang'] as $tableName => $fields) {
                $jsonKey = $fields['_jsonKey'] ?? $tableName;
                $sql = 'SELECT * FROM `' . _DB_PREFIX_ . bqSQL($tableName) . '`
                        WHERE `' . bqSQL($idColumn) . '` = ' . (int) $entityId;
                $rows = \Db::getInstance()->executeS($sql);

                // Convert from idLang-based format to locale-based format
                $result[$jsonKey] = $this->convertLangFieldsToLocales($rows, $fields);
            }
        }

        // Load shop fields
        if (!empty($metadata['shop'])) {
            foreach ($metadata['shop'] as $tableName => $fields) {
                $jsonKey = $fields['_jsonKey'] ?? $tableName;
                $sql = 'SELECT * FROM `' . _DB_PREFIX_ . bqSQL($tableName) . '`
                        WHERE `' . bqSQL($idColumn) . '` = ' . (int) $entityId;
                $rows = \Db::getInstance()->executeS($sql);

                // Convert from idShop-based format to shop ID-based format
                $result[$jsonKey] = $this->convertShopFieldsToIds($rows, $fields);
            }
        }

        return $result;
    }

    /**
     * Get default value for a field
     *
     * @param array $fieldMetadata Field metadata
     *
     * @return mixed
     */
    private function getDefaultValue(array $fieldMetadata): mixed
    {
        return match ($fieldMetadata['type']) {
            'integer', 'int' => 0,
            'boolean', 'bool' => false,
            'float' => 0.0,
            'array' => [],
            default => '',
        };
    }

    /**
     * Cast value to the expected type
     *
     * @param mixed $value Value to cast
     * @param string $type Target type
     *
     * @return mixed
     */
    private function castValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer', 'int' => (int) $value,
            'boolean', 'bool' => (bool) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'array' => is_array($value) ? $value : [],
            default => $value,
        };
    }

    /**
     * Convert lang fields from idLang-based format to locale-based format
     *
     * Input format (from database with idLang):
     * [
     *   {"id_lang": 1, "string_lang_field": "value1"},
     *   {"id_lang": 2, "string_lang_field": "value2"}
     * ]
     *
     * Output format (with locales):
     * {
     *   "stringLangField": {
     *     "fr-FR": "value1",
     *     "en-GB": "value2"
     *   }
     * }
     *
     * @param array $rows Database rows
     * @param array $fields Fields metadata
     *
     * @return array Converted lang data with locales
     */
    private function convertLangFieldsToLocales(array $rows, array $fields): array
    {
        $result = [];

        foreach ($rows as $row) {
            $idLang = (int) ($row['id_lang'] ?? 0);
            if ($idLang <= 0) {
                continue;
            }

            // Get locale for this language ID
            $locale = \Language::getLocaleById($idLang);
            if (!$locale) {
                continue;
            }

            // Build result structure: fieldName => {locale => value}
            foreach ($fields as $fieldName => $fieldMetadata) {
                if ($fieldName === 'idLang' || $fieldName === '_jsonKey') {
                    continue;
                }

                $column = $fieldMetadata['column'];
                $value = $row[$column] ?? null;
                $value = $this->castValue($value, $fieldMetadata['type']);

                if (!isset($result[$fieldName])) {
                    $result[$fieldName] = [];
                }

                $result[$fieldName][$locale] = $value;
            }
        }

        return $result;
    }

    /**
     * Convert shop fields from idShop-based format to shop ID-based format
     *
     * Input format (from database with idShop):
     * [
     *   {"id_shop": 1, "int_shop_field": 100},
     *   {"id_shop": 2, "int_shop_field": 200}
     * ]
     *
     * Output format (with shop IDs as keys):
     * {
     *   "intShopField": {
     *     "1": 100,
     *     "2": 200
     *   }
     * }
     *
     * @param array $rows Database rows
     * @param array $fields Fields metadata
     *
     * @return array Converted shop data with shop IDs as keys
     */
    private function convertShopFieldsToIds(array $rows, array $fields): array
    {
        $result = [];

        foreach ($rows as $row) {
            $idShop = (int) ($row['id_shop'] ?? 0);
            if ($idShop <= 0) {
                continue;
            }

            // Build result structure: fieldName => {shopId => value}
            foreach ($fields as $fieldName => $fieldMetadata) {
                if ($fieldName === 'idShop' || $fieldName === '_jsonKey') {
                    continue;
                }

                $column = $fieldMetadata['column'];
                $value = $row[$column] ?? null;
                $value = $this->castValue($value, $fieldMetadata['type']);

                if (!isset($result[$fieldName])) {
                    $result[$fieldName] = [];
                }

                // Use shop ID as string key (JSON keys are always strings)
                $result[$fieldName][(string) $idShop] = $value;
            }
        }

        return $result;
    }

    /**
     * Escape value for SQL
     *
     * @param mixed $value Value to escape
     * @param string $type Value type
     *
     * @return string
     */
    private function escapeValue(mixed $value, string $type): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return match ($type) {
            'integer', 'int' => (string) ((int) $value),
            'boolean', 'bool' => (string) ((int) (bool) $value),
            'float' => (string) ((float) $value),
            'string', 'enum' => '\'' . pSQL((string) $value) . '\'',
            default => '\'' . pSQL((string) $value) . '\'',
        };
    }
}
