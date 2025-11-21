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
use PrestaShop\Module\APIResources\Serializer\QueryParameterTypeCastSerializer;
use PrestaShopBundle\ApiPlatform\Processor\CommandProcessor;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorator for CommandProcessor to persist custom fields
 */
class CustomFieldsCommandProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandProcessor $decorated,
        private readonly CustomFieldsPersistenceService $persistenceService,
        private readonly CustomFieldsMetadataProvider $metadataProvider,
        private readonly RequestStack $requestStack,
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

        // Persist custom fields
        $this->persistenceService->persistCustomFields($entityName, (int) $entityId, $customFieldsData);

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
}
