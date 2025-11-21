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

namespace PrestaShop\Module\APIResources\Serializer;

use PrestaShop\Module\APIResources\CustomFields\CustomFieldsMetadataProvider;
use PrestaShopBundle\ApiPlatform\ContextParametersProvider;
use PrestaShopBundle\ApiPlatform\LocalizedValueUpdater;
use PrestaShopBundle\ApiPlatform\NormalizationMapper;
use PrestaShopBundle\ApiPlatform\Serializer\CQRSApiSerializer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * Extends CQRSApiSerializer to add automatic type casting for query parameters.
 */
class QueryParameterTypeCastSerializer extends CQRSApiSerializer
{
    /**
     * Request attribute key to store extracted custom fields
     */
    public const CUSTOM_FIELDS_ATTRIBUTE_KEY = '_custom_fields_data';

    public function __construct(
        Serializer $decorated,
        ContextParametersProvider $contextParametersProvider,
        ClassMetadataFactoryInterface $classMetadataFactory,
        LocalizedValueUpdater $localizedValueUpdater,
        NormalizationMapper $normalizationMapper,
        private readonly CustomFieldsMetadataProvider $customFieldsMetadataProvider,
        private readonly RequestStack $requestStack,
    ) {
        parent::__construct($decorated, $contextParametersProvider, $classMetadataFactory, $localizedValueUpdater, $normalizationMapper);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        // Extract custom fields before denormalization
        // The parent::denormalize() will handle locale -> ID conversion for native fields (names, publicNames, etc.)
        if (is_array($data)) {
            $entityName = $this->getEntityNameFromClass($type);
            if ($entityName && $this->customFieldsMetadataProvider->hasCustomFields($entityName)) {
                $metadata = $this->customFieldsMetadataProvider->getCustomFieldsMetadata($entityName);
                $customFieldsData = $this->extractCustomFields($data, $metadata);
                $cleanData = $this->removeCustomFields($data, $metadata);

                // Store custom fields in request attributes for later persistence
                // Only set if not already set (to avoid overwriting with empty array from query denormalization)
                $request = $this->requestStack->getCurrentRequest();
                if ($request) {
                    $existingData = $request->attributes->get(self::CUSTOM_FIELDS_ATTRIBUTE_KEY);
                    if (empty($existingData) && !empty($customFieldsData)) {
                        $request->attributes->set(self::CUSTOM_FIELDS_ATTRIBUTE_KEY, $customFieldsData);
                    }
                }

                $data = $cleanData;
            }
        }

        if (is_array($data) && str_starts_with($type, 'PrestaShop\\PrestaShop\\Core\\Domain\\')) {
            $data = $this->castQueryParametersToExpectedTypes($data, $type);
        }

        return parent::denormalize($data, $type, $format, $context);
    }

    /**
     * Cast string query parameter values to their expected types based on the CQRS query constructor.
     */
    private function castQueryParametersToExpectedTypes(array $data, string $queryClass): array
    {
        try {
            $reflection = new \ReflectionClass($queryClass);
            $constructor = $reflection->getConstructor();

            if (!$constructor) {
                return $data;
            }

            foreach ($constructor->getParameters() as $parameter) {
                $paramName = $parameter->getName();

                if (!array_key_exists($paramName, $data) || !is_string($data[$paramName])) {
                    continue;
                }

                $type = $parameter->getType();

                if (!$type instanceof \ReflectionNamedType || !$type->isBuiltin()) {
                    continue;
                }

                $data[$paramName] = match ($type->getName()) {
                    'int' => (int) $data[$paramName],
                    'float' => (float) $data[$paramName],
                    'bool' => filter_var($data[$paramName], FILTER_VALIDATE_BOOLEAN),
                    default => $data[$paramName],
                };
            }
        } catch (\ReflectionException $e) {
            return $data;
        }

        return $data;
    }

    /**
     * Extract custom fields from input data
     *
     * @param array $data Input data
     * @param array $metadata Custom fields metadata
     *
     * @return array Extracted custom fields
     */
    private function extractCustomFields(array $data, array $metadata): array
    {
        $customFields = [];

        // Extract base fields
        if (!empty($metadata['fields'])) {
            foreach ($metadata['fields'] as $tableName => $fields) {
                foreach ($fields as $fieldName => $fieldMetadata) {
                    if (isset($data[$fieldName])) {
                        $customFields[$fieldName] = $data[$fieldName];
                    }
                }
            }
        }

        // Extract lang fields
        if (!empty($metadata['lang'])) {
            foreach ($metadata['lang'] as $tableName => $fields) {
                $jsonKey = $fields['_jsonKey'] ?? $tableName;
                if (isset($data[$jsonKey]) && is_array($data[$jsonKey])) {
                    // Convert locale-based format to idLang-based format if needed
                    $customFields[$jsonKey] = $this->convertLangFieldsFromLocales($data[$jsonKey], $fields);
                }
            }
        }

        // Extract shop fields
        if (!empty($metadata['shop'])) {
            foreach ($metadata['shop'] as $tableName => $fields) {
                $jsonKey = $fields['_jsonKey'] ?? $tableName;
                if (isset($data[$jsonKey]) && is_array($data[$jsonKey])) {
                    // Convert shop ID-based format to idShop-based format if needed
                    $customFields[$jsonKey] = $this->convertShopFieldsFromIds($data[$jsonKey], $fields);
                }
            }
        }

        return $customFields;
    }

