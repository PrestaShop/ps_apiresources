# PrestaShop API Resources

[![Coverage Status](https://coveralls.io/repos/github/PrestaShop/ps_apiresources/badge.svg)](https://coveralls.io/github/PrestaShop/ps_apiresources)

## About

Includes the resources allowing using the API for the PrestaShop domain, all endpoints are based on CQRS commands/queries from the Core and we [API Platform](https://api-platform.com/) framework is used as a base.

This module contains no code only some resource files that are automatically scanned and integrated by the Core, these resources are in [this folder](src/ApiPlatform/Resources).

## Reporting issues

You can report issues with this module in the main PrestaShop repository. [Click here to report an issue][report-issue].

## Under development

Please be aware that this module is still under development, which is why its version is only `0.*`. We haven't released a major version `1.0` yet because we anticipate some breaking changes may happen while we add all the missing endpoints.

We aim to maintain backward compatibility as much as possible and never introduce breaking changes, but we also want this API contract to be a fixed standard once it's finalized, so we prefer to polish and refine it before a major version is released.

After the `1.0` version is released it won't include other breaking changes, any breaking change will require a new major version (`2.0`) and will follow semver convention.

## Requirements

Required only for development:

- composer

## Installation

Install all dependencies.
```bash
composer install
```

## Run tests locally

### Initialize tmp shop environment

First, initialize the test environment with this command that will install a PrestaShop shop in a temporary folder so that the integration tests can run:

```bash
composer create-test-db
```

### Customize tmp shop environment

You can define custom values when setting up the tmp shop:

```bash
composer clear-test-cache
composer setup-local-tests -- [arguments]
  arguments:
    --force-clone Force cloning the repository even if a cloned repository is detected (when no repository is detected, the clone is automatic)
    --build-assets Force building assets even if they are already built (when no assets are detected, the build is automatic)
    --build-db Force building DB by installing the default shop data (when no DB is detected, the DB shop is installed automatically)
    --update-local-parameters Force copying parameters from the `test/local-parameters` folder (when no parameter file is detectec they are automatically copied)
    --force Force all the previous arguments
    --core-branch Use a specific branch, you can use a branch from the original repository (ex: `develop`, `9.0.x`, ...) or from a fork (ex: `myfork:my-dev-branch`) (By default branch develop is used)
```

Example:
```bash
# To test with 9.0.x branch
composer setup-local-tests -- --force --core-branch=9.0.x

# To test with a branch from your fork (in this example, fork: jolelievre, branch: product-api)
composer setup-local-tests -- --force --core-branch=jolelievre:product-api
```

### Run tests

Then you can run the tests with this command:

```bash
composer run-module-tests
```

## Contributing

The PrestaShop Admin API provides RESTful endpoints for managing your store programmatically. Everyone is welcome to contribute by adding new API endpoints!

**Please read our contributing guide before starting:**
- **[Create new API endpoints](https://devdocs.prestashop-project.org/9/admin-api/contribute-to-core-api/)** - Complete step-by-step guide
- **[Admin API Documentation](https://devdocs.prestashop-project.org/9/admin-api/)** - Full API reference

Need help or believe core improvements are needed? Reach out to the team on [Slack][slack], and `#cfc-adminapi` channel - we're here to help!

Also, follow our general [contribution guidelines][contribution-guidelines].

## License

This module is released under the [Academic Free License 3.0][AFL-3.0]

[report-issue]: https://github.com/PrestaShop/PrestaShop/issues/new/choose
[prestashop]: https://www.prestashop-project.org/
[contribution-guidelines]: https://devdocs.prestashop-project.org/9/contribute/contribution-guidelines/project-modules/
[AFL-3.0]: https://opensource.org/licenses/AFL-3.0
[slack]: https://prestashop-project.org/slack
