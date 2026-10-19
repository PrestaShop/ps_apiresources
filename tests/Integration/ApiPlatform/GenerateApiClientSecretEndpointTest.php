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

use Symfony\Component\HttpFoundation\Response;

class GenerateApiClientSecretEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['api_client_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'generate api client secret endpoint' => ['PUT', '/api-clients/1/secrets'];
    }

    public function testGenerateApiClientSecret(): void
    {
        $apiClientId = (int) \Db::getInstance()->getValue(
            'SELECT `id_api_client` FROM `' . _DB_PREFIX_ . 'api_client` ORDER BY `id_api_client` DESC'
        );

        $response = $this->updateItem(
            '/api-clients/' . $apiClientId . '/secrets',
            [],
            ['api_client_write'],
            Response::HTTP_OK
        );

        $this->assertArrayHasKey('secret', $response);
        $this->assertNotEmpty($response['secret']);
    }
}
