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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Requires every operation of an #[ApiResource] to declare at least one scope, through its
 * scopes argument or extraProperties['scopes']. An operation without scope is reachable by any
 * API client whatever the scopes it was granted.
 *
 * @implements Rule<Class_>
 */
final class ApiResourceOperationScopesRule implements Rule
{
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
        $errors = [];
        foreach ($this->getOperations($node) as $operation) {
            if ($this->hasScopes($operation)) {
                continue;
            }

            $operationName = $operation->class instanceof Name ? $operation->class->getLast() : 'Operation';
            $uriTemplate = $this->getNamedArgument($operation, 'uriTemplate')?->value;

            $errors[] = RuleErrorBuilder::message(sprintf(
                'API operation %s%s has no scope. Every endpoint must declare at least one scope (e.g. scopes: [\'product_write\']).',
                $operationName,
                $uriTemplate instanceof String_ ? ' ' . $uriTemplate->value : ''
            ))
                ->identifier('apiResource.missingScope')
                ->line($operation->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * @return list<New_>
     */
    private function getOperations(Class_ $class): array
    {
        $operations = [];
        foreach ($class->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->getLast() !== 'ApiResource') {
                    continue;
                }

                foreach ($attr->args as $arg) {
                    if ($arg->name?->name !== 'operations' || !$arg->value instanceof Array_) {
                        continue;
                    }

                    foreach ($arg->value->items as $item) {
                        if ($item?->value instanceof New_) {
                            $operations[] = $item->value;
                        }
                    }
                }
            }
        }

        return $operations;
    }

    private function hasScopes(New_ $operation): bool
    {
        $scopes = $this->getNamedArgument($operation, 'scopes')?->value;
        if (null !== $scopes && !$this->isEmptyArray($scopes)) {
            return true;
        }

        $extraProperties = $this->getNamedArgument($operation, 'extraProperties')?->value;
        if (!$extraProperties instanceof Array_) {
            // Not a literal array, cannot be checked statically
            return null !== $extraProperties;
        }

        foreach ($extraProperties->items as $item) {
            if ($item?->key instanceof String_ && $item->key->value === 'scopes' && !$this->isEmptyArray($item->value)) {
                return true;
            }
        }

        return false;
    }

    private function isEmptyArray(Node\Expr $expr): bool
    {
        return $expr instanceof Array_ && empty($expr->items);
    }

    private function getNamedArgument(New_ $new, string $name): ?Arg
    {
        foreach ($new->args as $arg) {
            if ($arg instanceof Arg && $arg->name?->name === $name) {
                return $arg;
            }
        }

        return null;
    }
}
