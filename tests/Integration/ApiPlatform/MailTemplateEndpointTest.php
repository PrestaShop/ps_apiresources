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

class MailTemplateEndpointTest extends ApiTestCase
{
    /**
     * The mail templates live in mails/<iso>, so what these endpoints call a locale is really
     * the language ISO code — 'en-US' matches no directory and lists nothing.
     */
    private const LOCALE = 'en';

    /**
     * The listing, get and edit endpoints all use CQRS classes that landed in 9.2. Below that
     * version ApiResourceScopesExtractor drops the operations, so neither their routes nor the
     * mail_template_read scope exist. Only the generate endpoint is available everywhere.
     */
    private const READ_EDIT_MIN_VERSION = '9.2.0';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(
            self::isVersionAtLeast(self::READ_EDIT_MIN_VERSION)
                ? ['mail_template_read', 'mail_template_write']
                : ['mail_template_write']
        );
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'generate theme mail templates endpoint' => ['PUT', '/mail-templates'];

        if (self::isVersionAtLeast(self::READ_EDIT_MIN_VERSION)) {
            yield 'list mail templates endpoint' => ['GET', '/mail-templates?locale=' . self::LOCALE];
            yield 'get mail template' => ['GET', '/mail-templates/order_conf?locale=' . self::LOCALE . '&source=core'];
            yield 'edit mail template' => ['PATCH', '/mail-templates/order_conf'];
        }
    }

    /**
     * @return array{templateName: string, source: string}
     */
    public function testListMailTemplates(): array
    {
        $this->markTestSkippedByMinVersion(self::READ_EDIT_MIN_VERSION);

        $result = $this->getItem('/mail-templates?locale=' . self::LOCALE, ['mail_template_read']);

        $this->assertIsArray($result);
        // PS ships a bunch of English core templates — expect at least one row.
        $this->assertNotEmpty($result);
        foreach ($result as $row) {
            $this->assertArrayHasKey('templateName', $row);
            $this->assertArrayHasKey('source', $row);
        }

        // Pick a core template so the rest of the suite works on a template that really exists,
        // instead of assuming order_conf is installed.
        foreach ($result as $row) {
            if ('core' === $row['source']) {
                return ['templateName' => $row['templateName'], 'source' => $row['source']];
            }
        }

        $this->markTestSkipped('No core mail template is listed for ' . self::LOCALE . '.');
    }

    /**
     * @depends testListMailTemplates
     *
     * @param array{templateName: string, source: string} $template
     */
    public function testGetMailTemplate(array $template): array
    {
        $mailTemplate = $this->getItem(
            sprintf('/mail-templates/%s?locale=%s&source=core', $template['templateName'], self::LOCALE),
            ['mail_template_read']
        );

        $this->assertSame($template['templateName'], $mailTemplate['templateName']);
        $this->assertArrayHasKey('htmlContent', $mailTemplate);
        $this->assertArrayHasKey('txtContent', $mailTemplate);

        return $template;
    }

    /**
     * @depends testGetMailTemplate
     *
     * @param array{templateName: string, source: string} $template
     */
    public function testEditMailTemplate(array $template): void
    {
        $htmlContent = '<p>Edited by the Admin API integration test</p>';
        $txtContent = 'Edited by the Admin API integration test';

        $this->partialUpdateItem(
            '/mail-templates/' . $template['templateName'],
            [
                'locale' => self::LOCALE,
                'source' => 'core',
                'htmlContent' => $htmlContent,
                'txtContent' => $txtContent,
            ],
            ['mail_template_write'],
            // EditEmailBodyTemplateHandler returns void and the operation declares no
            // CQRSQuery, so the update answers an empty 204
            Response::HTTP_NO_CONTENT
        );

        // The edit is observed through the get endpoint, which is what #370 and #373 could not
        // do while they lived in separate PRs
        $mailTemplate = $this->getItem(
            sprintf('/mail-templates/%s?locale=%s&source=core', $template['templateName'], self::LOCALE),
            ['mail_template_read']
        );

        $this->assertSame($htmlContent, $mailTemplate['htmlContent']);
        $this->assertSame($txtContent, $mailTemplate['txtContent']);
    }

    public function testGenerateThemeMailTemplates(): void
    {
        $return = $this->updateItem(
            '/mail-templates',
            [
                'themeName' => 'classic',
                'language' => 'en',
                'overwriteTemplates' => false,
            ],
            ['mail_template_write'],
            Response::HTTP_NO_CONTENT
        );

        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);
    }
}
