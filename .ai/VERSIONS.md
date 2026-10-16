# Core version constraints

Read when a CQRS class or a core behaviour an endpoint relies on is missing
or different on one of the supported cores. The conventions of the module
are in [`CONTEXT.md`](./CONTEXT.md); the reference branch to read is defined
in `CONTEXT.md` → "Working with the core"; the test side (skips, version-
dependent expectations) is in [`TESTING.md`](./TESTING.md) → "Version-dependent
endpoints".

The module runs on PrestaShop 9.0.3, 9.1.5, 9.2.x and develop (the CI
matrix of `.github/workflows/integration.yml`). Some endpoints rely on
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
  older core: a bug there is fixed in the core, on the lowest branch.
- An endpoint already shipped in a tagged release that gets gated behind
  a newer version disappears from the older cores. Weigh the loss, keep
  the operations that work everywhere (e.g. `POST /customers/{customerId}/carts`
  kept for every core while `GET` needs 9.2.1), and list it as a BC break.
- A core prerequisite that is not a bug fix lands in `develop` and gates
  the endpoint on the next minor (`minVersion: 9.3.0`): when a
  configuration-level solution works on every supported core, prefer it.
  Prefer code that works on the oldest core (a literal `Choice` list
  rather than an enum only the newer core has).

The named operation arguments (`minVersion`, `maxVersion`,
`experimentalOperation`, `defaultValues`…) are copied by the core into
`extraProperties`, but a core that does not know an argument rejects
it: pass the recent ones through `extraProperties: [...]` so the
resource still loads on 9.0.3.

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
