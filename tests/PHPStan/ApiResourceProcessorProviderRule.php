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
 * Prevents adding custom API Platform Processor or Provider implementations.
 *
 * The PrestaShopBundle provides generic CommandProcessor and QueryProvider that handle
 * CQRS command/query bus integration. Custom processors and providers bypass this
 * generic infrastructure and add unnecessary complexity.
 *
 * This rule has no exceptions: no class in this module should implement
 * ProcessorInterface or ProviderInterface directly.
 *
 * @implements Rule<Class_>
 */
final class ApiResourceProcessorProviderRule implements Rule
{
    /**
     * API Platform interfaces that indicate a custom processor or provider.
     *
     * @var list<string>
     */
    private const PROCESSOR_PROVIDER_INTERFACES = [
        'ApiPlatform\\State\\ProcessorInterface',
        'ApiPlatform\\State\\ProviderInterface',
    ];

    private const PROCESSOR_PROVIDER_SHORT_NAMES = [
        'ProcessorInterface',
        'ProviderInterface',
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
        $implementedInterface = $this->findProcessorProviderInterface($node);

        if ($implementedInterface === null) {
            return [];
        }

        $className = $node->namespacedName !== null
            ? $node->namespacedName->toString()
            : ($node->name !== null ? $node->name->name : 'anonymous');

        return [
            RuleErrorBuilder::message(sprintf(
                'Class "%s" implements "%s". '
                . 'Custom API Platform Processors and Providers are not allowed in this module. '
                . 'Use the CommandProcessor or QueryProvider from PrestaShopBundle instead.',
                $className,
                $implementedInterface
            ))
                ->identifier('apiResource.processorProviderNotAllowed')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function findProcessorProviderInterface(Class_ $class): ?string
    {
        foreach ($class->implements as $interfaceName) {
            // PHPStan's NameResolver resolves use-imports, so toString() usually gives the FQCN.
            $fqcn = $interfaceName->toString();
            if (in_array($fqcn, self::PROCESSOR_PROVIDER_INTERFACES, true)) {
                return $fqcn;
            }

            // Fallback: check by short name in case the name was not fully resolved.
            $shortName = $interfaceName->getLast();
            if (in_array($shortName, self::PROCESSOR_PROVIDER_SHORT_NAMES, true)) {
                return $shortName;
            }
        }

        return null;
    }
}
