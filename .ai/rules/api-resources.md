---
description: "ps_apiresources: on-demand contexts for the API resources (OpenAPI, lists, multishop, errors, versions)"
paths:
  - "src/ApiPlatform/**"
  - "config/**"
---

# API resources

Before changing a resource, read in `.ai/`: `OPENAPI.md` (every property, operation, description or
example change — the generated documentation is part of the contract); `LISTS.md` when the operation is a
`PaginatedList`, `CQRSPaginate` or `CQRSGetCollection`; `MULTISHOP.md` when the entity has `shopIds` or
the CQRS class takes a shop id / `ShopConstraint`; `ERRORS.md` when touching `exceptionToStatus`, a
constraint, a validation group or a bulk operation; `VERSIONS.md` when a CQRS class or behaviour is missing
on a supported core (`minVersion`). The always-true conventions are in `.ai/CONTEXT.md`.
