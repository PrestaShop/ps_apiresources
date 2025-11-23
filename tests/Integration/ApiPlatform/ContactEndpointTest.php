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
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;

class ContactEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::resetTables();
        self::createApiClient(['contact_read', 'contact_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        LanguageResetter::resetLanguages();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'contact',
            'contact_lang',
            'contact_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/contacts/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/contacts',
        ];

        yield 'patch endpoint' => [
            'PATCH',
            '/contacts/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/contacts',
        ];
    }

    public function testAddContact(): int
    {
        $postData = [
            'names' => [
                'en-US' => 'Contact EN',
                'fr-FR' => 'Contact FR',
            ],
            'descriptions' => [
                'en-US' => 'Description EN',
                'fr-FR' => 'Description FR',
            ],
            'email' => 'test@test.com',
            'messagesSavingEnabled' => true,
            'shopIds' => [1],
        ];

        $contact = $this->createItem('/contacts', $postData, ['contact_write']);
        $this->assertArrayHasKey('contactId', $contact);
        $contactId = $contact['contactId'];

        $this->assertSame($postData['names'], $contact['names']);

        return $contactId;
    }

    /**
     * @depends testAddContact
     */
    public function testGetContact(int $contactId): int
    {
        $contact = $this->getItem('/contacts/' . $contactId, ['contact_read']);
        $this->assertEquals($contactId, $contact['contactId']);
        $this->assertArrayHasKey('names', $contact);

        return $contactId;
    }

    /**
     * @depends testGetContact
     */
    public function testPartialUpdateContact(int $contactId): int
    {
        $patchData = [
            'names' => [
                'en-US' => 'Updated Contact EN',
                'fr-FR' => 'Updated Contact FR',
            ],
            'email' => 'updated@test.com',
            'messagesSavingEnabled' => false,
            'shopIds' => [1],
        ];

        $updatedContact = $this->partialUpdateItem('/contacts/' . $contactId, $patchData, ['contact_write']);
        $this->assertSame($patchData['names'], $updatedContact['names']);
        $this->assertSame($patchData['email'], $updatedContact['email']);
        $this->assertSame((bool) $patchData['messagesSavingEnabled'], (bool) $updatedContact['messagesSavingEnabled']);

        return $contactId;
    }

    /**
     * @depends testPartialUpdateContact
     */
    public function testListContacts(int $contactId): int
    {
        $paginatedContacts = $this->listItems('/contacts?orderBy=contactId&sortOrder=desc', ['contact_read']);
        $this->assertGreaterThanOrEqual(1, $paginatedContacts['totalItems']);
        $this->assertEquals('contactId', $paginatedContacts['orderBy']);

        $firstContact = $paginatedContacts['items'][0];
        $this->assertEquals($contactId, $firstContact['contactId']);

        return $contactId;
    }

    public function testInvalidContact(): void
    {
        $invalidData = [
            'names' => [
                'fr-FR' => 'Invalid<',
            ],
            'email' => 'invalidemail@',
            'messagesSavingEnabled' => true,
            'shopIds' => [],
        ];

        $validationErrorsResponse = $this->createItem('/contacts', $invalidData, ['contact_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);

        $this->assertValidationErrors([
            [
                'propertyPath' => 'names',
                'message' => 'The field names is required at least in your default language.',
            ],
            [
                'propertyPath' => 'names[fr-FR]',
                'message' => '"Invalid<" is invalid',
            ],
            [
                'propertyPath' => 'email',
                'message' => 'This value is not a valid email address.',
            ],
            [
                'propertyPath' => 'shopIds',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);
    }
}
