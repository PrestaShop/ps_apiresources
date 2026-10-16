---
trigger: glob
globs: tests/Integration/**
description: "ps_apiresources: testing expectations of the integration tests"
---
<!-- Generated from .ai/rules/api-tests.md by .ai/bin/sync-rules.sh: edit the source, not this copy -->

# Integration tests

Read `.ai/TESTING.md` before writing or changing a test: `ApiTestCase` helpers, the test environment
(no employee in the context), whole-response assertions, fixtures through the API, the error cases of
every mapped exception, version-dependent endpoints. The always-true conventions are in `.ai/CONTEXT.md`.
