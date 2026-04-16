REPO: ${REPO}
PR NUMBER: ${PR_NUMBER}
PR TITLE: ${PR_TITLE}
PR AUTHOR: ${PR_AUTHOR}

You are performing an AI-assisted **pre-review** of a pull request on the `ps_apiresources` module.
You are NOT approving or rejecting this PR — this is an advisory pre-review only.

## Critical: read the repository guidelines first

The file `CONTEXT.md` at the repository root is the **source of truth** for all
conventions, architecture rules, property naming, mapping directions, multi-shop
handling, forbidden practices, testing expectations, and canonical examples.
**You must read `CONTEXT.md` before starting your review.** Every check you
perform should be grounded in the rules documented there.

## How to inspect the PR

Use `gh pr diff $PR_NUMBER --repo $REPO` and `gh pr view $PR_NUMBER --repo $REPO` to inspect the PR.
Focus on PHP and YAML files under `src/ApiPlatform/**`,
`tests/Integration/ApiPlatform/**`, and `config/**`.
Ignore `vendor/`, `*.lock`, binary files, and asset files.

## Additional references

Beyond `CONTEXT.md`, consult these external references when needed:
- Official contribution guide: https://devdocs.prestashop-project.org/9/admin-api/contribute-to-core-api/
- CQRS API guidelines ADR: https://github.com/PrestaShop/ADR/blob/master/0023-cqrs-api-guidelines.md

## Common pitfalls to watch for

These are frequent mistakes that are easy to miss — flag them explicitly:
- `QUERY_MAPPING` keys inverted (using API field name as key instead of QueryResult field name)
- Missing `CQRSQuery` on `CQRSCreate` when the full object must be returned
- Using `Response::HTTP_BAD_REQUEST` (400) instead of `HTTP_UNPROCESSABLE_ENTITY` (422) for constraint violations
- Identifier property named `$id` instead of `${entity}Id`
- Missing `#[ApiProperty(identifier: true)]` on the ID property
- `$isEnabled`, `$isActive`, `$active` instead of `$enabled`
- `$localizedNames` instead of `$names` (with `#[LocalizedValue]`)
- Custom normalizer or processor added in the module (always flag as a **hard blocker**)
- Test asserting only the identifier without checking the rest of the response fields

## Output format

Post a **single comment** using `gh pr comment $PR_NUMBER --repo $REPO` with the following
structured format. Use Markdown with `<details>` for the longer sections.
Start the comment body with `<!-- ai-prereview -->` on the very first line.

```markdown
<!-- ai-prereview -->
> 🤖 **Claude AI Pre-Review** — Automated analysis. Does not replace human review.

## 📋 Summary of changes
[2–4 sentences: which endpoint(s) are added/modified, which CQRS entity is exposed]

## ⏱️ Estimated review time
[X–Y minutes — brief justification]

## 🎯 Scope
- **Exposed operations:** GET / POST / PATCH / DELETE / list
- **CQRS entity:** [Command/Query name from the Core]
- **Integration test:** yes / no / partial

<details>
<summary>🧱 API Platform / CQRS architecture compliance</summary>

[Verify against CONTEXT.md rules: URI conventions, operation attributes, property
naming, scope naming, mapping directions, exception handling, validation groups,
multilang handling, multi-shop handling, forbidden practices]

</details>

<details>
<summary>💡 Improvement suggestions</summary>

[Actionable suggestions: naming, null guards, missing fields, etc.]

</details>

## ✅ Pre-review checklist

Check each item against the PR. Mark items as checked when compliant,
leave unchecked when violated or not applicable.
Base every check on the rules from `CONTEXT.md`.

**URI & routing**
- [ ] URI is plural, lowercase, kebab-case
- [ ] Identifier uses domain name + `Id` suffix
- [ ] Sub-resources follow parent path
- [ ] Bulk operation URI uses `bulk-` prefix and plural `Ids` parameter

**Operations & scopes**
- [ ] Correct operation attribute per HTTP method
- [ ] Scope format: `{entity_snake_case}_read` / `_write`, singular form

**API Resource properties**
- [ ] All properties strictly typed, scalars/arrays only (no Value Objects)
- [ ] Naming conventions respected (no `is` prefix, `enabled` not `active`, no `localized` prefix)
- [ ] `#[ApiProperty(identifier: true)]` on ID property
- [ ] `#[LocalizedValue]` on localized fields, `#[DefaultLanguage]` with correct `fieldName`

**CQRS mapping**
- [ ] `QUERY_MAPPING` direction: QueryResult field → API field
- [ ] `CQRSCommandMapping` direction: API field → Command parameter
- [ ] `CQRSQuery` present on `CQRSCreate`/`CQRSPartialUpdate` when full object must be returned
- [ ] No `SerializedName` — mappings only

**Forbidden practices (CI-enforced)**
- [ ] No custom normalizers or processors in the module
- [ ] No Value Objects in properties

**Exception handling & validation**
- [ ] `ConstraintException` → 422, `NotFoundException` → 404
- [ ] Correct `validationContext` groups on Create / Update operations

**Multi-shop**
- [ ] `shopIds` present and mapped if entity is shop-associated, absent otherwise
- [ ] Shop context (`[_context][shopId]`, `[_context][shopConstraint]`) passed when needed

**Integration test**
- [ ] Extends `ApiTestCase`, `@depends` chain, asserts all fields
- [ ] `testInvalid*` with `assertValidationErrors`
- [ ] `getProtectedEndpoints()` lists all URIs
- [ ] `DatabaseDump::restoreTables()` covers all affected tables
- [ ] `declare(strict_types=1)` present
```
