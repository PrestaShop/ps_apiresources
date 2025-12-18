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

namespace PsApiResourcesTest\Rector;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Validates and fixes API Platform resource URI templates to use correct plural forms.
 *
 * This rule ensures URI templates follow a consistent pluralization pattern based on the resource's namespace.
 *
 * Rules applied:
 * 1. **First segment**: Must be the pluralized form of the namespace domain (converted to kebab-case)
 *    - Namespace "Product" → first segment must be "products"
 *    - Namespace "TaxRulesGroup" → first segment must be "tax-rules-groups"
 *
 * 2. **Namespace to URI conversion**: PascalCase namespace is converted to kebab-case
 *    - Uses Doctrine Inflector's tableize() method + underscore-to-dash conversion
 *    - "TaxRulesGroup" → "tax-rules-group" → "tax-rules-groups"
 *    - "WebserviceKey" → "webservice-key" → "webservice-keys"
 *
 * 3. **Compound words**: Only the last word in dash-separated segments is pluralized
 *    - "tax-rules-group" → "tax-rules-groups" (only "group" → "groups")
 *    - "webservice-key" → "webservice-keys" (only "key" → "keys")
 *    - "feature-value" → "feature-values" (only "value" → "values")
 *
 * 4. **Other segments**: Pluralized individually
 *    - "value" → "values"
 *    - "group" → "groups"
 *
 * 5. **Skips ID placeholders**: Segments in curly braces are skipped
 *    - {productId}, {categoryId}, {id} are not modified
 *
 * 6. **Skips operation keywords**: Predefined operations from SKIPPED_KEYWORDS remain unchanged
 *    - batch, status, toggle-status, upload, reset, image, cover, logo, delete, etc.
 *
 * 7. **Skips bulk operations**: Any segment starting with "bulk-" is preserved as-is
 *    - "bulk-delete", "bulk-toggle-status", "bulk-update" remain unchanged
 *
 * 8. **Validates bulk operations**: When CQRSCommand parameter contains "Bulk"
 *    - The last non-placeholder segment must start with "bulk-"
 *    - If missing, "bulk-" is automatically prefixed to the segment
 *    - Example: BulkDeleteProductCommand → last segment must be "bulk-delete"
 *    - "/products/delete" with BulkDeleteProductCommand → "/products/bulk-delete"
 *
 * 9. **Uses Doctrine Inflector**: Accurate pluralization including irregular forms
 *    - "category" → "categories"
 *    - "address" → "addresses"
 *    - "person" → "people"
 *
 * Examples:
 * - "/product/{productId}" → "/products/{productId}"
 * - "/product/{productId}/image" → "/products/{productId}/images"
 * - "/features/value/{id}" → "/features/values/{id}"
 * - "/category/{id}/status" → "/categories/{id}/status"
 * - "/tax-rules-group/{id}" → "/tax-rules-groups/{id}"
 * - "/products/batch" → "/products/batch" (no change)
 * - "/webservice-key" → "/webservice-keys"
 * - "/products/delete" with BulkDeleteProductCommand → "/products/bulk-delete"
 * - "/categories/bulk-toggle-status" with BulkUpdateCategoryStatusCommand → "/categories/bulk-toggle-status" (no change)
 */
final class ApiResourceUriTemplateRector extends AbstractRector
{
    private readonly Inflector $inflector;

    /**
     * Skipped keywords that should not be pluralized, usually refer to some actions, or
     * specific elements that are unique (like the cover)
     */
    private const SKIPPED_KEYWORDS = [
        'batch',
        'status',
        'toggle-status',
        'set-status',
        'upload',
        'reset',
        'upgrade',
        'uninstall',
        'install',
        'delete',
        'cover',
        'disable',
        'enable',
        'search',
        'upload-archive',
        'upload-source',
        'thumbnail',
        'logo',
        'duplicate',
    ];

