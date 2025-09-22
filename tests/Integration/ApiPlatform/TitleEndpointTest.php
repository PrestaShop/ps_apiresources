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

class TitleEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Add the fr-FR language to test multi lang values accurately
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        DatabaseDump::restoreTables(['gender', 'gender_lang']);
        self::createApiClient(['title_write', 'title_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        LanguageResetter::resetLanguages();
        DatabaseDump::restoreTables(['gender', 'gender_lang']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/title',
            'multipart/form-data',
        ];

        yield 'get endpoint' => [
            'GET',
            '/title/1',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/title/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/title/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/titles',
        ];

        yield 'bulk delete endpoint' => [
            'PUT',
            '/titles/delete',
        ];
    }

    public function testAddTitle(): int
    {
        $itemsCount = $this->countItems('/titles', ['title_read']);
        $uploadTitle = $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/image/Brown_bear_cushion.jpg');

        $title = $this->requestApi('POST', '/title', null, ['title_write'], Response::HTTP_CREATED, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'names' => [
                        'en-US' => 'name en',
                        'fr-FR' => 'name fr',
                    ],
                    // We use string on purpose because form data are sent like string, thus we validate here that the denormalization still
                    // works with string value (actually we only ignore the wrong type, but it works nonetheless)
                    'gender' => '1',
                ],
            ],
        ]);
        $this->assertArrayHasKey('titleId', $title);
        $titleId = $title['titleId'];
        $this->assertEquals(
            [
                'titleId' => $titleId,
            ],
            $title
        );

        $newItemsCount = $this->countItems('/titles', ['title_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $titleId;
    }

    /**
     * @depends testAddTitle
     *
     * @param int $titleId
     *
     * @return int
     */
    public function testGetTitle(int $titleId): int
    {
        $title = $this->getItem('/title/' . $titleId, ['title_read']);
        $this->assertEquals(
            [
                'titleId' => $titleId,
                'names' => [
                    'en-US' => 'name en',
                    'fr-FR' => 'name fr',
                ],
                'gender' => 1,
                'width' => 16,
                'height' => 16,
            ],
            $title
        );

        return $titleId;
    }

    /**
     * @depends testGetTitle
     *
     * @param int $titleId
     *
     * @return int
     */
    public function testPartialUpdateTitle(int $titleId): int
    {
        $updatedTitle = $this->partialUpdateItem('/title/' . $titleId, [
            'names' => [
                'en-US' => 'name en Updated',
                'fr-FR' => 'name fr Updated',
            ],
        ], ['title_write']);
        $this->assertEquals(
            [
                'titleId' => $titleId,
                'names' => [
                    'en-US' => 'name en Updated',
                    'fr-FR' => 'name fr Updated',
                ],
                'gender' => 1,
                'width' => 16,
                'height' => 16,
            ],
            $updatedTitle
        );

        $updatedTitle = $this->partialUpdateItem('/title/' . $titleId, [
            'gender' => 2,
        ], ['title_write']);
        $this->assertEquals(
            [
                'titleId' => $titleId,
                'names' => [
                    'en-US' => 'name en Updated',
                    'fr-FR' => 'name fr Updated',
                ],
                'gender' => 2,
                'width' => 16,
                'height' => 16,
            ],
            $updatedTitle
        );

        return $titleId;
    }

    /**
     * @depends testPartialUpdateTitle
     *
     * @param int $titleId
     *
     * @return int
     */
    public function testGetUpdatedTitle(int $titleId): int
    {
        $title = $this->getItem('/title/' . $titleId, ['title_read']);
        $this->assertEquals(
            [
                'titleId' => $titleId,
                'names' => [
                    'en-US' => 'name en Updated',
                    'fr-FR' => 'name fr Updated',
                ],
                'gender' => 2,
                'width' => 16,
                'height' => 16,
            ],
            $title
        );

        return $titleId;
    }

    /**
     * @depends testGetUpdatedTitle
     *
     * @param int $titleId
     *
     * @return int
     */
    public function testListTitles(int $titleId): int
    {
        $titles = $this->listItems('/titles', ['title_read']);
        $this->assertGreaterThanOrEqual(1, $titles['totalItems']);

        // Search for the one created previously during the tests and assert its data in the list
        $testTitle = null;
        foreach ($titles['items'] as $title) {
            if ($title['titleId'] === $titleId) {
                $testTitle = $title;
            }
        }
        $this->assertNotNull($testTitle);
        $this->assertEquals(
            [
                'titleId' => $titleId,
                'name' => 'name en Updated',
                'gender' => 2,
            ],
            $testTitle
        );

        return $titleId;
    }

    /**
     * @depends testListTitles
     *
     * @param int $titleId
     *
     * @return int
     */
    public function testDeleteTitle(int $titleId): void
    {
        $return = $this->deleteItem('/title/' . $titleId, ['title_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/title/' . $titleId, ['title_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testDeleteTitle
     *
     * @param int $titleId
     *
     * @return int
     */
    public function testBulkDeleteTitles(): void
    {
        // There are titles in default fixtures
        $titles = $this->listItems('/titles', ['title_read']);
        $this->assertEquals(2, $titles['totalItems']);

        // We create two new titles
        $titleNew1 = $this->requestApi('POST', '/title', null, ['title_write'], Response::HTTP_CREATED, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'names' => [
                        'en-US' => 'name en',
                        'fr-FR' => 'name fr',
                    ],
                    // We use string on purpose because form data are sent like string, thus we validate here that the denormalization still
                    // works with string value (actually we only ignore the wrong type, but it works nonetheless)
                    'gender' => '1',
                ],
            ],
        ]);
        $this->assertArrayHasKey('titleId', $titleNew1);

        $titleNew2 = $this->requestApi('POST', '/title', null, ['title_write'], Response::HTTP_CREATED, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'names' => [
                        'en-US' => 'name en',
                        'fr-FR' => 'name fr',
                    ],
                    // We use string on purpose because form data are sent like string, thus we validate here that the denormalization still
                    // works with string value (actually we only ignore the wrong type, but it works nonetheless)
                    'gender' => '1',
                ],
            ],
        ]);
        $this->assertArrayHasKey('titleId', $titleNew2);

        // There are titles in default fixtures
        $titles = $this->listItems('/titles', ['title_read']);
        $this->assertEquals(4, $titles['totalItems']);

        // We remove the two titles
        $bulkTitles = [
            $titleNew1['titleId'],
            $titleNew2['titleId'],
        ];

        $this->updateItem('/titles/delete', [
            'titleIds' => $bulkTitles,
        ], ['title_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided titles have been removed
        foreach ($bulkTitles as $titleId) {
            $this->getItem('/title/' . $titleId, ['title_read'], Response::HTTP_NOT_FOUND);
        }

        $this->assertEquals(2, $this->countItems('/titles', ['title_read']));
    }
}
