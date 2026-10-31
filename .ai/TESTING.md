# Testing expectations

Read when writing or reviewing an integration test
(`tests/Integration/ApiPlatform/`). The conventions of the module are in
[`CONTEXT.md`](./CONTEXT.md).

Every new endpoint requires an integration test. The test class lives at
`tests/Integration/ApiPlatform/{Entity}EndpointTest.php` and extends
`ApiTestCase` (same directory). Tests should focus on **API behaviour,
contract, and response format** — authentication and OAuth token setup
are handled automatically by the base class.

## Always use the `ApiTestCase` helper methods

Do not call `static::createClient()->request(...)` directly. Use the
helpers below — they build the request, request a Bearer token for the
given scopes (auto-creating an API client that holds them, cached
across tests), and assert the expected HTTP status code.

| Helper                    | HTTP   | Purpose                                       |
|---------------------------|--------|-----------------------------------------------|
| `getItem($url, $scopes)`  | GET    | Fetch a single item                           |
| `createItem($url, $data, $scopes)` | POST | Create an item                         |
| `partialUpdateItem($url, $data, $scopes)` | PATCH | Partial update                   |
| `updateItem($url, $data, $scopes)` | PUT | Full update                               |
| `deleteItem($url, $scopes)` | DELETE | Delete a single item                       |
| `bulkDeleteItems($url, $data, $scopes)` | DELETE | Bulk delete with a payload      |
| `bulkCommandItemsWithExpectedErrors(...)` | any | Bulk command answering 207 (422 on cores before 9.1.2), asserts `errors` |
| `listItems($url, $scopes, $filters)` | GET | Paginated list (asserts envelope)      |
| `countItems($url, $scopes, $filters)` | GET | Paginated list → totalItems only     |
| `requestApi(...)`         | any    | Last resort for cases the others don't fit    |

The item and write helpers and `requestApi()` also accept an
`$expectedHttpCode` parameter when asserting a specific status (e.g.
`HTTP_UNPROCESSABLE_ENTITY` on invalid payloads, `HTTP_NOT_FOUND` after a
delete); `listItems()` and `countItems()` always expect 200, and
`bulkCommandItemsWithExpectedErrors()` picks its own status.
From core 9.2.1, `requestApi()` (so every helper) also asserts that the
`status` of an error payload is the HTTP status of the response; before,
it was always 500 whatever the `exceptionToStatus` mapping.

## Default test environment

`ApiTestCase::setUpBeforeClass()` already:

- Forces `PS_ADMIN_API_FORCE_DEBUG_SECURED = 0`
- Resets API clients and languages
- **Installs `fr-FR` as a second language** so every endpoint is tested
  against a multi-language environment

The suite boots like `admin-api/index.php`: **no employee in the legacy
context** (no `_PS_ADMIN_DIR_`), and `requestApi()` resets the legacy
static caches and clears the employee / cart of the context before each
request. An endpoint that only works with an employee in the context is
broken for real API clients; a test must not add one. The command is
fixed in the core to work with an API client, recording the API client
with `MutationTracker::addMutationForApiClient()` when the action needs a
history of who performed it (an edge case: `UpdateOrderStatusHandler`).

A test class never repeats this setup: its `setUpBeforeClass()` calls
the parent, restores its tables and pre-creates its API client, nothing
else; its `tearDownAfterClass()` calls the parent and restores the
tables. The `LanguageResetter::resetLanguages()` and
`addLanguageByLocale('fr-FR')` calls still found in older classes are
residue, not a precedent.

Every endpoint handling localized fields is tested with both languages:
each localized value of the fixtures and of the expected payloads
carries `en-US` and `fr-FR`, in the writes and in the reads. A test that
sends or asserts one language does not exercise the behaviour the module
must handle and is a finding.

Static helpers available for additional setup (call from
`setUpBeforeClass` when needed):

- `addLanguageByLocale($locale)` — install another language
- `addShopGroup($name, $color?)` / `addShop($name, $groupId, $color?)`
  — build a multi-shop fixture. Once multistore is active, every API
  request answers 404 until the class also sets
  `MultistoreConfig::FEATURE_STATUS` and enables
  `FeatureFlagSettings::FEATURE_FLAG_ADMIN_API_MULTISTORE`, reset with
  `FeatureFlagResetter` (`CustomerGroupMultiShopEndpointTest`)
- `updateConfiguration($key, $value, $shopConstraint?)` — override a
  configuration value for the test
