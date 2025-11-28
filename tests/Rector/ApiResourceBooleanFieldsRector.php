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

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\VarLikeIdentifier;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Removes the "is" prefix from boolean fields and normalizes synonyms in API Platform resources.
 *
 * This rule ensures boolean properties follow a consistent naming convention:
 * 1. Removes "is" prefix from boolean properties
 * 2. Normalizes synonyms to use consistent terminology (e.g., "active" → "enabled")
 *
 * Rules applied:
 * 1. **Only processes API Platform resources**: Classes in src/ApiPlatform/Resources directory
 * 2. **Only processes boolean properties**: Properties with bool type hint
 * 3. **Removes "is" prefix**: Properties starting with "is" have the prefix removed
 * 4. **Converts to camel case**: The remaining part is converted to camel case
 * 5. **Normalizes synonyms**: Properties named "active" are renamed to "enabled"
 *
 * Examples:
 * - "public bool $isEnabled;" → "public bool $enabled;"
 * - "public bool $isTaxIncluded;" → "public bool $taxIncluded;"
 * - "public bool $isActive;" → "public bool $enabled;"
 * - "public bool $active;" → "public bool $enabled;"
 * - "public bool $enabled;" → "public bool $enabled;" (no change)
 */
final class ApiResourceBooleanFieldsRector extends AbstractRector
{
    /**
     * Synonyms that should be normalized to a consistent name
     * Key: the synonym to replace, Value: the target name
     */
    private const BOOLEAN_SYNONYMS = [
        'active' => 'enabled',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Removes the "is" prefix from boolean fields in API Platform resources',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class Discount
{
    public bool $isTaxIncluded;
    public bool $active;
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
class Discount
{
    public bool $taxIncluded;
    public bool $enabled;
}
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

        // Process all properties in the class
        $hasChanges = false;
        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof Property) {
                continue;
            }

            if ($this->processProperty($stmt)) {
                $hasChanges = true;
            }
        }

        // Return the node if we made changes, null otherwise
        return $hasChanges ? $node : null;
    }

    /**
     * Process a property and rename it if needed
     */
    private function processProperty(Property $property): bool
    {
        // Only process boolean properties
        if (!$this->isPropertyBoolean($property)) {
            return false;
        }

        // Get the property name
        $propertyName = $this->getName($property);
        if (!$propertyName) {
            return false;
        }

        $newPropertyName = $propertyName;

        // Step 1: Check if the property name starts with "is" and remove the prefix
        if (str_starts_with($propertyName, 'is')) {
            $newPropertyName = $this->removeIsPrefixAndCamelCase($propertyName);
        }

        // Step 2: Check if the property name (after removing "is" if applicable) is a synonym
        if (isset(self::BOOLEAN_SYNONYMS[$newPropertyName])) {
            $newPropertyName = self::BOOLEAN_SYNONYMS[$newPropertyName];
        }

        // If the new name is the same as the original, no changes needed
        if ($newPropertyName === $propertyName) {
            return false;
        }

        // Rename the property
        $property->props[0]->name = new VarLikeIdentifier($newPropertyName);

        return true;
    }

    /**
     * Check if a property has a boolean type hint
     */
    private function isPropertyBoolean(Property $node): bool
    {
        if ($node->type === null) {
            return false;
        }

        // Handle Identifier nodes (simple types like 'bool')
        if ($node->type instanceof Identifier) {
            return $node->type->toString() === 'bool';
        }

        // Handle Name nodes (for namespaced types, though unlikely for bool)
        if ($node->type instanceof Name) {
            return $this->getName($node->type) === 'bool';
        }

        return false;
    }

    /**
     * Remove "is" prefix and convert to camel case
     *
     * Examples:
     * - "isEnabled" -> "enabled"
     * - "isTaxIncluded" -> "taxIncluded"
     * - "isActive" -> "active"
     */
    private function removeIsPrefixAndCamelCase(string $propertyName): string
    {
        // Remove "is" prefix
        $withoutIs = substr($propertyName, 2);

        // If the remaining string is empty or doesn't start with uppercase, keep original
        if (empty($withoutIs) || !ctype_upper($withoutIs[0])) {
            return $propertyName;
        }

        // Convert first character to lowercase for camel case
        return lcfirst($withoutIs);
    }
}
