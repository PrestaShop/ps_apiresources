# Multi-shop

Read when the entity has a shop association (`shopIds`) or when the
command / query takes a shop id or a `ShopConstraint`. The conventions of
the module are in [`CONTEXT.md`](./CONTEXT.md).

> **Experimental — feature flag required.** Admin API support for
> multistore is gated behind the `admin_api_multistore` feature flag
> (Advanced Parameters → New & Experimental Features). The conventions
> below describe how endpoints must be written so they behave correctly
> in multistore, but they only take effect at runtime once the flag is
> enabled. Without the flag, multistore context parameters are ignored
> and the API falls back to single-shop behavior.

When PrestaShop's multistore feature is **disabled**, the API
automatically uses the default shop — no extra parameters are needed.

When multistore is **enabled** (and the `admin_api_multistore` feature
flag is on), every API request **must** include a shop context
parameter (otherwise the API returns 400). The `ShopContextListener` in
the Core reads these parameters from the request and builds a
`ShopConstraint` that CQRS commands/queries use:

| Request parameter | Context built | Use case |
|---|---|---|
| `shopId` | `ShopConstraint::shop($id)` | Target a single specific shop |
| `shopGroupId` | `ShopConstraint::shopGroup($id)` | Target all shops in a group |
| `shopIds` (comma-separated or array) | `ShopCollection::shops([...])` | Target a specific set of shops |
| `allShops` (presence is enough, value ignored) | `ShopConstraint::allShops()` | Target all shops |

These are **request-level context parameters** (query string or request
body) that determine *which shop(s) the operation runs against*. They
are distinct from the `shopIds` DTO property described below.

## Shop association property

Entities that have a shop association (e.g. contacts, attributes,
categories) expose a `public array $shopIds` property on the DTO. This
represents the list of shops the entity is associated with, and must be
mapped in both directions:

- **Query mapping** (read): map the Core field (often `associatedShops`,
  `associatedShopIds`, or `shopAssociation`) → API field `shopIds`.
- **Command mapping** (write): map API field `shopIds` → Core parameter
  (often `shopAssociation` or `associatedShopIds`).

If the entity has no shop association, the `shopIds` property must be
absent from the DTO.

On creation, the shops of the request context are the **default** of
`shopIds`, declared with the `defaultValues` extra property
(`'shopIds' => '[_context][shopIds]'`, PrestaShop ≥ 9.2.0; on older
cores the field is required). Never express that default as a
`[_context]` command mapping: it would overwrite an explicit `shopIds`
sent by the client. Both behaviours are tested
([`TESTING.md`](./TESTING.md)). The `shopIds` association is
round-tripped: the write response contains what was sent. An unknown
shop in `shopIds` answers 404 (`ShopNotFoundException`), an invalid one
422 (`ShopException`) — both mapped in `exceptionToStatus`. The 404
needs a core handler that checks the shops exist (`CustomerGroup` does):
the legacy `AbstractObjectModelHandler::associateWithShops()` inserts
with `INSERT IGNORE` and never throws, a core prerequisite to name in
the PR for the domains that use it (`Contact`…). An unsupported shop
context (`InvalidShopConstraintException`, a `ShopException` subclass)
answers 422 like any invalid shop value.

## Passing shop context to CQRS commands/queries

Some commands/queries need the current shop context (e.g. to filter
results by shop). This is done via the special `[_context]` prefix in
mappings, which injects the request's shop context parameters:

```php
// In a CQRSCommandMapping or CQRSQueryMapping:
'[_context][shopId]' => '[shopId]',              // single shop ID (int)
'[_context][shopIds]' => '[shopIds]',            // multiple shop IDs (array)
'[_context][shopConstraint]' => '[shopConstraint]', // full ShopConstraint object
```

Use `[_context][shopConstraint]` when the CQRS command/query accepts a
`ShopConstraint` value object directly (common for Product-related
commands). Use `[_context][shopId]` or `[_context][shopIds]` when the
command expects plain integer IDs. Only map the context when the CQRS
class takes that argument, with its exact type: a context entry on a
query without shop parameter breaks its denormalization, and a plain
`int $shopId` is not a `ShopConstraint`. A query that always runs on all
shops gets no mapping, and its description says that the context
parameters do not restrict it. A command whose repository requires
`shopConstraint->forAllShops()` cannot be exposed from the request
context (core change needed).

See `Product.php`, `Combination.php`, `CombinationList.php`, and
`CustomerGroup.php` for examples of these patterns.