    public function __construct()
    {
        $this->inflector = InflectorFactory::create()->build();
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Validates that API Platform resource URI templates use the correct plural prefix based on namespace',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
namespace App\ApiPlatform\Resources\Contact;

#[ApiResource(
    operations: [
        new CQRSGet(uriTemplate: '/contact/{contactId}'),
    ]
)]
class Contact {}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
namespace App\ApiPlatform\Resources\Contact;

#[ApiResource(
    operations: [
        new CQRSGet(uriTemplate: '/contacts/{contactId}'),
    ]
)]
class Contact {}
CODE_SAMPLE
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        // Only process classes in the ApiPlatform/Resources directory
        $namespace = $this->getName($node->namespacedName);
        if (!$namespace || !str_contains($namespace, 'ApiPlatform\\Resources\\')) {
            return null;
        }

        // Extract the domain from the namespace
        $domain = $this->extractDomain($namespace);
        if (!$domain) {
            return null;
        }

        // Find the ApiResource attribute
        $apiResourceAttr = $this->findApiResourceAttribute($node);
        if (!$apiResourceAttr) {
            return null;
        }

        // Fix all operations with incorrect URI templates
        $hasChanges = $this->fixOperations($apiResourceAttr, $domain);

        // Return the node if we made changes, null otherwise
        return $hasChanges ? $node : null;
    }

    private function extractDomain(string $namespace): ?string
    {
        // Extract domain from namespace like "PrestaShop\Module\APIResources\ApiPlatform\Resources\Contact\Contact"
        // We want the part after "Resources\" and before the class name
        // Keep the original case (e.g., "TaxRulesGroup") to properly convert to kebab-case later
        if (!preg_match('/\\\\Resources\\\\(\w+)\\\\/', $namespace, $matches)) {
            // Handle cases where class is directly in Resources (like CartRule.php)
            if (preg_match('/\\\\Resources\\\\(\w+)$/', $namespace, $matches)) {
                return $matches[1];
            }

            return null;
        }

        return $matches[1];
    }

    private function pluralize(string $word): string
    {
        $lowerWord = strtolower($word);

        // Handle compound words with dashes (e.g., tax-rules-group, webservice-key)
        // Only the last part should be pluralized
        if (str_contains($lowerWord, '-')) {
            $parts = explode('-', $lowerWord);
            $lastIndex = count($parts) - 1;
            $parts[$lastIndex] = $this->inflector->pluralize($parts[$lastIndex]);

            return implode('-', $parts);
        }

        // Use Doctrine Inflector to pluralize simple words
        return $this->inflector->pluralize($lowerWord);
    }

    /**
     * Convert a word to kebab-case format using Doctrine Inflector
     * Examples: "TaxRulesGroup" -> "tax-rules-group", "WebserviceKey" -> "webservice-key"
     */
    private function toKebabCase(string $word): string
    {
        // tableize() converts to snake_case (e.g., "TaxRulesGroup" -> "tax_rules_group")
        // Then replace underscores with dashes to get kebab-case
        return str_replace('_', '-', $this->inflector->tableize($word));
    }

    private function findApiResourceAttribute(Class_ $class): ?Attribute
    {
        foreach ($class->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attrName = $this->getName($attr->name);
                if ($attrName === 'ApiPlatform\Metadata\ApiResource'
                    || $attrName === 'ApiResource') {
                    return $attr;
                }
            }
        }

        return null;
    }

    private function fixOperations(Attribute $attr, string $domain): bool
    {
        $hasChanges = false;

        foreach ($attr->args as $arg) {
            // Look for the 'operations' argument
            if (!$arg->name || $this->getName($arg->name) !== 'operations') {
                continue;
            }

            if (!$arg->value instanceof Array_) {
                continue;
            }

            foreach ($arg->value->items as $item) {
                if (!$item instanceof ArrayItem || !$item->value instanceof New_) {
                    continue;
                }

                $hasChanges = $this->fixOperation($item->value, $domain) || $hasChanges;
            }
        }

        return $hasChanges;
    }

    private function fixOperation(New_ $operation, string $domain): bool
    {
        // Check if this is a bulk operation
        $isBulkOperation = $this->isBulkOperation($operation);

        // Find the uriTemplate argument
        foreach ($operation->args as $arg) {
            if (!$arg->name || $this->getName($arg->name) !== 'uriTemplate') {
                continue;
            }

            if (!$arg->value instanceof String_) {
                continue;
            }

            $uriTemplate = $arg->value->value;

            // Check if URI needs to be fixed
            $newUriTemplate = $this->fixUriTemplate($uriTemplate, $domain, $isBulkOperation);

            if ($newUriTemplate !== $uriTemplate) {
                // Replace the string value with the corrected URI
                $arg->value->value = $newUriTemplate;

                return true;
            }
        }

        return false;
    }

    /**
     * Check if an operation is a bulk operation based on CQRSCommand parameter
     */
    private function isBulkOperation(New_ $operation): bool
    {
        foreach ($operation->args as $arg) {
            if (!$arg->name || $this->getName($arg->name) !== 'CQRSCommand') {
                continue;
            }

            // Get the command class name
            $commandName = $this->getName($arg->value);
            if ($commandName && str_contains($commandName, 'Bulk')) {
                return true;
            }
        }

        return false;
    }

    private function fixUriTemplate(string $uriTemplate, string $domain, bool $isBulkOperation = false): string
    {
        $hasLeadingSlash = str_starts_with($uriTemplate, '/');
        $normalizedUri = ltrim($uriTemplate, '/');

        // Split URI into segments
        $segments = explode('/', $normalizedUri);

        if (empty($segments)) {
            return $uriTemplate;
        }

        $hasChanges = false;

        // Convert domain to kebab-case to match URI format (e.g., "TaxRulesGroup" -> "tax-rules-group")
        $domainKebab = $this->toKebabCase($domain);
        $expectedFirstSegment = $this->pluralize($domainKebab);

        // Find the last non-placeholder segment index
        $lastNonPlaceholderIndex = $this->getLastNonPlaceholderIndex($segments);

        // Process each segment
        for ($i = 0; $i < count($segments); ++$i) {
            $segment = $segments[$i];

            // Special rule for first segment: must be the pluralized domain
            if ($i === 0) {
                if ($segment !== $expectedFirstSegment) {
                    $segments[0] = $expectedFirstSegment;
                    $hasChanges = true;
                }
                continue;
            }

            // Skip placeholders (segments in curly braces)
            if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                continue;
            }

            // Special handling for bulk operations: last segment must start with "bulk-"
            if ($isBulkOperation && $i === $lastNonPlaceholderIndex) {
                if (!str_starts_with($segment, 'bulk-')) {
                    $segments[$i] = 'bulk-' . $segment;
                    $hasChanges = true;
                }
                continue;
            }

            // Skip keywords and bulk operations
            if (in_array($segment, self::SKIPPED_KEYWORDS, true) || str_starts_with($segment, 'bulk-')) {
                continue;
            }

            // For other segments: pluralize them individually
            $pluralForm = $this->inflector->pluralize($segment);

            // Only replace if the plural is different from the original
            if ($pluralForm !== $segment) {
                $segments[$i] = $pluralForm;
                $hasChanges = true;
            }
        }

        if (!$hasChanges) {
            return $uriTemplate;
        }

        $newUri = implode('/', $segments);

        return $hasLeadingSlash ? '/' . $newUri : $newUri;
    }

    /**
     * Get the index of the last non-placeholder segment
     */
    private function getLastNonPlaceholderIndex(array $segments): int
    {
        for ($i = count($segments) - 1; $i >= 0; --$i) {
            $segment = $segments[$i];
            if (!str_starts_with($segment, '{') || !str_ends_with($segment, '}')) {
                return $i;
            }
        }

        return count($segments) - 1;
    }
}
