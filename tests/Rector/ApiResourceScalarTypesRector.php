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
 * If you did not receive a copy of the license and be unable to
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
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces `float` property types with `\PrestaShop\Decimal\DecimalNumber` in
 * ApiPlatform resource classes.
 *
 * `float` is banned in ApiResource classes because it loses precision for
 * monetary and dimensional values. `DecimalNumber` is the required replacement.
 *
 * This rule only fixes the PHP type declaration. Developers must also:
 *   - Add `use PrestaShop\Decimal\DecimalNumber;` (or keep the FQN produced
 *     here and let their IDE/PHP-CS-Fixer shorten it).
 *   - Update any `openapiContext` that declared `'format' => 'float'`.
 *   - Update any setter that casts with `(float)` to use `new DecimalNumber()`.
 *
 * Examples:
 * - `public float $rate;`      → `public \PrestaShop\Decimal\DecimalNumber $rate;`
 * - `public ?float $amount;`   → `public ?\PrestaShop\Decimal\DecimalNumber $amount;`
 */
final class ApiResourceScalarTypesRector extends AbstractRector
{
    private const DECIMAL_NUMBER_FQCN = 'PrestaShop\\Decimal\\DecimalNumber';

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replaces float property types with DecimalNumber in ApiPlatform resource classes',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Tax
{
    public float $rate;
    public ?float $optionalAmount;
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Tax
{
    public \PrestaShop\Decimal\DecimalNumber $rate;
    public ?\PrestaShop\Decimal\DecimalNumber $optionalAmount;
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
        // Only process classes in the ApiPlatform/Resources namespace.
        $namespace = $this->getName($node->namespacedName);
        if (!$namespace || !str_contains($namespace, 'ApiPlatform\\Resources\\')) {
            return null;
        }

        // Only process classes decorated with #[ApiResource].
        if (!$this->hasApiResourceAttribute($node)) {
            return null;
        }

        $hasChanges = false;
        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof Property) {
                continue;
            }
            if ($this->replaceFloatType($stmt)) {
                $hasChanges = true;
            }
        }

        return $hasChanges ? $node : null;
    }

    private function hasApiResourceAttribute(Class_ $class): bool
    {
        foreach ($class->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $name = $attr->name->toString();
                if ($name === 'ApiResource' || str_ends_with($name, '\\ApiResource')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Replaces a `float` or `?float` type on the given property with
     * `\PrestaShop\Decimal\DecimalNumber` / `?\PrestaShop\Decimal\DecimalNumber`.
     *
     * Returns true when a replacement was made.
     */
    private function replaceFloatType(Property $property): bool
    {
        $type = $property->type;

        if ($type instanceof Identifier && $type->name === 'float') {
            $property->type = new FullyQualified(self::DECIMAL_NUMBER_FQCN);

            return true;
        }

        if (
            $type instanceof NullableType
            && $type->type instanceof Identifier
            && $type->type->name === 'float'
        ) {
            $type->type = new FullyQualified(self::DECIMAL_NUMBER_FQCN);

            return true;
        }

        return false;
    }
}
