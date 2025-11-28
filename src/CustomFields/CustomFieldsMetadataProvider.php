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
 * Service to retrieve custom fields metadata via the hook
 */
class CustomFieldsMetadataProvider
{
    /**
     * Cache for metadata by entity name
     *
     * @var array<string, array>
     */
    private array $metadataCache = [];

    /**
     * Get custom fields metadata for an entity
     *
     * @param string $entityName Entity name (e.g., 'AttributeGroup')
     *
     * @return array Custom fields metadata structure
     */
    public function getCustomFieldsMetadata(string $entityName): array
    {
        if (isset($this->metadataCache[$entityName])) {
            return $this->metadataCache[$entityName];
        }

        $customFields = [
            'entity' => [],
            'fields' => [],
            'lang' => [],
            'shop' => [],
        ];

        /**
         * CHAINED HOOK: addApiResourcesCustomFields
         *
         * Parameters provided to the hook:
         * - 'entity' (string): The name of the entity for which custom fields are being requested (e.g., 'AttributeGroup', 'Product').
         * - 'customFields' (array): The current array of custom fields metadata for the entity, which can be extended or modified.
         *
         * Structure of 'customFields':
         * [
         *     'entity' => [
         *         'idColumn' => (string) The primary key column name (e.g., 'id_attribute_group').
         *                                 If not provided, it will be deduced from entity name.
         *     ],
         *     'fields' => [ // Base entity fields - table name as key
         *         'table_name' => [
         *             'fieldName' => [
         *                 'type' => (string) Data type ('string', 'integer', 'boolean', 'enum', 'date', 'datetime'),
         *                 'column' => (string) The database column name,
         *                 'nullable' => (bool) Whether the field can be null (optional, defaults to false),
         *                 'validation' => (array) For 'enum' type, list of allowed values (optional),
         *             ],
         *             // ... more fields in this table
         *         ],
         *         // ... more tables
         *     ],
         *     'lang' => [ // Localized fields - table name as key
         *         'table_name' => [
         *             '_jsonKey' => (string) JSON property name in API (e.g., 'attributeGroupLangExtra').
         *                               If not provided, table name will be used.
         *             'idLang' => ['type' => 'int', 'column' => 'id_lang'], // REQUIRED: junction key
         *             'fieldName' => [
         *                 'type' => (string) Data type ('string', 'integer', 'boolean'),
         *                 'column' => (string) The database column name,
         *                 'nullable' => (bool) Whether the field can be null (optional, defaults to false),
         *             ],
         *             // ... more fields in this table
         *         ],
         *         // ... more tables
         *     ],
         *     'shop' => [ // Shop-specific fields - table name as key
         *         'table_name' => [
         *             '_jsonKey' => (string) JSON property name in API (e.g., 'attributeGroupShopExtra').
         *                               If not provided, table name will be used.
         *             'idShop' => ['type' => 'int', 'column' => 'id_shop'], // REQUIRED: junction key
         *             'fieldName' => [
         *                 'type' => (string) Data type ('string', 'integer', 'boolean'),
         *                 'column' => (string) The database column name,
         *                 'nullable' => (bool) Whether the field can be null (optional, defaults to false),
         *             ],
         *             // ... more fields in this table
         *         ],
         *         // ... more tables
         *     ],
         * ]
         *
         * JSON Format:
         * - Base fields: Direct properties in the JSON object (e.g., "stringField": "value")
         * - Lang fields: Object with field names as keys, locales as nested keys (e.g., "attributeGroupLangExtra": {"stringLangField": {"fr-FR": "value", "en-GB": "value"}})
         * - Shop fields: Object with field names as keys, shop IDs as nested keys (e.g., "attributeGroupShopExtra": {"intShopField": {"1": 100, "2": 200}})
         *
         * To add or modify fields, update the 'customFields' array as needed.
         * IMPORTANT: Since this is a chained hook, you MUST return the modified 'customFields' array at the end of your hook implementation.
         */
        $hookResult = \Hook::exec(
            'addApiResourcesCustomFields',
            [
                'entity' => $entityName,
                'customFields' => &$customFields,
            ],
            null,
            false,
            true,
            false,
            null,
            true
        );

        // If hook returns an array, use it (chained hook)
        if (is_array($hookResult)) {
            $customFields = $hookResult;
        }

        $this->metadataCache[$entityName] = $customFields;

        return $customFields;
    }

    /**
     * Check if an entity has custom fields
     *
     * @param string $entityName Entity name
     *
     * @return bool
     */
    public function hasCustomFields(string $entityName): bool
    {
        $metadata = $this->getCustomFieldsMetadata($entityName);

        return !empty($metadata['fields']) || !empty($metadata['lang']) || !empty($metadata['shop']);
    }

    /**
     * Get entity ID column name
     *
     * @param string $entityName Entity name
     *
     * @return string|null
     */
    public function getEntityIdColumn(string $entityName): ?string
    {
        $metadata = $this->getCustomFieldsMetadata($entityName);

        return $metadata['entity']['idColumn'] ?? null;
    }

    /**
     * Clear metadata cache
     */
    public function clearCache(): void
    {
        $this->metadataCache = [];
    }
}
