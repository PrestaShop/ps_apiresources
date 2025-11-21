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
use PrestaShop\Module\APIResources\CustomFields\CustomFieldsPersistenceService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Generic processor to load/persist custom fields for GET/POST/PATCH operations
 */
class CustomFieldsProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CustomFieldsMetadataProvider $metadataProvider,
        private readonly CustomFieldsPersistenceService $persistenceService,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!is_object($data)) {
            return $data;
        }

        $entityName = $this->getEntityNameFromClass(get_class($data));
        if (!$entityName || !$this->metadataProvider->hasCustomFields($entityName)) {
            return $data;
        }

        $operationName = $operation->getName();

        // Handle GET operation - load custom fields
        if ($operationName === 'get') {
            $entityId = $this->extractEntityIdFromUriVariables($uriVariables, $entityName);
            if ($entityId) {
                $this->loadCustomFieldsIntoObject($data, $entityName, $entityId);
            }
        }

        // Handle POST/PATCH operations - persist and reload custom fields
        if (in_array($operationName, ['post', 'patch'], true)) {
            // Custom fields are persisted by CustomFieldsCommandProcessor
            // Here we just need to reload them into the object for the response
            $entityId = $this->extractEntityIdFromObject($data, $entityName, $uriVariables);
            if ($entityId) {
                $this->loadCustomFieldsIntoObject($data, $entityName, $entityId);
            }
        }

        return $data;
    }

    /**
     * Load custom fields from database into the object
     *
     * @param object $object Object to populate
     * @param string $entityName Entity name
     * @param int $entityId Entity ID
     */
    private function loadCustomFieldsIntoObject(object $object, string $entityName, int $entityId): void
    {
        $customFields = $this->persistenceService->loadCustomFields($entityName, $entityId);

        // Inject custom fields as public properties on the object
        foreach ($customFields as $fieldName => $value) {
            if (property_exists($object, $fieldName)) {
                $object->$fieldName = $value;
            } else {
                // Use reflection to set property if it doesn't exist
                try {
                    $reflection = new \ReflectionClass($object);
                    $property = $reflection->getProperty($fieldName);
                    $property->setValue($object, $value);
                } catch (\ReflectionException $e) {
                    // Property doesn't exist, create it dynamically
                    $object->$fieldName = $value;
                }
            }
        }
    }

    /**
     * Extract entity ID from URI variables
     *
     * @param array $uriVariables URI variables
     * @param string $entityName Entity name
     *
     * @return int|null
     */
    private function extractEntityIdFromUriVariables(array $uriVariables, string $entityName): ?int
    {
        $idColumn = $this->metadataProvider->getEntityIdColumn($entityName);
        if (!$idColumn) {
            return null;
        }

        // Try common patterns
        $patterns = [
            $this->camelCase($idColumn),
            lcfirst(str_replace('_', '', ucwords($idColumn, '_'))),
            $idColumn,
        ];

        foreach ($patterns as $pattern) {
            if (isset($uriVariables[$pattern]) && is_int($uriVariables[$pattern])) {
                return (int) $uriVariables[$pattern];
            }
        }

        return null;
    }

    /**
     * Extract entity ID from object or URI variables
     *
     * @param object $object Object
     * @param string $entityName Entity name
     * @param array $uriVariables URI variables
     *
     * @return int|null
     */
    private function extractEntityIdFromObject(object $object, string $entityName, array $uriVariables): ?int
    {
        // Try URI variables first (for PATCH)
        $idFromUri = $this->extractEntityIdFromUriVariables($uriVariables, $entityName);
        if ($idFromUri) {
            return $idFromUri;
        }

        // Try object properties
        $idColumn = $this->metadataProvider->getEntityIdColumn($entityName);
        if ($idColumn) {
            $patterns = [
                $this->camelCase($idColumn),
                lcfirst(str_replace('_', '', ucwords($idColumn, '_'))),
                'id',
            ];

            foreach ($patterns as $pattern) {
                if (property_exists($object, $pattern)) {
                    $id = $object->$pattern;
                    if (is_int($id)) {
                        return $id;
                    }
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
        $parts = explode('\\', $className);
        $shortName = end($parts);

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
        $string = preg_replace('/^id_/', '', $string);
        $parts = explode('_', $string);
        $first = array_shift($parts);
        $parts = array_map('ucfirst', $parts);

        return $first . implode('', $parts);
    }
}
