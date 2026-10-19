# List endpoints

Read when an operation is a `PaginatedList`, a `CQRSPaginate` or a
`CQRSGetCollection`. The conventions of the module are in
[`CONTEXT.md`](./CONTEXT.md).

## Choosing the collection operation

- `PaginatedList` when the back office lists the entity through a grid:
  the operation wraps the grid data factory (`gridDataFactory`) and its
  `Filters` class.
- `CQRSPaginate` when a CQRS list query exists and no grid does: the
  query result holds the items and the count (`itemsField` / `countField`).
- `CQRSGetCollection` only when the handler returns a plain array, without
  pagination; a result wrapped in an object becomes a `CQRSGet` with an
  array property. A result indexed by id answers a JSON list with
  `extraProperties: ['collectionAsList' => true]` (core 9.2.1).
- `offset` / `limit` are exposed when the query supports them, never
  capped silently at a core default.

## Grid data factory and filters

- To find the correct `gridDataFactory` service name: look at the
  entity's Symfony controller → Grid factory definition → Grid data
  factory service.
- A list declares both `filtersClass` (the core `Filters` subclass:
  default `orderBy`, default `limit`) and `filtersMapping` (API field →
  grid or query field, used for `orderBy` and `filters[...]`), for a
  `PaginatedList` and a `CQRSPaginate` alike (both go through
  `QueryListProvider`); without them `orderBy` on an API field name
  answers 500 on a grid, is silently ignored on a CQRS query, and the
  filters are silently ignored. A CQRS list query whose constructor
  names the list parameters differently (`orderWay` for `sortOrder`)
  maps them in `CQRSQueryMapping`, otherwise the sorting is echoed in the
  envelope and never applied.
- A `filtersMapping` entry also serves `orderBy`, so it stays even when
  the grid's query builder does not filter on that field; the core then
  ignores that filter silently, and the operation description says which
  filters are supported. See
  `src/ApiPlatform/Resources/Customer/CustomerGroupList.php`.

## Mapping the items

`ApiResourceMapping` ([`CONTEXT.md`](./CONTEXT.md) → "Field mapping")
renames the item fields of a `PaginatedList` / `CQRSPaginate` (grid data
factory fields or CQRS query result fields → API fields). For
`PaginatedList`, the source field names come from the query builder
service wired through `gridDataFactory`.

**Field alignment** — the DTO properties must mirror the fields selected
by the grid's underlying query builder. To verify alignment:

1. Trace the `gridDataFactory` service to its query builder class in
   PrestaShop Core (the class implementing `DoctrineQueryBuilderInterface`
   or similar).
2. Read the SQL `SELECT` clause — each selected column is a field the
   grid can return.
3. Every selected field should have a corresponding DTO property. If a
   field is intentionally omitted, it should be a conscious decision
   (not an oversight).
4. When the SQL column name differs from the DTO property name (e.g.
   `id_contact` vs `contactId`, `firstname` vs `firstName`), an
   `ApiResourceMapping` entry must cover the rename.
5. A DTO property with no matching query field will always be `null` at
   runtime — this is almost certainly a bug.

The same principle applies to `CQRSPaginate`: compare the CQRS query's
result DTO fields against the ApiResource properties. The format of the
localized fields of a list (one language) is in `CONTEXT.md` →
"Property naming".

## Envelope and documentation

The paginated envelope is `{totalItems, orderBy, sortOrder, limit,
offset, filters, items}` (`offset` an integer, `items` a JSON list;
`orderBy` is absent when the request sends none and `filters` is `[]`
when empty, a core behaviour the tests account for). The
core documents the `limit` / `offset` / `orderBy` / `sortOrder` /
`filters[...]` parameters and the envelope from 9.2.1, for both
`PaginatedList` and `CQRSPaginate` (the detection is on the
`QueryListProvider`). A `CQRSGetCollection` answers a JSON array. The
core UI tests (`tests/UI/campaigns/functional/API/` in the PrestaShop
repository) assert the exact keys of the envelope.

## Tests

See [`TESTING.md`](./TESTING.md) → "Test structure" (lists).

## Examples

- Grid-sourced list: `src/ApiPlatform/Resources/Country/CountryList.php`
  (`PaginatedList`, `gridDataFactory`, `filtersClass`, `filtersMapping`,
  `ApiResourceMapping`, the filters in the operation description).
- CQRS-sourced list: `src/ApiPlatform/Resources/Product/CombinationList.php`
  (`CQRSPaginate` with `CQRSQuery`, `CQRSQueryMapping`, `ApiResourceMapping`,
  `itemsField`, `countField`, `DecimalNumber` properties).
- Non-paginated collection indexed by id in the core:
  `src/ApiPlatform/Resources/Customer/FoundCustomer.php` (`CQRSGetCollection`
  with `collectionAsList`).
