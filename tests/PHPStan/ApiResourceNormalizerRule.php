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
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Prevents adding new custom Normalizer/Denormalizer implementations to the module.
 *
 * Custom normalizers are often added to work around non-scalar ApiResource property
 * types. The ApiResourcePropertyTypeRule enforces scalar types, making most normalizers
 * unnecessary. Only the limited set listed in ALLOWED_CLASSES is permitted.
 *
 * To add a new normalizer you MUST:
 *   1. Add its fully-qualified class name to ALLOWED_CLASSES below.
 *   2. Justify in a comment why a normalizer is necessary rather than fixing the
 *      ApiResource property type to use scalar types.
 *
 * @implements Rule<Class_>
 */
final class ApiResourceNormalizerRule implements Rule
{
    /**
     * Symfony Serializer / API Platform interfaces that mark a class as a
     * normalizer or denormalizer.
     *
     * @var list<string>
     */
    private const NORMALIZER_INTERFACES = [
        'Symfony\\Component\\Serializer\\Normalizer\\NormalizerInterface',
        'Symfony\\Component\\Serializer\\Normalizer\\DenormalizerInterface',
        'ApiPlatform\\Serializer\\ContextAwareNormalizerInterface',
    ];

    private const NORMALIZER_INTERFACE_SHORT_NAMES = [
        'NormalizerInterface',
        'DenormalizerInterface',
        'ContextAwareNormalizerInterface',
    ];

    /**
     * Fully-qualified class names of normalizers/denormalizers that are permitted.
     *
     * Classes marked "Pending removal" should be deleted once the corresponding
     * ApiResource property-type issue is resolved; do NOT add new entries without
     * justification and review.
     *
     * @var list<string>
     */
    private const ALLOWED_CLASSES = [
        // Valid: complex denormalization of product-combination generation input.
        'PrestaShop\\Module\\APIResources\\ApiPlatform\\Normalizer\\GenerateCombinationsSerializer',
    ];

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
        if (!$this->implementsNormalizerInterface($node)) {
            return [];
        }

        $fqcn = $node->namespacedName !== null ? $node->namespacedName->toString() : null;

        if ($fqcn !== null && in_array($fqcn, self::ALLOWED_CLASSES, true)) {
            return [];
        }

        $className = $fqcn ?? ($node->name !== null ? $node->name->name : 'anonymous');

        return [
            RuleErrorBuilder::message(sprintf(
                'Class "%s" implements a Normalizer/Denormalizer interface. '
                . 'Custom normalizers are not allowed without explicit approval. '
                . 'Fix the ApiResource property type to use scalar types instead, '
                . 'or add this class to the allowed list in ApiResourceNormalizerRule::ALLOWED_CLASSES.',
                $className
            ))
                ->identifier('apiResource.normalizerNotAllowed')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function implementsNormalizerInterface(Class_ $class): bool
    {
        foreach ($class->implements as $interface) {
            if ($this->isNormalizerInterface($interface)) {
                return true;
            }
        }

        return false;
    }

    private function isNormalizerInterface(Name $name): bool
    {
        // PHPStan's NameResolver resolves use-imports, so toString() usually gives the FQCN.
        if (in_array($name->toString(), self::NORMALIZER_INTERFACES, true)) {
            return true;
        }

        // Fallback: check by short name in case the name was not fully resolved.
        return in_array($name->getLast(), self::NORMALIZER_INTERFACE_SHORT_NAMES, true);
    }
}
