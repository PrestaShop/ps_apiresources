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

class EmployeePasswordResetEndpointTest extends ApiTestCase
{
    private static ?int $originalMailMethod = null;

    public static function setUpBeforeClass(): void
    {
        if (self::isVersionUnder('9.2.0')) {
            static::markTestSkipped('The employee password reset endpoint only exists since PrestaShop 9.2.0');

            return;
        }

        parent::setUpBeforeClass();
        self::createApiClient(['employee_write']);

        // Disable real email sending so Mail::send() succeeds without an SMTP server.
        self::$originalMailMethod = (int) \Configuration::get('PS_MAIL_METHOD');
        \Configuration::updateValue('PS_MAIL_METHOD', \Mail::METHOD_DISABLE);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$originalMailMethod !== null) {
            \Configuration::updateValue('PS_MAIL_METHOD', self::$originalMailMethod);
        }

        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        // Data providers are resolved when PHPUnit builds the test suite, before setUpBeforeClass
        // gets a chance to skip the class, and an empty provider is reported as an error. So the
        // endpoint is yielded unconditionally; on cores < 9.2.0 the whole class is skipped anyway
        // and this data set is never executed.
        yield 'send employee password reset email endpoint' => ['POST', '/employees/send-password-reset-email'];
    }

    public function testSendPasswordResetEmail(): void
    {
        $adminEmail = (string) \Db::getInstance()->getValue(
            'SELECT `email` FROM `' . _DB_PREFIX_ . 'employee` ORDER BY `id_employee` ASC'
        );

        $this->requestApi(
            'POST',
            '/employees/send-password-reset-email',
            ['email' => $adminEmail],
            ['employee_write'],
            Response::HTTP_NO_CONTENT
        );
    }

    public function testUnknownEmployeeReturnsNotFound(): void
    {
        $this->requestApi(
            'POST',
            '/employees/send-password-reset-email',
            ['email' => 'never-existed-' . uniqid() . '@example.test'],
            ['employee_write'],
            Response::HTTP_NOT_FOUND
        );
    }
}
