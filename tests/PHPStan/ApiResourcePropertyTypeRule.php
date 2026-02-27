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

namespace PsApiResourcesTest\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces that all properties in ApiResource classes use only allowed types.
 *
 * Allowed types: bool, int, string, array (and their nullable variants),
 * PrestaShop\Decimal\DecimalNumber, DateTimeImmutable,
 * PrestaShop\PrestaShop\Core\Util\DateTime\DateImmutable (date-only fields),
 * and Symfony\Component\HttpFoundation\File\File (file-upload resources).
 *
 * - `float` is explicitly banned: use DecimalNumber instead.
 * - Every property must have a type declaration.
 * - No other class types are allowed.
 *
 * This rule only applies to classes:
 *   1. In the ApiPlatform\Resources namespace.
 *   2. Annotated with the #[ApiResource] attribute.
 *
 * @implements Rule<Class_>
 */
final class ApiResourcePropertyTypeRule implements Rule
{
    private const ALLOWED_SCALAR_TYPES = ['bool', 'int', 'string', 'array'];

    /**
     * Short class names accepted as property types (in addition to scalars).
     * Matched against the last segment of the resolved class name.
     */
    private const ALLOWED_CLASS_SHORT_NAMES = ['DecimalNumber', 'DateTimeImmutable', 'File'];

    /**
     * Fully-qualified class names accepted as property types.
     */
    private const ALLOWED_CLASS_FQCNS = [
        'PrestaShop\\Decimal\\DecimalNumber',
        'DateTimeImmutable',
        'Symfony\\Component\\HttpFoundation\\File\\File',
        // Date-only fields (no time component) use PrestaShop's own DateImmutable
        // which formats the value correctly without the time part.
        'PrestaShop\\PrestaShop\\Core\\Util\\DateTime\\DateImmutable',
    ];

    /**
     * Types that are explicitly banned, with their recommended replacement.
     *
     * @var array<string, string>
     */
    private const BANNED_TYPES = [
        'float' => 'DecimalNumber',
        'double' => 'DecimalNumber',
    ];

    private const ALLOWED_TYPES_DESCRIPTION = 'Only scalar types (bool, int, string, array), DecimalNumber, DateTimeImmutable, and File are allowed.';

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Only process classes in the ApiPlatform\Resources namespace.
        if ($node->namespacedName === null) {
            return [];
        }
        if (!str_contains($node->namespacedName->toString(), 'ApiPlatform\\Resources\\')) {
            return [];
        }

        // Only process classes decorated with #[ApiResource].
        if (!$this->hasApiResourceAttribute($node)) {
            return [];
        }

        $errors = [];
        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof Property) {
                continue;
            }
            foreach ($stmt->props as $prop) {
                $errors = array_merge($errors, $this->checkProperty($stmt, $prop->name->name));
            }
        }

        return $errors;
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
     * @return list<RuleError>
     */
    private function checkProperty(Property $property, string $propertyName): array
    {
        $line = $property->getStartLine();

        if ($property->type === null) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'ApiResource property $%s has no type declaration. %s',
                    $propertyName,
                    self::ALLOWED_TYPES_DESCRIPTION
                ))->line($line)->build(),
            ];
        }

        return $this->checkTypeNode($property->type, $propertyName, $line);
    }

    /**
     * @return list<RuleError>
     */
    private function checkTypeNode(Node $typeNode, string $propertyName, int $line): array
    {
        // ?T — unwrap and check inner type.
        if ($typeNode instanceof NullableType) {
            return $this->checkTypeNode($typeNode->type, $propertyName, $line);
        }

        // T1|T2|null — check each non-null type.
        if ($typeNode instanceof UnionType) {
            $errors = [];
            foreach ($typeNode->types as $type) {
                if ($type instanceof Identifier && $type->name === 'null') {
                    continue;
                }
                $errors = array_merge($errors, $this->checkTypeNode($type, $propertyName, $line));
            }

            return $errors;
        }

        // Built-in type identifiers: int, string, bool, float, array, mixed, …
        if ($typeNode instanceof Identifier) {
            return $this->checkBuiltinType($typeNode->name, $propertyName, $line);
        }

        // Class/interface names (resolved by PHPStan's NameResolver).
        if ($typeNode instanceof Name) {
            return $this->checkClassType($typeNode, $propertyName, $line);
        }

        return [];
    }

    /**
     * @return list<RuleError>
     */
    private function checkBuiltinType(string $typeName, string $propertyName, int $line): array
    {
        if (isset(self::BANNED_TYPES[$typeName])) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'ApiResource property $%s uses banned type "%s". Use %s instead.',
                    $propertyName,
                    $typeName,
                    self::BANNED_TYPES[$typeName]
                ))->line($line)->build(),
            ];
        }

        if (!in_array($typeName, self::ALLOWED_SCALAR_TYPES, true)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'ApiResource property $%s has unsupported type "%s". %s',
                    $propertyName,
                    $typeName,
                    self::ALLOWED_TYPES_DESCRIPTION
                ))->line($line)->build(),
            ];
        }

        return [];
    }

    /**
     * @return list<RuleError>
     */
    private function checkClassType(Name $typeNode, string $propertyName, int $line): array
    {
        // PHPStan's NameResolver has already resolved use-imports, so toString()
        // gives the fully-qualified class name (without a leading backslash).
        $fqcn = $typeNode->toString();
        $shortName = $typeNode->getLast();

        if (in_array($fqcn, self::ALLOWED_CLASS_FQCNS, true)) {
            return [];
        }

        // Also accept by short name so that \DateTimeImmutable (global class,
        // no import needed) and any alias still resolves correctly.
        if (in_array($shortName, self::ALLOWED_CLASS_SHORT_NAMES, true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'ApiResource property $%s has unsupported class type "%s". %s',
                $propertyName,
                $fqcn,
                self::ALLOWED_TYPES_DESCRIPTION
            ))->line($line)->build(),
        ];
    }
}
