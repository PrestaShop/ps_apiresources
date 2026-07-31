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

namespace PsApiResourcesTest\Integration\ApiPlatform;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PrestaShop\PrestaShop\Core\Version;

/**
 * End-to-end test of the minVersion/maxVersion endpoint filtering, handled by the module's (deprecated)
 * CoreVersionCompatibilityMetadataCollectionFactoryDecorator on PrestaShop < 9.2 and by its core twin
 * on PrestaShop >= 9.2.
 *
 * The test resource (see VersionedApiResource) is not part of the module resources: it is copied into the
 * module's src/ApiPlatform/Resources folder for the duration of this test class only, so it never pollutes
 * the OpenApi documentation of a real shop. Its version boundaries are chosen so that a different combination
 * of endpoints is filtered on each core version of the CI matrix, and the expectations are computed at runtime
 * from Version::VERSION so they stay accurate on any core version.
 *
 * The tests run in separate PHP processes because the compiled container class of the kernel stays loaded
 * in the PHP process once booted: when the whole suite runs in a single process, a kernel booted before
 * this class copied the test resource keeps serving its old routes (without the test resource) even after
 * the cache has been cleared. For the same reason, the resource is copied and the cache is cleared BEFORE
 * the parent setUpBeforeClass boots the first kernel of each process.
 */
#[RunTestsInSeparateProcesses]
class VersionedEndpointsTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!copy(self::getFixtureResourcePath(), self::getInstalledResourcePath())) {
            throw new \RuntimeException(sprintf('Could not copy the test resource to %s', self::getInstalledResourcePath()));
        }
        self::clearCache();
        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (file_exists(self::getInstalledResourcePath())) {
            unlink(self::getInstalledResourcePath());
        }
        self::clearCache();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get versioned endpoint' => [
            'GET',
            '/test/module/versioned/always/hook/1',
        ];
    }

    public function testVersionedEndpointsAreFilteredBasedOnCoreVersion(): void
    {
        // The version filtering applies in debug mode as well (unless the experimental endpoints feature
        // flag is enabled), so the default test kernel can be used directly
        $bearerToken = $this->getBearerToken(['hook_read']);
        $client = static::createClient();

        foreach (self::getVersionedEndpoints() as $description => [$endpointUrl, $expectedAvailable]) {
            $client->request('GET', $endpointUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $bearerToken,
                ],
            ]);
            self::assertResponseStatusCodeSame(
                $expectedAvailable ? 200 : 404,
                sprintf('Unexpected response status for endpoint "%s" (%s) on PrestaShop %s', $endpointUrl, $description, Version::VERSION)
            );
        }
    }

    /**
     * The expected availability is computed at runtime against the actual core version, with the same
     * inclusive version_compare semantics as the decorator, so this test runs accurately on every core
     * version of the CI matrix (9.0.x, 9.1.x, 9.2.x, develop).
     *
     * @return iterable<string, array{string, bool}>
     */
    private static function getVersionedEndpoints(): iterable
    {
        yield 'no version constraint, always available' => [
            '/test/module/versioned/always/hook/1',
            true,
        ];

        yield 'minVersion 9.1.0, filtered on lower core versions' => [
            '/test/module/versioned/min-91/hook/1',
            version_compare(Version::VERSION, '9.1.0', '>='),
        ];

        yield 'minVersion 9.2.0, filtered on lower core versions' => [
            '/test/module/versioned/min-92/hook/1',
            version_compare(Version::VERSION, '9.2.0', '>='),
        ];

        yield 'maxVersion 9.1.9999, filtered on higher core versions' => [
            '/test/module/versioned/max-91/hook/1',
            version_compare(Version::VERSION, '9.1.9999', '<='),
        ];

        yield 'minVersion 99.99.99, never available' => [
            '/test/module/versioned/never/hook/1',
            false,
        ];
    }

    private static function getFixtureResourcePath(): string
    {
        return __DIR__ . '/../../Resources/ApiPlatform/Resources/VersionedApiResource.php';
    }

    /**
     * The resource must be copied into the module folder that is scanned for ApiPlatform resources. It is
     * resolved through _PS_MODULE_DIR_ (redefined to tests/Resources/modules/ in the test bootstrap) which
     * contains a symbolic link to the actual module folder.
     */
    private static function getInstalledResourcePath(): string
    {
        return _PS_MODULE_DIR_ . 'ps_apiresources/src/ApiPlatform/Resources/VersionedApiResource.php';
    }

    /**
     * The compiled metadata (and its filtering) is cached, so the cache must be cleared after the test
     * resource is added and after it is removed.
     */
    private static function clearCache(): void
    {
        $commandLine = 'php -d memory_limit=-1 ' . _PS_ROOT_DIR_ . '/bin/console cache:clear --no-warmup --no-interaction --env=test --app-id=admin-api --quiet';
        $result = 0;
        system($commandLine, $result);
        if ($result !== 0) {
            throw new \RuntimeException('Could not clear the cache');
        }
    }
}
