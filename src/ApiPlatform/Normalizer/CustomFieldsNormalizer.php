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

namespace PrestaShop\Module\APIResources\ApiPlatform\Normalizer;

use PrestaShop\Module\APIResources\CustomFields\CustomFieldsMetadataProvider;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * Normalizer to inject custom fields into serialized output
 */
class CustomFieldsNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly ObjectNormalizer $objectNormalizer,
        private readonly CustomFieldsMetadataProvider $metadataProvider,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        // First normalize with the standard normalizer
        $normalized = $this->objectNormalizer->normalize($object, $format, $context);

        if (!is_array($normalized)) {
            return $normalized;
        }

        // Get entity name from the object class
        $entityName = $this->getEntityNameFromClass(get_class($object));

        if (!$entityName || !$this->metadataProvider->hasCustomFields($entityName)) {
            return $normalized;
        }

        // Try to get ID from object first (more reliable)
        $entityId = $this->extractEntityIdFromObject($object, $entityName);

        // Fallback: try to find the ID in the normalized data
        if (!$entityId) {
            $idColumn = $this->metadataProvider->getEntityIdColumn($entityName);
            if ($idColumn) {
                $entityId = $this->findEntityId($normalized, $idColumn);
            }
        }

        if (!$entityId) {
            return $normalized;
        }

        // Load custom fields via hook
        $customFields = $this->loadCustomFields($entityName, (int) $entityId);

        // Merge custom fields into normalized data
        return array_merge($normalized, $customFields);
    }

    /**
     * Extract entity ID from object
     *
     * @param object $object Object
     * @param string $entityName Entity name
     *
     * @return int|null
     */
    private function extractEntityIdFromObject(object $object, string $entityName): ?int
    {
        $idColumn = $this->metadataProvider->getEntityIdColumn($entityName);
        if (!$idColumn) {
            return null;
        }

        $patterns = [
            $this->camelCase($idColumn),
            lcfirst(str_replace('_', '', ucwords($idColumn, '_'))),
            'id',
        ];

        foreach ($patterns as $pattern) {
            if (property_exists($object, $pattern)) {
                $id = $object->$pattern;
                if (is_int($id) && $id > 0) {
                    return $id;
                }
            }
        }

        // Try reflection for uninitialized properties
        try {
            $reflection = new \ReflectionClass($object);
            foreach ($patterns as $pattern) {
                if ($reflection->hasProperty($pattern)) {
                    $property = $reflection->getProperty($pattern);
                    $id = $property->getValue($object);
                    if (is_int($id) && $id > 0) {
                        return $id;
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // Ignore reflection errors
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        if (!is_object($data)) {
            return false;
        }

        $entityName = $this->getEntityNameFromClass(get_class($data));

        return $entityName !== null && $this->metadataProvider->hasCustomFields($entityName);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            'object' => false,
        ];
    }

    /**
     * Get entity name from class name
     *
     * @param string $className Full class name
     *
     * @return string|null
     */
    private function getEntityNameFromClass(string $className): ?string
    {
        // Extract class name from namespace
        // e.g., PrestaShop\Module\APIResources\ApiPlatform\Resources\Attribute\AttributeGroup
        // -> AttributeGroup
        $parts = explode('\\', $className);
        $shortName = end($parts);

        // Check if it's an API Resource class
        if (strpos($className, 'PrestaShop\\Module\\APIResources\\ApiPlatform\\Resources\\') === 0) {
            return $shortName;
        }

        return null;
    }

    /**
     * Find entity ID in normalized data
     *
     * @param array $normalized Normalized data
     * @param string $idColumn ID column name (e.g., 'id_attribute_group')
     *
     * @return int|null
     */
    private function findEntityId(array $normalized, string $idColumn): ?int
    {
        // Try direct match first (e.g., id_attribute_group)
        if (isset($normalized[$idColumn])) {
            $id = $normalized[$idColumn];
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                return (int) $id;
            }
        }

        // Try common patterns (e.g., attributeGroupId, customerId)
        $patterns = [
            // Convert id_attribute_group to attributeGroupId
            $this->camelCase($idColumn) . 'Id',
            // Convert id_attribute_group to attributeGroupId (alternative)
            $this->camelCase($idColumn),
            // Convert id_attribute_group to idAttributeGroup
            lcfirst(str_replace('_', '', ucwords($idColumn, '_'))),
        ];

        foreach ($patterns as $pattern) {
            if (isset($normalized[$pattern])) {
                $id = $normalized[$pattern];
                if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                    return (int) $id;
                }
            }
        }

        // Try to find any property ending with 'Id' that might be the entity ID
        foreach ($normalized as $key => $value) {
            if ((str_ends_with($key, 'Id') || str_ends_with($key, 'ID')) && (is_int($value) || (is_string($value) && ctype_digit($value)))) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * Convert snake_case to camelCase
     *
     * @param string $string String to convert
     *
     * @return string
     */
    private function camelCase(string $string): string
    {
        // Remove 'id_' prefix if present
        $string = preg_replace('/^id_/', '', $string);

        // Convert snake_case to camelCase
        $parts = explode('_', $string);
        $first = array_shift($parts);
        $parts = array_map('ucfirst', $parts);

        return $first . implode('', $parts);
    }

    /**
     * Load custom fields via hook
     *
     * This hook is called to load custom fields data for an entity.
     * Modules implementing this hook should return an array of custom fields data
     * that will be merged into the API response.
     *
     * @param string $entityName Entity name (e.g., 'AttributeGroup')
     * @param int $entityId Entity ID
     *
     * @return array Custom fields data to merge into the response
     */
    private function loadCustomFields(string $entityName, int $entityId): array
    {
        $customFields = [];

        /**
         * HOOK: loadApiResourcesCustomFields
         *
         * Parameters provided to the hook:
         * - 'entity' (string): The name of the entity (e.g., 'AttributeGroup', 'Product')
         * - 'entityId' (int): The ID of the entity
         * - 'customFields' (array): The current array of custom fields data (initially empty)
         *
         * Modules should populate the 'customFields' array with the custom fields data
         * for the given entity. The structure should match the JSON format expected in API responses:
         * - Base fields: Direct properties (e.g., "stringField": "value")
         * - Lang fields: Object with field names as keys, locales as nested keys
         *   (e.g., "attributeGroupLangExtra": {"stringLangField": {"fr-FR": "value", "en-GB": "value"}})
         * - Shop fields: Object with field names as keys, shop IDs as nested keys
         *   (e.g., "attributeGroupShopExtra": {"intShopField": {"1": 100, "2": 200}})
         *
         * This is a chained hook, so modules should return the modified 'customFields' array.
         */
        $hookResult = \Hook::exec(
            'loadApiResourcesCustomFields',
            [
                'entity' => $entityName,
                'entityId' => $entityId,
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

        return $customFields;
    }
}
