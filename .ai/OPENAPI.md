# OpenAPI documentation

Read when adding or changing a resource property, an operation, a
description or an example: the generated documentation is part of the
contract and is reviewed as such ([`REVIEW.md`](./REVIEW.md) → §1 C). The
conventions of the module are in [`CONTEXT.md`](./CONTEXT.md).

The documentation is generated from the resource: property types and
attributes give the schemas, the core command gives the request body of
the write operations (constructor parameters and setters, `defaultValues`
shown as `default` and removed from `required`), the operation attribute
gives the parameters of a collection. What the resource must add:

- `openapi: new OpenApiOperation(summary: ..., description: ...)` on
  every command-style operation and on every operation whose HTTP method
  does not say what it does: an update forced to POST by a file upload
  needs it on **both** operations sharing the method, otherwise the doc
  shows two "Creates a … resource" entries.
- A description for every non-obvious behaviour: version differences
  (`minVersion`, a response shape that differs before 9.2.1), values
  formatted for display, fields that are always empty, side effects (an
  existing empty cart is reused), what a "set" operation replaces, the
  multishop coverage, the filters a list supports, the 207 behaviour of a
  bulk command. A description never promises what the command does not
  do.
- `#[ApiProperty(openapiContext: ['description' => ..., 'example' => ...])]`
  on a property whose name is not self-explanatory; localized examples
  keyed by locale. Only the `openapiContext` reaches the request body
  built from the command (`ApiProperty(description:)` and `example:`
  document the resource schema alone); the core merges it with the type
  and the items it infers from the property and its docblock, so it never
  carries a `type`. An identifier needs no `openapiContext`: its `int`
  type already documents it as an integer, and the resources set no
  example on it.
- Query parameters through `parameters: new Parameters([new QueryParameter(
  key:, required:, description:)])`, declared once, with a schema limited
  to `{type, default}` (the `default` typed like the `type`: `5`, not
  `'5'`) and the rich schema (array of strings, `style: form`) in the
  operation `openapiContext`.
- Full English sentences ending with a dot; examples with values the
  command accepts; a description that announces a status code states the
  real one (200, not 204, on a forced-POST update).
- Enumerations as lists (`'enum' => [...]`) built from the core constants
  (an associative array becomes a JSON object).
- `deprecationReason` on a field or operation kept for backward
  compatibility.

What must not be done: `type: string` overrides on `DecimalNumber`
properties (they are JSON numbers), object schemas written by hand (use a
DTO, see `CONTEXT.md` → "Sub objects: typed DTOs"), input-only fields
visible in the output (`#[ApiProperty(readable: false)]`), path variables
or read-only fields in a request body (`writable: false`), a `$ref` to a
value object schema (`DecimalNumber`, `Money`, `ShopConstraint`).

What the generator does and does not do: the input schema of a write
operation is built per command (its parameters, through the command
mapping) and the read schema from the query result, so they may
legitimately differ; a typed `openapiContext` on an array documents its
first level only; the request body of a bulk `DELETE` is not documented
(Swagger offers no field — a core limitation, not a resource bug); the
responses listed (400, 422 on writes, 404 on item routes) are API
Platform's generic defaults, not derived from `exceptionToStatus` yet;
an id-only operation (`duplicate`, `toggle-status`, `reset`) is exported
with a required, empty request body; a command whose accessors are named
differently from its constructor parameters (`getImageWidth()` for
`$imgWidth`) loses those parameters from the documented body; a
write-only nullable property guarded by `NotNull` (`?string $privateNote`)
is documented as nullable although `null` answers 422.

Class and property comments are PHPDoc for developers: the core does not
publish them (9.2.1 removed the factory that published class comments,
older cores never loaded it; property summaries need
`phpdocumentor/reflection-docblock`, which the core does not ship — only
the `@var` types are read). Public text goes in `description` only:
`openapiContext: ['description' => ...]` on a property, `openapi: new
OpenApiOperation(...)` on an operation.

Check a change with the export of the core, diffed before/after, and a
real call: `bin/console api:openapi:export --yaml -o /tmp/openapi.yaml`.
The Swagger page is linked from BO > Advanced Parameters > Admin API
(`/admin-dev/index.php/configure/advanced/admin-api/docs`).
