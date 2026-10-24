# Errors, validation and status codes

Read when touching `exceptionToStatus`, a constraint, a validation group,
a bulk operation, or when a request answers a status that was not
expected. The conventions of the module are in [`CONTEXT.md`](./CONTEXT.md);
the test side (error cases to cover) is in [`TESTING.md`](./TESTING.md).

## Exception mapping

Define `exceptionToStatus` for every exception the operations can
raise, with the `Response::HTTP_*` constants — at minimum the entity's
`…ConstraintException` → `HTTP_UNPROCESSABLE_ENTITY` (422) and
`…NotFoundException` → `HTTP_NOT_FOUND` (404), plus:

- the exceptions of the value objects built from the URI or the body:
  `/x/0/...` passes `\d+`, a value object that rejects `0` must answer
  422, not 500, and one that accepts it (`TaxRulesGroupId::assertPositiveInt()`
  only refuses negatives) makes `0` an unknown id, a 404 on an item or
  an empty list — read the body of the guard, not its name;
- the exceptions of the referenced entities checked by the handler or
  the value objects: `ShopException` 422 / `ShopNotFoundException` 404,
  `ZoneNotFoundException` 404, `CurrencyConstraintException` 422,
  `OrderReturnStateNotFoundException` 404…

Rule of thumb: **any identifier checked for existence answers 404 when
unknown, in the URI or in the body** (every `*NotFoundException` → 404,
no exception), an invalid identifier format (`0`, negative) answers
422, and the error message names the entity that failed.

API Platform takes the **first matching class**, so a subclass mapped to
a different status is listed before its parent
(`OrderReturnStateNotFoundException` → 404 before
`OrderReturnStateException` → 422, see
`src/ApiPlatform/Resources/OrderReturn/OrderReturn.php`); a child mapped
to the same status as its parent is redundant; a base
`{Entity}Exception` thrown directly by the handler stays mapped to 422
(narrowing it lets the others escape as 500) and is never mapped to
404. Do not map exceptions the handler cannot throw nor classes absent
from a supported core (`composer phpstan` on every version catches
them): when the reference core throws an exception the older cores do
not have, the core derives it from the domain's `…ConstraintException`
so the existing mapping covers it (paired core PR); a `class.notFound`
ignore scoped to the resource directory in the older legs' PHPStan
configuration is the fallback the PR has to justify, with what those
cores do instead. An operation refused by a business rule (the default
country, a group used by the configuration) answers 422 through a domain
exception; a legacy `PrestaShopException` wrapped in `CoreException` is a
core bug to fix, never a class to map. When the core query answers `[]` for an unknown id instead of
throwing, either the core gets the guard or the `200 []` contract is
documented and tested consistently across the domain. Never use
`HTTP_BAD_REQUEST` (400) for constraint violations — always 422; 400 is
reserved to malformed requests (invalid JSON, missing shop context).
`\TypeError` / `\ValueError` cannot be mapped: prevent them with
validation. Sibling operations answer the same code for the same input.

## Validation

A payload that follows the documentation never produces a 500 or a 400:

- `#[Assert\NotNull]` / `NotBlank` on every required non-nullable
  property: a missing constructor argument is otherwise a 400 from the
  serializer; a `Choice` alone leaves an uninitialized typed property and
  a 500; a missing key and an explicit `null` must give the same 422
  (`Choice`, `TypedRegex`, `Length`, `Positive` skip `null`: only
  `NotNull` / `NotBlank` / `DefaultLanguage` catch it).
- `Assert\All` + `Assert\Collection` (or a DTO) on nested arrays.
- `Choice` built from the core constants (a literal list for backed
  enums, since a `\ValueError` cannot be mapped).
- The map also covers the exceptions thrown while the command or its
  value objects are built (a `GroupConstraintException` from the
  constructor answers 422 once mapped); what it cannot cover surfaces as
  a 500 — a `\TypeError`, an `\InvalidArgumentException`, a generic
  `DomainConstraintException` of a value object — so those cases are
  prevented by a resource constraint, never left to the command.
- Constraints only where they act: none on a GET-only resource, no
  `NotBlank` on a `DecimalNumber` (an object is never blank), no
  `openapiContext` bounds in place of a constraint.
- Symfony comparison constraints (`Range`, `GreaterThan`…) do not work on
  `DecimalNumber` properties: such a check belongs to the command (core
  change).
- `validationContext: ['groups' => ['Default', 'Create']]` on
  `CQRSCreate` and `['Default', 'Update']` on `CQRSPartialUpdate` /
  `CQRSUpdate`: a group no operation activates never runs. Constraints
  match the rules of the associated BO form type, including the ones the
  CQRS command does not enforce itself (`CleanHtml`, `TypedRegex`,
  `DefaultLanguage`, non-empty collections, ranges, URL formats).
- A value the legacy model refuses is a raw `PrestaShopException` in
  `add()` / `update()`, a 500 nothing maps: a handler relying on
  `validateFields(false)` skips the localized fields, so `"<b>"` as a
  name reaches the model. The resource constraint prevents it, and the
  missing check is a core improvement (the CQRS layer validates every
  field, `validateFieldsLang()` in the handler).

## Bulk operations

- Bulk id lists carry `#[Assert\NotBlank]` and
  `#[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]`:
  the bulk commands cast each element with `(int)`, so an unvalidated
  `["3abc"]` silently acts on entity 3 (`Positive` alone does not reject
  it, `"3abc" > 0` is true). Bulk string identifiers carry `NotBlank` +
  `Count(min: 1)` + `All([Type('string'), NotBlank, Length(max: …)])`.
  `NotBlank` and `Count(min: 1)` together are the prescription, not a
  redundancy.
- A bulk command whose handler extends the core
  `AbstractBulkCommandHandler` answers 207 on a partial failure from core
  9.1.2 (`BulkCommandExceptionNormalizer`): one error per failed id, with
  the status of the operation's own `exceptionToStatus` (the map stays
  the single source: a child mapped to its parent's status is redundant
  there too); older cores answer 422. An exception the handler does not
  collect (a `CoreException` wrapping a legacy refusal) aborts the whole
  bulk with a 500: a core guard is a prerequisite (the default country in
  `BulkCountries`, guarded from core 9.2.1). The operation description says it ([`OPENAPI.md`](./OPENAPI.md)),
  the test covers it ([`TESTING.md`](./TESTING.md)).
- A bulk delete sets `allowEmptyBody: false` (the `CQRSDelete` default is
  `true`): a required body is never declared optional.

## Status codes

| Status | When |
|--------|------|
| 201 | creation, with the full resource (`CQRSQuery`) |
| 200 | update and command-style operation returning the updated state |
| 204 | deletion and body-less action with nothing to read back |
| 207 | partial failure of a bulk command (core ≥ 9.1.2) |
| 404 | unknown identifier, in the URI or in the body |
| 422 | invalid value: constraint violation (with the field list) or mapped value object / constraint exception (without) |
| 400 | malformed request only: invalid JSON, missing shop context |
| 401 / 403 | missing token / missing scope |

The responses listed in the OpenAPI output are not derived from this
mapping: see [`OPENAPI.md`](./OPENAPI.md).
