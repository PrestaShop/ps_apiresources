# Core version constraints

Read when a CQRS class or a core behaviour an endpoint relies on is missing
or different on one of the supported cores. The conventions of the module
are in [`CONTEXT.md`](./CONTEXT.md); the reference branch to read is defined
in `CONTEXT.md` → "Working with the core"; the test side (skips, version-
dependent expectations) is in [`TESTING.md`](./TESTING.md) → "Version-dependent
endpoints".

The supported cores (`CONTEXT.md` → "Core versions") are the CI matrix
of `.github/workflows/integration.yml`. Some endpoints rely on
CQRS classes or core behaviour that only exist (or only work) from a
given PrestaShop version. Declare that constraint on the operation with
the `minVersion` / `maxVersion` extra properties:

```php
new CQRSPartialUpdate(
    uriTemplate: '/products/{productId}/stock',
    // ...
    extraProperties: [
        'minVersion' => '9.2.0',
    ],
),
```

On a core outside the declared range the operation is filtered out of
the API entirely: requests return 404 and the operation disappears from
the OpenAPI documentation. The filtering is handled by the module's
`CoreVersionCompatibilityMetadataCollectionFactoryDecorator` on
PrestaShop < 9.2 and by its core twin on >= 9.2; both compare against
`PrestaShop\PrestaShop\Core\Version::VERSION` with inclusive
`version_compare` semantics.

Independently of `minVersion`, the core drops, operation by operation,
every operation whose `CQRSQuery` / `CQRSCommand` class or
`gridDataFactory` service does not exist
(`CQRSNotFoundMetadataCollectionFactoryDecorator`,
`ApiResourceScopesExtractor`): the other operations of the class stay,
the route answers 404 and the scope disappears. That filtering is off
when the experimental endpoints are enabled (the missing class then
fails at runtime), and PHPStan on the older legs needs the scoped
`class.notFound` ignore either way. `minVersion` adds what the automatic
filtering cannot: a class that exists but misbehaves, a gate the tests
and the documentation know (`isVersionAtLeast`, the version in the
description).

Use it when the backing CQRS command/query does not exist on older
supported cores, or exists but misbehaves there (e.g. the stock update
endpoints require 9.2.0 because older cores cannot create stock
movements for API clients). Real examples:
`src/ApiPlatform/Resources/Product/ProductStock.php`,
`src/ApiPlatform/Resources/TaxRule/TaxRuleList.php`. The filtering
itself is covered end-to-end by
`tests/Integration/ApiPlatform/VersionedEndpointsTest.php`.

A version-constrained endpoint needs matching test handling (see
`TESTING.md` → "Version-dependent endpoints"). Three limits:

- `minVersion` documents a real dependency (a CQRS class, a setter, a
  cast that only exists from that version). It never hides a 500 on an
  older core: a bug there is fixed in the core, on the lowest branch. A
  guard or a check that a later core adds is no dependency either: the
  endpoint stays available, its description states the difference per
  version and the test asserts the contract of each version
  (`Country`: duplicate ISO code, unknown zone, default country).
- An endpoint already shipped in a tagged release that gets gated behind
  a newer version disappears from the older cores. Weigh the loss, keep
  the operations that work everywhere (e.g. `POST /customers/{customerId}/carts`
  kept for every core while `GET` needs 9.2.1), and list it as a BC break.
- A prerequisite that lands in `develop` gates the endpoint on the next
  minor (`CONTEXT.md` → "Working with the core"): when a solution works
  on every supported core, prefer it (a configuration-level fix, a
  literal `Choice` list rather than an enum only the newer core has).

The named operation arguments (`minVersion`, `maxVersion`,
`defaultValues`…) are copied by the core into `extraProperties`, but a
core that does not know an argument rejects it: pass the ones younger
than 9.0.3 through `extraProperties: [...]` so the resource still loads
there (`experimentalOperation` is a named argument since 9.0.0 and
needs no such care).

## Features per core version

The full list is in the core context (`.ai/Component/AdminAPI/CONTEXT.md`
of the PrestaShop repository → "What changed per core version"). The ones
an endpoint depends on most often:

| Core | Feature |
|------|---------|
| 9.0.2 | `@index` placeholders in mappings; `CQRSDelete` with a `CQRSCommand` and `allowEmptyBody` |
| 9.0.3 | `CQRSPaginate` |
| 9.1.2 | 207 responses of the bulk commands (`AbstractBulkCommandHandler`, `BulkCommandExceptionNormalizer`) |
| 9.2.0 | `minVersion` / `maxVersion` filtering in the core; `defaultValues`; `ScalarFirstUnionTypeExtractor`; request bodies documented from the commands |
| 9.2.1 | Booleans of every query result cast at every level (nested DTOs, `@var Foo[]` lists); `@var Foo[]` / `@var int[]` item types read from the PHPDoc; `collectionAsList`; paginated envelope with an integer `offset` and documented list parameters; class PHPDoc no longer published |
