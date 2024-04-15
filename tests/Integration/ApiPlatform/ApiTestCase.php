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

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as SymfonyApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use PrestaShop\PrestaShop\Core\Domain\ApiClient\Command\AddApiClientCommand;
use PrestaShop\PrestaShop\Core\Domain\Configuration\ShopConfigurationInterface;
use PrestaShop\PrestaShop\Core\Domain\Language\Command\AddLanguageCommand;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\Resources\Resetter\ApiClientResetter;

abstract class ApiTestCase extends SymfonyApiTestCase
{
    protected const CLIENT_ID = 'test_client_id';
    protected const CLIENT_NAME = 'test_client_name';

    protected static ?string $clientSecret = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::updateConfiguration('PS_ADMIN_API_FORCE_DEBUG_SECURED', 0);
        ApiClientResetter::resetApiClient();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ApiClientResetter::resetApiClient();
        self::updateConfiguration('PS_ADMIN_API_FORCE_DEBUG_SECURED', 1);
        self::$clientSecret = null;
    }

    /**
     * API endpoints are only available in the AdminApi application so we force using the proper kernel here.
     *
     * @return string
     */
    protected static function getKernelClass(): string
    {
        return \AdminAPIKernel::class;
    }

    protected static function bootKernel(array $options = []): KernelInterface
    {
        $bootKernel = parent::bootKernel($options);
        // We must define the global $kernel variable for legacy code to access the container (see SymfonyContainer::getInstance)
        global $kernel;
        $kernel = $bootKernel;

        return $bootKernel;
    }

    /**
     * @dataProvider getProtectedEndpoints
     *
     * @param string $method
     * @param string $uri
     */
    public function testProtectedEndpoints(string $method, string $uri, string $contentType = 'application/json'): void
    {
        $options['headers']['content-type'] = $contentType;
        // Check that endpoints are not accessible without a proper Bearer token
        $response = static::createClient([], $options)->request($method, $uri);
        self::assertResponseStatusCodeSame(401);

        $content = $response->getContent(false);
        $this->assertNotEmpty($content);
        $this->assertEquals('"No Authorization header provided"', $content);

        // Test same endpoint with a token but without scopes
        $emptyBearerToken = $this->getBearerToken();
        static::createClient([], $options)->request($method, $uri, ['auth_bearer' => $emptyBearerToken]);
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * You must provide a list of protected endpoints that will we automatically checked,
     * the test will check that the endpoints are not accessible when no token is specified
     * AND that they are not accessible when the no particular scope is specified.
     *
     * You should use yield return like this:
     *
     *  yield 'get endpoint' => [
     *      'GET',
     *      '/product/1',
     *  ];
     *
     * Since all Api Platform resources should likely have some protected endpoints this provider
     * method was made abstract to force its implementation. In the unlikely event you need to use
     * this class on a resouce with absolutely no protected endpoints you can still implement this
     * method and return new \EmptyIterator();
     *
     * @return iterable
     */
    abstract public function getProtectedEndpoints(): iterable;

    protected static function createClient(array $kernelOptions = [], array $defaultOptions = []): Client
    {
        if (!isset($defaultOptions['headers']['accept'])) {
            $defaultOptions['headers']['accept'] = ['application/json'];
        }

        if (!isset($defaultOptions['headers']['content-type'])) {
            $defaultOptions['headers']['content-type'] = ['application/json'];
        }

        return parent::createClient($kernelOptions, $defaultOptions);
    }

    protected function getBearerToken(array $scopes = []): string
    {
        if (null === self::$clientSecret) {
            self::createApiClient($scopes);
        }
        $parameters = ['parameters' => [
            'client_id' => static::CLIENT_ID,
            'client_secret' => static::$clientSecret,
            'grant_type' => 'client_credentials',
            'scope' => $scopes,
        ]];
        $options = [
            'extra' => $parameters,
            'headers' => [
                'content-type' => 'application/x-www-form-urlencoded',
            ],
        ];
        $response = static::createClient()->request('POST', '/access_token', $options);

        return json_decode($response->getContent())->access_token;
    }

    protected function listItems(string $listUrl, array $scopes = [], array $filters = []): array
    {
        $bearerToken = $this->getBearerToken($scopes);
        $response = static::createClient()->request('GET', $listUrl, [
            'auth_bearer' => $bearerToken,
            'extra' => [
                'parameters' => [
                    'filters' => $filters,
                ],
            ],
        ]);
        self::assertResponseStatusCodeSame(200);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        $this->assertArrayHasKey('totalItems', $decodedResponse);
        $this->assertArrayHasKey('sortOrder', $decodedResponse);
        $this->assertArrayHasKey('limit', $decodedResponse);
        $this->assertArrayHasKey('filters', $decodedResponse);
        $this->assertArrayHasKey('items', $decodedResponse);

        return $decodedResponse;
    }

    protected function prepareUploadedFile(string $assetFilePath): UploadedFile
    {
        // Uploaded file must be a temporary copy because the file will be moved by the API
        $tmpUploadedImagePath = rtrim(sys_get_temp_dir(), '/') . '/' . basename($assetFilePath);
        copy($assetFilePath, $tmpUploadedImagePath);

        return new UploadedFile($tmpUploadedImagePath, basename($assetFilePath));
    }

    protected static function createApiClient(array $scopes = [], int $lifetime = 10000): void
    {
        $command = new AddApiClientCommand(
            static::CLIENT_NAME,
            static::CLIENT_ID,
            true,
            '',
            $lifetime,
            $scopes
        );

        $container = static::createClient()->getContainer();
        $commandBus = $container->get('prestashop.core.command_bus');
        $createdApiClient = $commandBus->handle($command);

        self::$clientSecret = $createdApiClient->getSecret();
    }

    protected static function addLanguageByLocale(string $locale): int
    {
        $isoCode = substr($locale, 0, strpos($locale, '-'));

        // Copy resource assets into tmp folder to mimic an upload file path
        $flagImage = __DIR__ . '/../../Resources/assets/lang/' . $isoCode . '.jpg';
        if (!file_exists($flagImage)) {
            $flagImage = __DIR__ . '/../../Resources/assets/lang/en.jpg';
        }

        $tmpFlagImage = sys_get_temp_dir() . '/' . $isoCode . '.jpg';
        $tmpNoPictureImage = sys_get_temp_dir() . '/' . $isoCode . '-no-picture.jpg';
        copy($flagImage, $tmpFlagImage);
        copy($flagImage, $tmpNoPictureImage);

        $command = new AddLanguageCommand(
            $locale,
            $isoCode,
            $locale,
            'd/m/Y',
            'd/m/Y H:i:s',
            $tmpFlagImage,
            $tmpNoPictureImage,
            false,
            true,
            [1]
        );

        $container = static::createClient()->getContainer();
        $commandBus = $container->get('prestashop.core.command_bus');

        return $commandBus->handle($command)->getValue();
    }

    protected static function addShopGroup(string $groupName, string $color = null): int
    {
        $shopGroup = new \ShopGroup();
        $shopGroup->name = $groupName;
        $shopGroup->active = true;

        if ($color !== null) {
            $shopGroup->color = $color;
        }

        if (!$shopGroup->add()) {
            throw new \RuntimeException('Could not create shop group');
        }

        return (int) $shopGroup->id;
    }

    protected static function addShop(string $shopName, int $shopGroupId, string $color = null): int
    {
        $shop = new \Shop();
        $shop->active = true;
        $shop->id_shop_group = $shopGroupId;
        // 2 : ID Category for "Home" in database
        $shop->id_category = 2;
        $shop->theme_name = _THEME_NAME_;
        $shop->name = $shopName;
        if ($color !== null) {
            $shop->color = $color;
        }

        if (!$shop->add()) {
            throw new \RuntimeException('Could not create shop');
        }
        $shop->setTheme();
        \Shop::resetContext();
        \Shop::resetStaticCache();

        return (int) $shop->id;
    }

    protected static function updateConfiguration(string $configurationKey, $value, ShopConstraint $shopConstraint = null): void
    {
        self::getContainer()->get(ShopConfigurationInterface::class)->set($configurationKey, $value, $shopConstraint ?: ShopConstraint::allShops());
        \Configuration::resetStaticCache();
    }
}
