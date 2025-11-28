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

namespace PsApiResourcesTest\Unit\Rector;

use PHPUnit\Framework\Attributes\DataProvider;
use PsApiResourcesTest\Rector\ApiResourceUriTemplateRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * Test class for ApiResourceUriTemplateRector following Rector's testing guidelines
 *
 * @see https://getrector.com/documentation/writing-tests-for-custom-rule
 */
final class ApiResourceUriTemplateRectorTest extends AbstractRectorTestCase
{
    /**
     * @param string $filePath
     */
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    /**
     * Data provider for test files
     */
    public static function provideData(): \Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture/ApiResourceUriTemplateRector');
    }

    /**
     * Provide the rector rule configuration
     */
    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/uri_template_rule.php';
    }
}