    /**
     * Remove custom fields from input data
     *
     * @param array $data Input data
     * @param array $metadata Custom fields metadata
     *
     * @return array Clean data without custom fields
     */
    private function removeCustomFields(array $data, array $metadata): array
    {
        $cleanData = $data;

        // Remove base fields
        if (!empty($metadata['fields'])) {
            foreach ($metadata['fields'] as $tableName => $fields) {
                foreach ($fields as $fieldName => $fieldMetadata) {
                    unset($cleanData[$fieldName]);
                }
            }
        }

        // Remove lang fields
        if (!empty($metadata['lang'])) {
            foreach ($metadata['lang'] as $tableName => $fields) {
                $jsonKey = $fields['_jsonKey'] ?? $tableName;
                unset($cleanData[$jsonKey]);
            }
        }

        // Remove shop fields
        if (!empty($metadata['shop'])) {
            foreach ($metadata['shop'] as $tableName => $fields) {
                $jsonKey = $fields['_jsonKey'] ?? $tableName;
                unset($cleanData[$jsonKey]);
            }
        }

        return $cleanData;
    }

    /**
     * Convert lang fields from locale-based format to idLang-based format
     *
     * Input format (with locales):
     * {
     *   "stringLangField": {
     *     "fr-FR": "value1",
     *     "en-GB": "value2"
     *   }
     * }
     *
     * Output format (with idLang):
     * [
     *   {"idLang": 1, "stringLangField": "value1"},
     *   {"idLang": 2, "stringLangField": "value2"}
     * ]
     *
     * @param array $langData Lang data (either locale-based or idLang-based)
     * @param array $fields Fields metadata
     *
     * @return array Converted lang data with idLang
     */
    private function convertLangFieldsFromLocales(array $langData, array $fields): array
    {
        // Check if it's already in idLang format (array of objects with idLang key)
        if (!empty($langData) && isset($langData[0]) && is_array($langData[0]) && isset($langData[0]['idLang'])) {
            return $langData;
        }

        // It's in locale-based format, convert it
        $result = [];
        $localesToIds = [];

        // First pass: collect all locales and convert them to IDs
        foreach ($langData as $fieldName => $fieldValues) {
            if ($fieldName === 'idLang' || $fieldName === '_jsonKey') {
                continue;
            }
            if (is_array($fieldValues)) {
                foreach ($fieldValues as $locale => $value) {
                    if (!isset($localesToIds[$locale])) {
                        $idLang = \Language::getIdByLocale($locale);
                        if ($idLang) {
                            $localesToIds[$locale] = (int) $idLang;
                        }
                    }
                }
            }
        }

        // Second pass: build the result array with idLang
        foreach ($localesToIds as $locale => $idLang) {
            $langRow = ['idLang' => $idLang];
            foreach ($langData as $fieldName => $fieldValues) {
                if ($fieldName === 'idLang' || $fieldName === '_jsonKey') {
                    continue;
                }
                if (is_array($fieldValues) && isset($fieldValues[$locale])) {
                    $langRow[$fieldName] = $fieldValues[$locale];
                }
            }
            if (count($langRow) > 1) { // At least idLang + one field
                $result[] = $langRow;
            }
        }

        return $result;
    }

    /**
     * Convert shop fields from shop ID-based format to idShop-based format
     *
     * Input format (with shop IDs as keys):
     * {
     *   "intShopField": {
     *     "1": 100,
     *     "2": 200
     *   }
     * }
     *
     * Output format (with idShop):
     * [
     *   {"idShop": 1, "intShopField": 100},
     *   {"idShop": 2, "intShopField": 200}
     * ]
     *
     * @param array $shopData Shop data (either ID-based or idShop-based)
     * @param array $fields Fields metadata
     *
     * @return array Converted shop data with idShop
     */
    private function convertShopFieldsFromIds(array $shopData, array $fields): array
    {
        // Check if it's already in idShop format (array of objects with idShop key)
        if (!empty($shopData) && isset($shopData[0]) && is_array($shopData[0]) && isset($shopData[0]['idShop'])) {
            return $shopData;
        }

        // It's in shop ID-based format, convert it
        $result = [];
        $shopIds = [];

        // First pass: collect all shop IDs
        foreach ($shopData as $fieldName => $fieldValues) {
            if ($fieldName === 'idShop' || $fieldName === '_jsonKey') {
                continue;
            }
            if (is_array($fieldValues)) {
                foreach ($fieldValues as $shopId => $value) {
                    $shopId = (int) $shopId;
                    if ($shopId > 0 && !in_array($shopId, $shopIds, true)) {
                        $shopIds[] = $shopId;
                    }
                }
            }
        }

        // Second pass: build the result array with idShop
        foreach ($shopIds as $shopId) {
            $shopRow = ['idShop' => $shopId];
            foreach ($shopData as $fieldName => $fieldValues) {
                if ($fieldName === 'idShop' || $fieldName === '_jsonKey') {
                    continue;
                }
                if (is_array($fieldValues)) {
                    // Shop IDs in JSON are strings, but we can access them as int
                    $shopIdKey = (string) $shopId;
                    if (isset($fieldValues[$shopIdKey])) {
                        $shopRow[$fieldName] = $fieldValues[$shopIdKey];
                    } elseif (isset($fieldValues[$shopId])) {
                        $shopRow[$fieldName] = $fieldValues[$shopId];
                    }
                }
            }
            if (count($shopRow) > 1) { // At least idShop + one field
                $result[] = $shopRow;
            }
        }

        return $result;
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
}