- `createApiClient([...])` — called from `setUpBeforeClass` with every
  scope the class uses (the convention of the existing suites, it
  pre-warms the token); `getBearerToken` would create one on the fly
  otherwise

## Test structure

- An upload endpoint adds the cases of [`UPLOAD.md`](./UPLOAD.md) →
  "Tests" (multipart request, stored file, JSON alternative).
- Implement `getProtectedEndpoints()` listing every operation that
  requires authentication so the base class can verify scope
  enforcement via `testProtectedEndpoints`.
- Chain CRUD test methods with `@depends` so each step builds on the
  previous one's created entity; identifiers come from the responses,
  never a hard-coded `1` unless it is a known fixture.
- **Assert the whole response**: one `assertEquals` of the full expected
  array against the response (every field, nested objects, list items,
  the `totalItems` / `orderBy` / `filters` of a list). Key-presence or
  field-by-field assertions are rejected: they miss removed fields and
  hide unexpected new ones — a new field must show up as a test change.
  The expected payload of a fixture is built by one private helper
  (`expectedXxx(int $id, array $overrides = [])` with `array_replace`)
  reused by every step of the `@depends` chain, instead of duplicated
  literal arrays. The whole envelope is asserted on a list the test
  fully controls (an exact filter on its own fixture); a list other test
  methods contribute to gets a shape assertion and `assertContains`,
  never a hard-coded count. Dynamic values (dates, display amounts) get a
  format assertion, then are copied into the expected array. GET after
  every write; a partial update leaves the other fields untouched, and
  the languages it does not send (`CONTEXT.md` → "Choosing the
  operation").
- Fixtures are created **through the API** (the endpoints under test or
  of the same domain), not with `ObjectModel` or SQL. When the endpoint
  that would create the fixture is missing, add it in the same domain
  PR, or leave a `@todo` naming it.
- Error cases: a 404 test for every mapped `*NotFoundException` on every
  operation (GET included, `testGetNonExistent{Entity}` with `99999`),
  plus GET after delete; `/x/0` → 422 when the value object rejects `0`,
  otherwise the answer of an unknown id; a `testInvalid…` method with
  `$this->assertValidationErrors([...], $response)` for the 422 produced
  by the **constraints** (the response carries the list of invalid
  fields), with one case per nullable field, per partial-update
  combination, empty values and an empty bulk list; when a 422 path is
  unreachable, a one-line comment says why. A 422 produced by a mapped
  CQRS exception (an invalid value object such as
  `OrderReturnStateException`) has no field list: assert the status, not
  `assertValidationErrors`.
- Bulk operations: one 207 test per bulk command with an unknown id in
  the batch, through `bulkCommandItemsWithExpectedErrors()` (it asserts
  `errors` and expects 422 on cores before 9.1.2), or a whole-envelope
  `assertEquals` when the type or the message matter; one 422 test with a
  non-integer element (`["3abc"]` → `propertyPath` `countryIds[0]`).
- A bug fix comes with a test that fails without the fix (revert it to
  check).
- A write test verifies the effect, not only the status: the entity is
  read back, the side effect the command promises is checked (restocked
  quantity, position shift), a cumulative action is called twice with
  different values. Test data has a known state: a dedicated fixture with
  expected content rather than a demo-data snapshot, values checked
  against the core constants.
- Lists: `listItems()` with the API field names, asserting the filtered
  items and the envelope. Its third argument is sent as `filters[...]`;
  `orderBy`, `sortOrder`, `limit` and `offset` go in the URL
  (`CountryEndpointTest`).
- Shop association: both behaviours when `shopIds` exists (no `shopIds`
  → context shops, explicit list kept), an unknown shop → 404, an
  invalid one → 422.
- `declare(strict_types=1)` must be present in the test file.
- Restore relevant DB tables via `DatabaseDump::restoreTables([...])`
  in `setUpBeforeClass` / `tearDownAfterClass` (include `_lang` table
  if localized, `_shop` table if shop-associated), never with manual
  SQL — it also runs after a failure; `ResourceResetter` for module
  files. A per-domain resetter of the core test resources
  (`ProductResetter`, `ShopResetter`) is used when one exists, once
  checked to restore what the test changes on every core version the
  test covers.

## Canonical tests

Start from one of these, never from a blank file or an older test:

- `tests/Integration/ApiPlatform/CountryEndpointTest.php` — the
  structure: the protected endpoints, the add → get → update → list →
  delete chain with whole-payload assertions in both languages, the
  invalid payloads field by field, 404 / 422 on ids, the business
  refusals, the toggle, the bulk operations and their 207 details, the
  contract of each core version, the shop association, the fixtures
  through the API;
- `tests/Integration/ApiPlatform/CustomerGroupEndpointTest.php` — the
  404 on an unknown shop of `shopIds`, when the core handler checks it.

Adapt: the tables of `resetTables()`, the scopes of `createApiClient()`,
every operation in `getProtectedEndpoints()`, the method names after the
endpoint's own actions, the fixtures and the expected payloads; remove
the copy-paste residue (labels, comments, setup blocks of the other
domain).

## Version-dependent endpoints

`ApiTestCase` provides helpers for endpoints that declare a
`minVersion` (see [`VERSIONS.md`](./VERSIONS.md)) — always use them, never
recode `version_compare` checks in test classes:

- `markTestSkippedByMinVersion('9.2.0')` — call at the start of a test
  method to skip it on older cores
- `isVersionAtLeast('9.2.0')` / `isVersionUnder('9.2.0')` — boolean
  checks for conditional logic (data providers, assertions)

Two patterns, depending on the scope of the constraint:

- **Every operation of the class is version-constrained** → skip the
  whole class from `setUpBeforeClass()`, *before* calling
  `parent::setUpBeforeClass()` (so no fixture setup runs either):

  ```php
  public static function setUpBeforeClass(): void
  {
      if (self::isVersionUnder('9.2.0')) {
          static::markTestSkipped('This endpoint requires PrestaShop 9.2.0');

          return;
      }

      parent::setUpBeforeClass();
      // ...
  }
  ```

  `getProtectedEndpoints()` must still yield its data sets
  **unconditionally**: data providers are resolved when PHPUnit builds
  the test suite, before `setUpBeforeClass()` gets a chance to skip the
  class, and an empty provider is reported as an error. The yielded
  data sets are never executed on old cores since the class is skipped.
  Examples: `TaxRuleEndpointTest`, `EmployeePasswordResetEndpointTest`,
  `ProductStockEndpointTest`.

- **Only part of the class is version-dependent** (the endpoint exists
  on all supported cores but some behaviour doesn't) → call
  `$this->markTestSkippedByMinVersion('9.2.0')` at the start of the
  affected tests (tests that `@depends` on them are skipped
  automatically), and guard conditional yields or assertions with
  `isVersionAtLeast()`. A conditional yield in `getProtectedEndpoints()`
  is fine here as long as at least one data set is always yielded.
  Examples: `CustomerCartsEndpointTest`,
  `ProductStockMovementsEndpointTest`; `ProductFeatureValueListEndpointTest`
  also asserts the 405 of its write operations on the older cores, which
  proves the resource still loads there.

A test whose expected value depends on a core fix that is not released
yet is gated with `isVersionAtLeast()` on the version that will carry
the fix (`git tag --contains <sha>` gives the first tag of a merged
commit), and the module PR names the core PR: the CI job of a branch is
red until the core change reaches it, and the module PR waits for it (see
`CONTEXT.md` → "Working with the core"). The assertion is not weakened to
make the job green, and
when an operation available on several cores behaves differently on the
older ones, even subtly, the test asserts the contract of each version
instead of skipping (`CountryEndpointTest`: the duplicate ISO code
created before 9.2.0, the unknown zone stored, the default country
answering 500, a 3-letter ISO code cut to 2 letters on creation and the
list envelope without `offset` on the first page before 9.2.1). An
operation gated with `minVersion` is skipped on the older cores, its 404
there is not tested (`VersionedEndpointsTest` covers the filtering). In
a class that mixes gated and ungated operations, the gated entries of
`getProtectedEndpoints()` are yielded only with `isVersionAtLeast()`: an
older core answers 404, not 401, for a filtered operation. When a PR
removes a workaround, the test cases the workaround had disabled come
back.

## Running the suite

```bash
composer setup-local-tests   # first time only
composer run-module-tests
```

From the core checkout (the module symlinked in `modules/`):
`composer api-module-tests-local` (full setup) then
`composer run-api-module-tests-local`, or one class with
`_PS_ROOT_DIR_=$(pwd) php -d date.timezone=UTC ./vendor/phpunit/phpunit/phpunit -c modules/ps_apiresources/tests/Integration/phpunit-local.xml --filter=ClassName`
(`_PS_ROOT_DIR_` on every command: PHPUnit cannot find the core root
when the module is a symlink). The core API suite and the module suite
share `var/cache/test`: clear it when switching from one to the other.
