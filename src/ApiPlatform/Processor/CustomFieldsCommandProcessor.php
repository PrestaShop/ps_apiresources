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

namespace PrestaShop\Module\APIResources\ApiPlatform\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use PrestaShop\Module\APIResources\CustomFields\CustomFieldsMetadataProvider;
use PrestaShop\Module\APIResources\Serializer\QueryParameterTypeCastSerializer;
use PrestaShopBundle\ApiPlatform\Processor\CommandProcessor;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Decorator for CommandProcessor to persist custom fields
 */
class CustomFieldsCommandProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandProcessor $decorated,
        private readonly CustomFieldsMetadataProvider $metadataProvider,
        private readonly RequestStack $requestStack,
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Process the command first
        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        // Extract custom fields from request attributes (set by QueryParameterTypeCastSerializer)
        $request = $this->requestStack->getCurrentRequest();
        $customFieldsData = $request?->attributes->get(QueryParameterTypeCastSerializer::CUSTOM_FIELDS_ATTRIBUTE_KEY);

        if (empty($customFieldsData)) {
            return $result;
        }

        // Get entity name from operation
        $entityName = $this->getEntityNameFromClass($operation->getClass());

        if (!$entityName || !$this->metadataProvider->hasCustomFields($entityName)) {
            return $result;
        }

        // Get entity ID from result or URI variables
        $entityId = $this->extractEntityId($result, $uriVariables, $entityName);

        if (!$entityId || $entityId <= 0) {
            return $result;
        }

        // Get normalized entity data (base fields) to pass to the hook
        $entityData = $this->getNormalizedEntityData($result, $entityName);

        // Persist custom fields via hook
        $this->persistCustomFieldsViaHook($entityName, (int) $entityId, $customFieldsData, $entityData);

        return $result;
    }

    /**
     * Extract entity ID from result or URI variables
     *
     * @param mixed $result Command result
     * @param array $uriVariables URI variables
     * @param string $entityName Entity name
     *
     * @return int|null
     */
    private function extractEntityId(mixed $result, array $uriVariables, string $entityName): ?int
    {
        // Try to get ID from URI variables first (for updates)
        $idColumn = $this->metadataProvider->getEntityIdColumn($entityName);
        if ($idColumn) {
            // Try common patterns
            $patterns = [
                $this->camelCase($idColumn),
                lcfirst(str_replace('_', '', ucwords($idColumn, '_'))),
                $idColumn,
            ];

            foreach ($patterns as $pattern) {
                if (isset($uriVariables[$pattern])) {
                    return (int) $uriVariables[$pattern];
                }
            }
        }

        // Try to get ID from result object
        if (is_object($result)) {
            // Try common property names
            $propertyNames = [
                $this->camelCase($idColumn ?? 'id'),
                lcfirst(str_replace('_', '', ucwords($entityName, '_'))) . 'Id',
                'id',
            ];

            foreach ($propertyNames as $propertyName) {
                if (property_exists($result, $propertyName)) {
                    $id = $result->$propertyName;
                    if (is_int($id) && $id > 0) {
                        return $id;
                    }
                }
            }

            // Try to access via reflection if property exists but is not initialized
            try {
                $reflection = new \ReflectionClass($result);
                foreach ($propertyNames as $propertyName) {
                    if ($reflection->hasProperty($propertyName)) {
                        $property = $reflection->getProperty($propertyName);
                        $id = $property->getValue($result);
                        if (is_int($id) && $id > 0) {
                            return $id;
                        }
                    }
                }
            } catch (\ReflectionException $e) {
                // Ignore reflection errors
            }
        }

        // Try to get ID from result array
        if (is_array($result)) {
            $propertyNames = [
                $this->camelCase($idColumn ?? 'id'),
                lcfirst(str_replace('_', '', ucwords($entityName, '_'))) . 'Id',
                'id',
            ];

            foreach ($propertyNames as $propertyName) {
                if (isset($result[$propertyName]) && is_int($result[$propertyName])) {
                    return (int) $result[$propertyName];
                }
            }
        }

        return null;
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
        $parts = explode('\\', $className);
        $shortName = end($parts);

        // Check if it's an API Resource class
        if (strpos($className, 'PrestaShop\\Module\\APIResources\\ApiPlatform\\Resources\\') === 0) {
            return $shortName;
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
     * Get normalized entity data
     *
     * @param mixed $result Command result
     * @param string $entityName Entity name
     *
     * @return array Normalized entity data (base fields only, without custom fields)
     */
    private function getNormalizedEntityData(mixed $result, string $entityName): array
    {
        if (!is_object($result)) {
            // If result is already an array, return it as is
            if (is_array($result)) {
                return $result;
            }

            return [];
        }

        try {
            // Normalize the entity to get all its base data
            $normalized = $this->normalizer->normalize($result, 'json');
            if (is_array($normalized)) {
                return $normalized;
            }
        } catch (\Exception $e) {
            // If normalization fails, try to extract data manually
            return $this->extractEntityDataFromObject($result);
        }

        return [];
    }

    /**
     * Extract entity data from object using reflection
     *
     * @param object $object Object to extract data from
     *
     * @return array Extracted data
     */
    private function extractEntityDataFromObject(object $object): array
    {
        $data = [];

        try {
            $reflection = new \ReflectionClass($object);
            foreach ($reflection->getProperties() as $property) {
                $value = $property->getValue($object);
                $data[$property->getName()] = $value;
            }
        } catch (\ReflectionException $e) {
            // Ignore reflection errors
        }

        return $data;
    }

    /**
     * Persist custom fields via hook
     *
     * This hook is called after an entity has been created or updated.
     * Modules implementing this hook should persist the custom fields data
     * to their own database tables.
     *
     * @param string $entityName Entity name (e.g., 'AttributeGroup')
     * @param int $entityId Entity ID
     * @param array $customFieldsData Custom fields data extracted from the request
     * @param array $entityData Normalized entity data (base fields only, without custom fields)
     *
     * @return void
     */
    private function persistCustomFieldsViaHook(string $entityName, int $entityId, array $customFieldsData, array $entityData): void
    {
        /*
         * HOOK: persistApiResourcesCustomFields
         *
         * Parameters provided to the hook:
         * - 'entity' (string): The name of the entity (e.g., 'AttributeGroup', 'Product')
         * - 'entityId' (int): The ID of the entity (after creation/update)
         * - 'customFieldsData' (array): The custom fields data extracted from the request
         * - 'entityData' (array): The normalized entity data (base fields only, without custom fields)
         *                          This contains all the native entity properties that were just persisted.
         *                          Useful for implementing business logic that depends on entity state.
         *
         * The 'customFieldsData' array structure matches the JSON format from the API request:
         * - Base fields: Direct properties (e.g., "stringField": "value")
         * - Lang fields: Object with field names as keys, locales as nested keys
         *   (e.g., "attributeGroupLangExtra": {"stringLangField": {"fr-FR": "value", "en-GB": "value"}})
         * - Shop fields: Object with field names as keys, shop IDs as nested keys
         *   (e.g., "attributeGroupShopExtra": {"intShopField": {"1": 100, "2": 200}})
         *
         * The 'entityData' array contains the normalized base entity data (e.g., for AttributeGroup:
         * names, publicNames, type, shopIds, position, etc.). This allows modules to implement
         * conditional logic based on the entity's native properties.
         *
         * Modules should persist this data to their own database tables.
         * This hook does not return a value (void hook).
         */
        \Hook::exec(
            'persistApiResourcesCustomFields',
            [
                'entity' => $entityName,
                'entityId' => $entityId,
                'customFieldsData' => $customFieldsData,
                'entityData' => $entityData,
            ]
        );
    }
}
