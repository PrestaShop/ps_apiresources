# ps_apiresources — Review checklist

How a pull request on this module is reviewed, by a human or by an AI reviewer (CodeRabbit reads this
file as a guideline, the `review-api-pr` skill runs it locally). The conventions live in
[`CONTEXT.md`](./CONTEXT.md), the single source of truth loaded during development; this file does not
repeat them. It says how to review (§0), what to check and where the rule is (§1), what not to flag
(§2), how to rate and write a finding (§3), how to audit an existing endpoint (§4) and how to maintain the
automated review (§5).

## 0. How to review

1. **Scope**: the files changed by the PR. Read other files only to verify a finding: the core classes a
   resource names, the sibling resources of the domain, `ApiTestCase`. A pre-existing defect is in scope
   when the PR graduates the operation (`experimentalOperation` removed), re-documents it or edits its
   block; any other pre-existing line is reported once, at the end, as a follow-up, not as a finding.
   Read the PR description (`gh pr view <n> --json body`): related core PR, BC breaks, version gates and
   deliberate omissions are checked against it, not guessed.
2. **Find the core reference** ([Working with the core](./CONTEXT.md#working-with-the-core)):
   - the **related core PR** when the description names one (`PrestaShop/PrestaShop#NNNN`): review the
     module against that PR's branch. The module CI is then expected to be red on the branches that lack
     the core change, and the core PR (which pins the module branch in its `composer.json`) must be green
     with the module changes — say so instead of flagging the red jobs;
   - otherwise the **most recent maintained stable branch** of the core (the highest `X.Y.x` branch,
     `9.2.x` today); `develop` only adds the evolutions too big for a patch release, and is the
     reference of an operation gated with `minVersion` on the next minor — a PR can have one reference
     per operation.
   - The module also runs on the older supported cores (9.0.3, 9.1.5), and the local checkout is never
     one of them: before writing "passed" or a finding about a handler, a query handler or a value object,
     check the age of the guard, cast or null check you rely on (`git log --since=<release date of the
     lowest supported tag> -- <file>`, `git blame`, `git tag --contains <sha>`). Anything younger than that
     release is absent on the older CI legs, whose behaviour is the one without it: a core bug to name in
     the description, a version gate on the test, red legs to announce. "The class exists on 9.0.3" says
     nothing about its behaviour.
3. **Open the core classes before writing a finding about a field, a type, a nullability, a mapping or an
   exception.** For each resource of the diff:
   - the command: constructor parameters (names, types, defaults, nullability), setters, multi-parameter
     setters, the exceptions its value objects throw (`{Entity}Id` → `{Entity}ConstraintException`,
     `ShopId` → `ShopException`…);
   - the query and its query result: getters (`getX()` / `isX()` → field `x`), types, what the handler
     really fills (a `0`/`1` tiny int, a formatted price, an always-empty array);
   - the handler: guards and exceptions (`{Entity}NotFoundException`, referenced entities such as
     `ZoneNotFoundException`), including the private assert / guard methods it calls before its own null
     checks, what it does with an optional parameter (a `foreach` on a null array, a uniqueness check on
     an absent name);
   - for a list: the grid query builder (`SELECT` columns, allowed filters and `orderBy` fields) or the
     CQRS list query result;
   - the back-office form type of the entity: constraints and default values the API must reproduce.
   Mappings are resolved **by name**: a property named like the query-result field or the constructor
   parameter needs no mapping entry. Verify the real hierarchy of an exception before saying it is
   unmapped (`CustomerException` is not `CustomerConstraintException`).
4. **Compare the documentation with the behaviour** (§1 C): the generated OpenAPI is part of the
   contract. A mismatch between the schema and the real payload is a finding even when the code works.
5. **One finding per comment**: the rule (the `CONTEXT.md` section), the evidence (core class and line, or
   the payload), the fix. Severity in §3. No praise, no restating the diff.

## 1. Checks

Each line is one check with the `CONTEXT.md` section that holds the rule and the evidence to open.

### A. Resource design and URIs — [Architecture overview](./CONTEXT.md#architecture-overview), [URI conventions](./CONTEXT.md#uri-conventions), [Scopes](./CONTEXT.md#scopes), [Do / Don't](./CONTEXT.md#do--dont)

- One class per concept, list and bulk classes apart, single-operation sub-resources merged into their
  parent; the `{Entity}List` ships with the domain or the description says why not.
- Operation chosen from the command semantics (POST / PATCH / PUT / DELETE, body-less 204 combo, forced
  POST on uploads) — open the command: setters and nullability decide PATCH vs PUT.
- URIs: plural kebab-case, singular only for a 1:1 sub-resource, `{entityId}` with `\d+`, no HTTP verb,
  `bulk-` prefix, `/search` on a `Found{Entity}`, action verbs from the existing wording and listed in
  `ApiResourceUriTemplateRector::SKIPPED_KEYWORDS`.
- A new endpoint compared with [ADR 0023](https://github.com/PrestaShop/ADR/blob/master/0023-cqrs-api-guidelines.md)
  (URI shape, method per operation, bulk shape and methods, scope name, property rules): a deviation is
  Major unless the description announces an ADR amendment; the endpoints of
  [Existing endpoints that deviate from ADR 0023](./CONTEXT.md#existing-endpoints-that-deviate-from-adr-0023)
  are not flagged and not copied.
- Every write returns the updated state through a `CQRSQuery`; `experimentalOperation` when the read side
  is missing.
- One format for read and write; input-only fields `readable: false`.
- No business logic, normalizer, denormalizer, provider or processor; the allow-listed normalizers are
  legacy exceptions, not precedents.
- Nothing exposed that another endpoint already returns, that is BO-only, deprecated, unsettled, or that
  can corrupt data or leak secrets; `EXCLUDED_CQRS_CLASSES` for deliberate omissions.
- Every operation has `scopes`; new named arguments go through `extraProperties` for older cores.
- Consistency with `Contact` / `Title` and with the siblings of the domain (same groups, helpers, status
  codes); obsolete workarounds removed in the PR that makes them useless.

### B. Contract with the core — [Property naming](./CONTEXT.md#property-naming), [Sub objects](./CONTEXT.md#sub-objects-typed-dtos), [Field mapping](./CONTEXT.md#field-mapping), [LISTS.md](./LISTS.md), [MULTISHOP.md](./MULTISHOP.md), [VERSIONS.md](./VERSIONS.md)

- Every write property reaches the command (constructor or setter, through the mapping), no advertised
  input the command drops; every read property has a source in the query result, every returned field is
  exposed or its omission explained — open the command and the query result.
- Names: camelCase incl. flattened legacy rows, `{entity}Id`, `enabled`, `names`, response keys = create
  payload keys, no database column names.
- Types follow the core signature (`DecimalNumber`, `DateTimeImmutable`, real booleans); nullable only when
  the handler can return `null`, returned as `null` (`skip_null_values` off).
- Sub objects are DTOs (`Dto/`, `@var Foo[]`), scalar lists `@var int[]`; nullable object + `required`
  list for multi-parameter setters; `minVersion: 9.2.1` when a DTO holds a list or a boolean from a query
  result.
- Mappings in the right direction, no identity entries, no options at their framework default, a rename
  asked in the core PR when the CQRS layer is unreleased.
- Shop context mapped only when the class takes it, with its type; `shopIds` only with a shop association,
  defaulted from the context through `defaultValues`, round-tripped.
- Lists: `filtersClass` + `filtersMapping`, DTO properties = `SELECT` columns, renamed columns mapped,
  unsupported filters stated in the description; `CQRSGetCollection` only for plain arrays, `collectionAsList`
  for id-indexed results.
- `minVersion` with a reason, never to hide a 500 on 9.0.3 / 9.1.5; next-minor gate for a `develop`
  prerequisite; BC break listed when a released endpoint gets gated.

### C. OpenAPI documentation — [OPENAPI.md](./OPENAPI.md)

With a shop: `bin/console api:openapi:export --yaml -o /tmp/after.yaml` on the PR, the same on the base,
diff the operations of the PR, call the endpoints and compare payloads with schemas. Without a shop:
derive the schema from the property types, the attributes and the core signatures, and compare.

- Types match the real JSON (`DecimalNumber` number, booleans, dates, integer identifiers with an example,
  nullability, enumerations as lists).
- Response schema without input-only fields; request body without path variables or read-only fields,
  with every command input and the right `required` list; `defaultValues` shown as defaults.
- Sub objects from their DTO, nullable objects `['object', 'null']` + `required`.
- `openapi: new OpenApiOperation(summary:, description:)` on command-style operations and when the method
  lies; descriptions for every non-obvious behaviour, never promising what the command does not do;
  field-level `description` / `example` where the name is not self-explanatory.
- Query parameters declared once, schema `{type, default}` with a typed default, rich schema in
  `openapiContext`; full sentences, valid examples, real status codes.
- Paginated collections document `limit` / `offset` / `orderBy` / `sortOrder` / `filters[...]` and the
  envelope (9.2.1); `CQRSGetCollection` answers an array.
- No contract written in class or property PHPDoc (not published); no `$ref` to a value object schema;
  `deprecationReason` on what is kept for BC.
- Not flagged: the input/read schema difference, the first-level-only array context, the missing body of
  a bulk `DELETE`, the missing 404 / 422 responses (core limitations stated in the section).

### D. Errors and validation — [ERRORS.md](./ERRORS.md)

- `exceptionToStatus` covers every exception the operations can raise: entity, value objects of URI and
  body ids (`/x/0`), referenced entities; `*NotFoundException` → 404 wherever the id is, invalid id format
  → 422; subclass before parent, no redundant child, base exception never 404, no dead mapping; `200 []`
  documented when the core does not throw — open the handler and the value objects.
- A documented payload never answers 500 or 400: `NotNull` / `NotBlank` on required properties, nested
  arrays validated, `Choice` lists, the BO form rules, `validationContext` groups activated by the
  operation, constraints only where they act.
- Bulk ids: `NotBlank` + `All([Type('integer'), Positive()])` (string ids: `Type('string')`, `NotBlank`,
  `Length`) — review the constraint set, not the status; 207 behaviour of `AbstractBulkCommandHandler`
  stated in the description.
- Status codes: 201 / 200 / 204 / 207 / 404 / 422 / 400 / 403 as listed in the Do list.

### E. Tests — [TESTING.md](./TESTING.md)

- One `{Entity}EndpointTest` per resource class, `ApiTestCase` helpers, `getProtectedEndpoints()` complete
  (gated entries yielded with `isVersionAtLeast()` in a mixed class).
- Whole-response `assertEquals` through one expected-payload helper reused by the `@depends` chain;
  envelope asserted only on a fully controlled list; GET after every write; effects checked, not only
  statuses; known-state fixtures.
- Error cases: 404 per mapped `*NotFoundException` on every operation, `/x/0` → 422, `testInvalid…` with
  `assertValidationErrors` for constraint 422 only, 207 and non-integer element tests on bulk operations.
- Fixtures through the API, `createApiClient` with the class scopes, every touched table restored,
  resetters, no copy-paste residue.
- Version gates with the helpers, on the first tag carrying the core commit; a test red on a leg that
  lacks a related core PR is expected; the tests a removed workaround had disabled come back.
- A bug fix comes with a test that fails without it.

### F. Hygiene and process — [Working with the core](./CONTEXT.md#working-with-the-core), [Coding standards](./CONTEXT.md#coding-standards)

- One PR per domain; description with endpoints, test scenarios, related core PR, version gates and their
  reason, BC breaks.
- CI green except the legs a related core PR makes red; one PHPStan neon per matrix leg.
- No merge commits, fixups squashed, no unrelated files, `use` imports, `strict_types` in every PHP file
  (a sibling lacking it is not a precedent), no commented code, no stray file.
- Core bugs fixed in the core on the lowest applicable branch, in a named PR; behaviour bugs block, doc
  issues do not.
- The author owns the code and triages automated reviews explicitly.

## 2. Do not flag

- An endpoint listed in [Existing endpoints that deviate from ADR 0023](./CONTEXT.md#existing-endpoints-that-deviate-from-adr-0023):
  its migration is a decision of the team, not a review comment.
- A "missing mapping" for a field whose name already matches the query-result getter or the constructor
  parameter: resolution is by name.
- A field only reachable through a named constructor or a private setter: not a module fix, point to the
  core instead.
- `assertValidationErrors()` for a 422 that comes from a mapped CQRS exception.
- A red CI job on a branch that lacks the related core PR named in the description.
- The removal of `createApiClient()` from `setUpBeforeClass()`, `@depends` chains, or a parent exception
  mapping that covers the subclasses the handler throws ("too broad" is wrong when the handler throws
  the base class itself).
- A "missing `[_context][shopConstraint]`" on a query that takes no shop argument, or `shopConstraint`
  where the constructor takes a plain `int $shopId`.
- A `filtersMapping` entry "unused by the grid filters": it serves `orderBy` too.
- A type that follows the command signature (`string` where the command takes a string, a
  `defaultValues` nullability copied from the command), or a "missing" `exceptionToStatus` when the query
  returns `[]` for an unknown id and the `200 []` contract is documented.
- An option at its framework default reported as "missing" (`allowEmptyBody: false` on a `CQRSCreate` /
  `CQRSPartialUpdate`; on a `CQRSDelete` the default is `true`, so `allowEmptyBody: false` there is a
  real setting).
- `identifier: true` on a resource without an item GET: the convention is not settled, ask the maintainer
  instead of flagging.
- A normalizer, processor or provider as the suggested fix: it is forbidden here.
- A convention that the reference resources (`Contact`, `Title`) or every sibling of the same kind do not
  follow themselves (`example` on identifiers, a redundant `output: false` / `read: false` / `provider:`
  — `read: false` is repeated by 25 resources —, a 404 mapping on a list): on **existing** resources,
  report it once, as Minor, naming the reference, or propose a module-wide follow-up — not one comment
  per occurrence; on the resources a PR adds, each occurrence is a Minor. A status endpoint next to a
  PATCH exposing `enabled` is flagged on a new domain only; existing domains ship both.
- A hand-rolled whole-envelope assertion on a 207 response instead of `bulkCommandItemsWithExpectedErrors()`
  (which only asserts `errors`); a Rector `SKIPPED_KEYWORDS` "gap" while the Rector dry run is green.
- Style already enforced by php-cs-fixer and PHPStan; docblocks on self-explanatory properties; `float`.
- Development-mode stack traces in a QA report: the status code is the finding, not the trace.

## 3. Severity and comment format

| Severity | Examples |
|----------|----------|
| Blocker | a 500 reachable with a payload or URI the documentation allows (unknown id, id `0` through `\d+`, an omitted optional field, a documented body) — which includes an unmapped `*NotFoundException` / `*ConstraintException` on that path; a request that silently acts on another entity (unvalidated ids cast with `(int)`); business logic, normalizer, processor or provider in the module; an operation without scope; no integration test; a core limitation worked around in the module |
| Major | an unmapped exception only reachable through a malformed payload, or one the constraints already prevent; wrong status code; property dropped or always null; schema/doc disagreeing with the payload; partial test assertions; missing version gate or gate hiding a 500 |
| Minor | naming, redundant option or mapping entry, a `filtersMapping` entry the grid ignores without a description saying so, missing description on a non-obvious behaviour, a contract written in PHPDoc instead of `description` |

Comment = rule reference + evidence (core class and line, exported schema, payload) + fix. One finding
per comment, except the documentation texts of one resource (summaries, descriptions, examples), merged
in one comment. A finding that mixes a convention verdict with a verified behaviour bug is split: the bug
gets its own comment and severity. No comment on what is already correct.

## 4. Auditing an existing endpoint

Apply §1 A–D to the whole resource instead of a diff. With a shop:
1. `bin/console api:openapi:export --yaml -o /tmp/openapi.yaml`, extract the operations of the resource.
2. Call each operation (`GET`, the writes with a minimal valid body, an unknown id, id `0`, an empty body,
   an invalid field) and record the status and payload.
3. Compare: schema types/nullability/required vs the payloads; status codes vs §1 D; descriptions vs the
   behaviour; the test file vs §1 E.
4. Report per operation, with the before/after schema extracts expected in the fix PR.

## 5. Maintaining the automated review

- CodeRabbit reads `CONTEXT.md`, this file and the core AdminAPI context as guidelines, plus the
  path-scoped instructions and pre-merge checks of `.coderabbit.yaml` (the file takes precedence over the
  settings of the web UI; path instructions take precedence over learnings).
- When a finding is wrong, answer on the line, with the evidence and the reason: CodeRabbit records a
  learning from the reply. When the correction is general, change the rule in `CONTEXT.md` (or §2 here)
  instead: a learning only applies to similar code, a guideline applies everywhere.
- When a human review catches something `CONTEXT.md` does not state, add the convention there and the
  check here; add the false-positive pattern to §2. A finding caused by an outdated example (a resource
  copied from an older one, a snippet of `CONTEXT.md` or of the `ps-api-endpoint` skill) is fixed in the
  example too.
- `@coderabbitai emit path instructions` on a PR proposes the path instructions it accumulated; review
  them against this file before merging the suggestion.
