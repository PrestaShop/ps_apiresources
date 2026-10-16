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

- the exceptions of the value objects built from the URI or the body
  (`/x/0/...` passes `\d+` and must answer 422, not 500);
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
them). When the core query answers `[]` for an unknown id instead of
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
  a 500; a missing key and an explicit `null` must give the same 422.
- `Assert\All` + `Assert\Collection` (or a DTO) on nested arrays.
- `Choice` built from the core constants (a literal list for backed
  enums, since a `\ValueError` cannot be mapped).
- An exception thrown in the command constructor surfaces as a 500:
  validate at the resource level.
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
  the status of the operation's own `exceptionToStatus`; older cores
  answer 422. Say it in the operation description and test it with
  `bulkCommandItemsWithExpectedErrors()`.
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

The responses listed in the OpenAPI output are API Platform's generic
defaults, not derived from `exceptionToStatus` (see
[`OPENAPI.md`](./OPENAPI.md)).
