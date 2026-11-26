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

class OrderStateEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['order_state_read', 'order_state_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'order_state',
            'order_state_lang',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/order-states/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/order-states',
        ];

        yield 'patch endpoint' => [
            'PATCH',
            '/order-states/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/order-states',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/order-states/1',
        ];
    }

    private function getCreateData(): array
    {
        return [
            'names' => [
                'en-US' => 'Awaiting review EN',
                'fr-FR' => 'Awaiting review FR',
            ],
            'templates' => [
                'en-US' => '',
                'fr-FR' => '',
            ],
            'color' => '#4169E1',
            'loggable' => false,
            'invoice' => false,
            'hidden' => false,
            'sendEmail' => false,
            'pdfInvoice' => false,
            'pdfDelivery' => false,
            'shipped' => false,
            'paid' => false,
            'delivery' => false,
        ];
    }

    /**
     * The create operation replays GetOrderStateForEditing, so it returns the whole entity and
     * not just its id. Asserting the complete structure here is what pins that contract.
     */
    private function getExpectedOrderState(int $orderStateId, array $data): array
    {
        return array_merge($data, [
            'orderStateId' => $orderStateId,
            // Order states are soft deleted, a freshly created one is never flagged
            'deleted' => false,
        ]);
    }

    public function testAddOrderState(): int
    {
        $orderState = $this->createItem('/order-states', $this->getCreateData(), ['order_state_write']);
        $this->assertArrayHasKey('orderStateId', $orderState);
        $orderStateId = $orderState['orderStateId'];

        $this->assertEquals(
            $this->getExpectedOrderState($orderStateId, $this->getCreateData()),
            $orderState
        );

        return $orderStateId;
    }

    /**
     * @depends testAddOrderState
     */
    public function testGetOrderState(int $orderStateId): int
    {
        $orderState = $this->getItem('/order-states/' . $orderStateId, ['order_state_read']);

        // The GET must return exactly what the POST returned
        $this->assertEquals(
            $this->getExpectedOrderState($orderStateId, $this->getCreateData()),
            $orderState
        );

        return $orderStateId;
    }

    /**
     * @depends testGetOrderState
     */
    public function testPartialUpdateOrderState(int $orderStateId): int
    {
        $patchData = [
            'names' => [
                'en-US' => 'Updated status EN',
                'fr-FR' => 'Updated status FR',
            ],
            'color' => '#32CD32',
        ];

        $expected = $this->getExpectedOrderState(
            $orderStateId,
            array_merge($this->getCreateData(), $patchData)
        );

        // The partial update returns the updated entity through the same query as the GET
        $updatedOrderState = $this->partialUpdateItem('/order-states/' . $orderStateId, $patchData, ['order_state_write']);
        $this->assertEquals($expected, $updatedOrderState);

        // And a subsequent GET returns exactly the same thing
        $this->assertEquals($expected, $this->getItem('/order-states/' . $orderStateId, ['order_state_read']));

        return $orderStateId;
    }

    /**
     * @depends testPartialUpdateOrderState
     */
    public function testListOrderStates(int $orderStateId): int
    {
        // The whole envelope is asserted on a list the test fully controls: an exact filter on its own fixture
        $filtered = $this->listItems('/order-states?orderBy=orderStateId&sortOrder=desc', ['order_state_read'], ['orderStateId' => $orderStateId]);
        $expected = [
            'totalItems' => 1,
            'orderBy' => 'orderStateId',
            'sortOrder' => 'desc',
            'limit' => 50,
            'offset' => 0,
            'filters' => ['orderStateId' => $orderStateId],
            'items' => [
                [
                    'orderStateId' => $orderStateId,
                    'name' => 'Updated status EN',
                    'color' => '#32CD32',
                    'sendEmail' => false,
                    'delivery' => false,
                    'invoice' => false,
                    'template' => '',
                    'unremovable' => false,
                ],
            ],
        ];
        if (self::isVersionUnder('9.2.2')) {
            // The first page has a null offset the serializer drops, PrestaShop 9.2.2 returns 0
            unset($expected['offset']);
        }
        $this->assertEquals($expected, $filtered);

        // The boolean filter matches exactly
        $withoutEmail = $this->listItems('/order-states', ['order_state_read'], ['orderStateId' => $orderStateId, 'sendEmail' => false]);
        $this->assertEquals(1, $withoutEmail['totalItems']);
        $withEmail = $this->listItems('/order-states', ['order_state_read'], ['orderStateId' => $orderStateId, 'sendEmail' => true]);
        $this->assertEquals(0, $withEmail['totalItems']);

        return $orderStateId;
    }

    /**
     * @depends testListOrderStates
     */
    public function testDeleteOrderState(int $orderStateId): void
    {
        $return = $this->deleteItem('/order-states/' . $orderStateId, ['order_state_write']);
        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);

        // Order states are soft deleted (existing orders may reference them): the record stays
        // readable and editable, flagged as deleted, and leaves the listing
        $expected = $this->getExpectedOrderState($orderStateId, array_merge($this->getCreateData(), [
            'names' => [
                'en-US' => 'Updated status EN',
                'fr-FR' => 'Updated status FR',
            ],
            'color' => '#32CD32',
        ]));
        $expected['deleted'] = true;
        $this->assertEquals($expected, $this->getItem('/order-states/' . $orderStateId, ['order_state_read']));
        $this->assertEquals(0, $this->countItems('/order-states', ['order_state_read'], ['orderStateId' => $orderStateId]));

        $expected['color'] = '#FF8C00';
        $this->assertEquals($expected, $this->partialUpdateItem('/order-states/' . $orderStateId, ['color' => '#FF8C00'], ['order_state_write']));
        $this->assertEquals($expected, $this->getItem('/order-states/' . $orderStateId, ['order_state_read']));

        // Deleting it again is accepted and changes nothing
        $this->assertNull($this->deleteItem('/order-states/' . $orderStateId, ['order_state_write']));
        $this->assertEquals($expected, $this->getItem('/order-states/' . $orderStateId, ['order_state_read']));
    }

    public function testDeleteNativeOrderState(): void
    {
        // The native states are unremovable: DeleteOrderStateException
        $this->deleteItem('/order-states/1', ['order_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertFalse($this->getItem('/order-states/1', ['order_state_read'])['deleted']);
    }

    public function testUnknownOrderState(): void
    {
        $this->getItem('/order-states/999999', ['order_state_read'], Response::HTTP_NOT_FOUND);
        $this->partialUpdateItem('/order-states/999999', ['color' => '#111111'], ['order_state_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/order-states/999999', ['order_state_write'], Response::HTTP_NOT_FOUND);

        // OrderStateId accepts 0, which is then unknown
        $this->getItem('/order-states/0', ['order_state_read'], Response::HTTP_NOT_FOUND);
        $this->partialUpdateItem('/order-states/0', ['color' => '#111111'], ['order_state_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/order-states/0', ['order_state_write'], Response::HTTP_NOT_FOUND);
    }

    public function testAddOrderStateWithTemplates(): void
    {
        $createData = array_merge($this->getCreateData(), [
            'sendEmail' => true,
            'templates' => [
                'en-US' => 'payment',
                'fr-FR' => 'payment',
            ],
        ]);
        $orderState = $this->createItem('/order-states', $createData, ['order_state_write']);
        $orderStateId = $orderState['orderStateId'];
        $expected = $this->getExpectedOrderState($orderStateId, $createData);
        $this->assertEquals($expected, $orderState);
        $this->assertEquals($expected, $this->getItem('/order-states/' . $orderStateId, ['order_state_read']));

        // The templates are only stored when sendEmail is sent in the same request
        $this->partialUpdateItem('/order-states/' . $orderStateId, [
            'templates' => ['en-US' => 'shipped', 'fr-FR' => 'shipped'],
        ], ['order_state_write']);
        $this->assertEquals($expected, $this->getItem('/order-states/' . $orderStateId, ['order_state_read']));

        $patchData = [
            'sendEmail' => true,
            'templates' => ['en-US' => 'shipped', 'fr-FR' => 'shipped'],
        ];
        $expected = $this->getExpectedOrderState($orderStateId, array_merge($createData, $patchData));
        $this->assertEquals($expected, $this->partialUpdateItem('/order-states/' . $orderStateId, $patchData, ['order_state_write']));
        $this->assertEquals($expected, $this->getItem('/order-states/' . $orderStateId, ['order_state_read']));
    }

    public function testAddOrderStateSendingEmailsWithoutTemplates(): void
    {
        $createData = array_merge($this->getCreateData(), ['sendEmail' => true]);

        if (!self::isVersionAtLeast('9.2.2')) {
            // Older cores do not check the templates on creation (only on update), see PrestaShop/PrestaShop#43382
            $this->createItem('/order-states', $createData, ['order_state_write']);

            return;
        }

        // Sending e-mails needs a template in every language: MissingOrderStateRequiredFieldsException
        $this->createItem('/order-states', $createData, ['order_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFileInformationIsNotUploaded(): void
    {
        $orderState = $this->createItem('/order-states', $this->getCreateData(), ['order_state_write']);
        $orderStateId = $orderState['orderStateId'];
        $fileInformation = ['pathName' => '/etc/hostname', 'fileSize' => 10, 'mimeType' => 'image/gif', 'originalName' => 'icon.gif'];

        // Not documented, but EditOrderStateCommand::setFileInformation() still receives it: move_uploaded_file()
        // ignores a path that is not an upload, so the other fields are saved and no icon is written
        $expected = $this->getExpectedOrderState($orderStateId, array_merge($this->getCreateData(), ['color' => '#32CD32']));
        $this->assertEquals($expected, $this->partialUpdateItem('/order-states/' . $orderStateId, [
            'color' => '#32CD32',
            'fileInformation' => $fileInformation,
        ], ['order_state_write']));
        $this->assertEquals($expected, $this->getItem('/order-states/' . $orderStateId, ['order_state_read']));
        $this->assertFileDoesNotExist(_PS_ORDER_STATE_IMG_DIR_ . $orderStateId . '.gif');

        // A size above the max upload size is refused (sent alone: the handler saves the other fields before the upload fails)
        $this->partialUpdateItem('/order-states/' . $orderStateId, [
            'fileInformation' => array_merge($fileInformation, ['fileSize' => PHP_INT_MAX]),
        ], ['order_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertEquals($expected, $this->getItem('/order-states/' . $orderStateId, ['order_state_read']));
    }

    public function testPartialUpdateOfOneLanguageKeepsTheOthers(): void
    {
        // Older cores replace the whole localized name on edit, so the default language ends up empty
        // (see PrestaShop/PrestaShop#43268)
        $this->markTestSkippedByMinVersion('9.2.2');

        $orderState = $this->createItem('/order-states', $this->getCreateData(), ['order_state_write']);
        $orderStateId = $orderState['orderStateId'];

        $patchData = [
            'names' => [
                'fr-FR' => 'Seulement FR',
            ],
        ];
        $expected = $this->getExpectedOrderState($orderStateId, array_merge($this->getCreateData(), [
            'names' => [
                'en-US' => 'Awaiting review EN',
                'fr-FR' => 'Seulement FR',
            ],
        ]));
        $this->assertEquals($expected, $this->partialUpdateItem('/order-states/' . $orderStateId, $patchData, ['order_state_write']));
        $this->assertEquals($expected, $this->getItem('/order-states/' . $orderStateId, ['order_state_read']));
    }

    public function testAddOrderStateWithDefaultValues(): void
    {
        $createData = [
            'names' => [
                'en-US' => 'Minimal state EN',
                'fr-FR' => 'Minimal state FR',
            ],
            'color' => '#123456',
        ];

        if (!self::isVersionAtLeast('9.2.0')) {
            // The defaultValues extra property is ignored before 9.2.0, the fields stay required
            $this->createItem('/order-states', $createData, ['order_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

            return;
        }

        $orderState = $this->createItem('/order-states', $createData, ['order_state_write']);
        $this->assertEquals(
            $this->getExpectedOrderState($orderState['orderStateId'], array_merge($this->getCreateData(), $createData)),
            $orderState
        );
    }

    public function testInvalidOrderStateValues(): void
    {
        $invalidData = $this->getCreateData();
        $invalidData['names'] = [
            'en-US' => '<b>Bold</b>',
            'fr-FR' => str_repeat('a', 65),
        ];
        $invalidData['templates'] = [
            'en-US' => 'not a template',
            'fr-FR' => str_repeat('a', 65),
        ];
        $invalidData['color'] = '#zz';

        $validationErrorsResponse = $this->createItem('/order-states', $invalidData, ['order_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'names[en-US]'],
            ['propertyPath' => 'names[fr-FR]'],
            ['propertyPath' => 'templates[en-US]'],
            ['propertyPath' => 'templates[fr-FR]'],
            ['propertyPath' => 'color'],
        ], $validationErrorsResponse);

        $invalidData = $this->getCreateData();
        $invalidData['names'] = ['en-US' => 17, 'fr-FR' => 'Nom FR'];
        $invalidData['sendEmail'] = true;
        $invalidData['templates'] = ['en-US' => 'payment', 'fr-FR' => null];

        $validationErrorsResponse = $this->createItem('/order-states', $invalidData, ['order_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'names[en-US]'],
            ['propertyPath' => 'templates[fr-FR]'],
        ], $validationErrorsResponse);
    }

    public function testInvalidOrderStateUpdate(): void
    {
        $orderState = $this->createItem('/order-states', $this->getCreateData(), ['order_state_write']);
        $orderStateId = $orderState['orderStateId'];

        // The default language cannot be emptied on update (a payload without it is a partial update
        // of the other languages, which DefaultLanguage accepts)
        $validationErrorsResponse = $this->partialUpdateItem('/order-states/' . $orderStateId, [
            'names' => ['en-US' => '', 'fr-FR' => 'Nom FR'],
        ], ['order_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'names'],
        ], $validationErrorsResponse);

        $validationErrorsResponse = $this->partialUpdateItem('/order-states/' . $orderStateId, [
            'names' => ['en-US' => null, 'fr-FR' => null],
        ], ['order_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'names[en-US]'],
            ['propertyPath' => 'names[fr-FR]'],
        ], $validationErrorsResponse);

        $validationErrorsResponse = $this->partialUpdateItem('/order-states/' . $orderStateId, [
            'names' => ['en-US' => 17],
            'sendEmail' => true,
            'templates' => ['en-US' => 'payment', 'fr-FR' => null],
        ], ['order_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'names[en-US]'],
            ['propertyPath' => 'templates[fr-FR]'],
        ], $validationErrorsResponse);

        $validationErrorsResponse = $this->partialUpdateItem('/order-states/' . $orderStateId, [
            'sendEmail' => true,
            'templates' => ['en-US' => 'payment', 'fr-FR' => 7],
        ], ['order_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'templates[fr-FR]'],
        ], $validationErrorsResponse);

        // Sending e-mails needs a template in every language: MissingOrderStateRequiredFieldsException
        $this->partialUpdateItem('/order-states/' . $orderStateId, [
            'sendEmail' => true,
        ], ['order_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testInvalidOrderState(): void
    {
        $invalidData = $this->getCreateData();
        $invalidData['names'] = [
            'fr-FR' => 'Nom FR uniquement',
        ];
        $invalidData['color'] = '';

        $validationErrorsResponse = $this->createItem('/order-states', $invalidData, ['order_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);

        $this->assertValidationErrors([
            [
                'propertyPath' => 'names',
                'message' => 'The field names is required at least in your default language.',
            ],
            [
                'propertyPath' => 'color',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);
    }
}
