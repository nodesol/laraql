# LaraQL
## Laravel, meet GraphQL.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nodesol/laraql.svg?style=flat-square)](https://packagist.org/packages/nodesol/laraql)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/nodesol/laraql/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/nodesol/laraql/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/nodesol/laraql/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/nodesol/laraql/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/nodesol/laraql.svg?style=flat-square)](https://packagist.org/packages/nodesol/laraql)

LaraQL enables you to effortlessly integrate GraphQL into your Laravel application using native PHP attributes. Published in 2025 for modern developer workflows.

LaraQL uses Code-First Discovery. Instead of maintaining a separate .graphql file, your PHP classes become the source of truth. LaraQL scans these classes and generates the SDL for Lighthouse on the fly.

## Documentation

[https://nodesol.github.io/laraql](https://nodesol.github.io/laraql)

## Installation

Use composer to add LaraQL to your Laravel project:

```bash
composer require nodesol/laraql
```

Publish the default configuration to customize scan paths.

```bash
php artisan vendor:publish --tag="laraql-schema"
```

## Usage

The *#[Model()]* attribute tells LaraQL that this class should be part of the GraphQL schema. LaraQL will automatically generate the necessary schema to create a **Type**, **Input**, two **Queries** (single/multiple), and **Mutations** (create/update/delete).

```php
use Nodesol\LaraQL\Attributes\Model as ModelAttribute;

#[ModelAttribute()]
class Article extends Model
{
    public string $title;
}
```

## AI Agents (Laravel Boost)

LaraQL ships [Laravel Boost](https://laravel.com/docs/boost) guidelines and agent skills, so coding agents extend the schema with attributes instead of hand-written SDL.

```bash
composer require laravel/boost --dev
php artisan boost:install
```

Boost detects `nodesol/laraql` and installs:

- **Guidelines** (always loaded): how LaraQL discovers classes, what `#[Model()]` generates, and the rules around `$fillable`, `$hidden`, schema caching, database introspection and overrides.
- **Skills** (loaded on demand):
    - `laraql-development` - models, attributes, generated CRUD, filters, overrides, authorization and schema debugging, plus `references/attributes.md` and `references/troubleshooting.md`.
    - `laraql-scout-search` - the `@scoutFilters` directive for Meilisearch search combined with Eloquent `where` conditions and pagination.

Both live in [`resources/boost`](resources/boost), so you can read them or copy them into your application's `.ai/guidelines` and `.ai/skills` directories to adapt them.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Amer Chaudhary](https://github.com/amermchaudhary)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
