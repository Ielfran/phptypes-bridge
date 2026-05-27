# phptypes

Generate TypeScript types, Zod schemas, and a typed fetch client 
from your PHP API — zero YAML, zero drift.

## Requirements
- PHP 8.1+
- Symfony Console 6+

## Installation
```bash
composer require phptypes/bridge
```

## Usage

Create `phptypes.php` in your project root:
```php
return [
    'source_dirs' => ['app/Http/Controllers', 'app/DTOs'],
    'output_dir'  => 'resources/js/api',
    'generators'  => ['types', 'schemas', 'client'],
    'base_url'    => '',
];
```

Annotate your controllers:
```php
#[ApiGroup(prefix: '/api')]
class UserController
{
    #[ApiEndpoint(method: 'GET', path: '/users/{id}')]
    public function show(int $id): UserDto { ... }
}
```

Generate:
```bash
vendor/bin/phptypes generate
```