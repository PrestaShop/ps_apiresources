# Design — Admin API missing endpoints (Tax + Supplier)

Tracking issue: PrestaShop/PrestaShop#39630
Target repo: PrestaShop/ps_apiresources, base branch `dev`
Two independent PRs (one per endpoint).

## Due diligence (why these two are safe)

- Issue #39630 last updated 2026-05-11 and is **stale** (e.g. it lists `GetDiscountTypes`
  as Missing although `DiscountTypeList.php` already exists from merged PR #86).
- Cross-checked against **all open PRs** of `ps_apiresources`: none touch the Tax or
  Supplier resources.
- Verified directly on up-to-date `upstream/dev`:
  - `Tax.php` exposes only create / delete / get / partial-update — no status endpoint.
  - `Supplier/` has no `SupplierDetails` resource and no `/details` operation.
- The stale `missing-endpoints` branch is an old already-merged branch (PR #86), no active work.

## Consistency rule for status endpoints

PrestaShop core has two command families; the API module already mirrors them:

| Family | Core constructor | API convention | Examples |
|---|---|---|---|
| Blind toggle (flips current state) | `($id)` | `PUT .../toggle-status`, empty body | Store, Zone, Supplier |
| Explicit set (idempotent target state) | `($id, $expectedStatus)` | `.../set-status` + `{enabled}` body | TaxRulesGroup, Category, Manufacturer, **Tax** |

`ToggleTaxStatusCommand($taxId, $expectedStatus)` belongs to the **explicit-set** family
(despite its name) → it must receive a boolean, so it follows the `set-status` convention.

## PR 1 — Tax: `PATCH /taxes/{taxId}/set-status`

- File: `src/ApiPlatform/Resources/Tax/Tax.php` — add one `CQRSPartialUpdate` operation,
  mirroring `TaxRulesGroup` `/set-status`:
  - `uriTemplate: '/taxes/{taxId}/set-status'`, `requirements: ['taxId' => '\d+']`
  - `output: false`, `read: false` (HTTP 204)
  - `CQRSCommand: ToggleTaxStatusCommand::class`
  - `CQRSCommandMapping: ['[enabled]' => '[expectedStatus]']`
  - `scopes: ['tax_write']`
  - Reuses the existing `enabled` property of the `Tax` resource as request body.
- Test: add `testSetStatusTax` to `tests/Integration/ApiPlatform/TaxEndpointTest.php`
  + an entry in the unauthorized/scopes data provider.

## PR 2 — Supplier: `GET /suppliers/{supplierId}/details`

- New file: `src/ApiPlatform/Resources/Supplier/SupplierDetails.php`, mirroring
  `Customer/CustomerDetails.php`:
  - `CQRSGet` at `/suppliers/{supplierId}/details`, `requirements: ['supplierId' => '\d+']`
  - `CQRSQuery: GetSupplierForViewing::class`
  - `CQRSQueryMapping: ['[_context][langId]' => '[languageId]']`
    (context language injected into the 2nd constructor arg — pattern taken from `Product.php`)
  - `scopes: ['supplier_read']`
  - `exceptionToStatus: [SupplierNotFoundException::class => 404]`
  - Properties, from the `ViewableSupplier` DTO: `supplierId` (identifier),
    `name` (string), `supplierProducts` (array).
- Test: add `testGetSupplierDetails` to
  `tests/Integration/ApiPlatform/SupplierEndpointTest.php` + scopes data provider entry.

## Out of scope / YAGNI

- No new core CQRS classes (all already exist in core).
- No changes to the stale tracking issue itself.
- Planning docs are not committed into the contribution branches.
