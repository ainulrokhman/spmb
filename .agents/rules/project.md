---
trigger: always_on
glob: "**/*.{php,js,blade.php,json,md}"
description: Core rules for laravel-admin (Laravel 12, Spatie RBAC, Security & Quality)
---

# laravel-admin Agent Rules

Always adhere to these architectural, security, and RBAC rules:

## 1. Tech Stack
- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Blade + Bootstrap 5 + Bootstrap Icons. Custom vanilla CSS.
- **RBAC**: Spatie Laravel Permission (`spatie/laravel-permission`)
- **DataTables**: Server-side Yajra DataTables (`yajra/laravel-datatables-oracle`)

## 2. Strict RBAC (Permissions)
- **Convention**: `<resource>-list`, `<resource>-create`, `<resource>-edit`, `<resource>-delete`, `<resource>-show`, `view-<name>`, `manage-<action>`.
- **Controllers**: Authorize methods at the top: `Gate::authorize('<resource>-<action>')`.
- **Routes**: Protect using permission middleware: `->middleware('permission:<resource>-list')`.
- **Blade**: Wrap actions/navigation links in `@can('<permission>') ... @endcan`.
- **Seeders**: Add new permissions to `$permissions` in [RolesAndPermissionsSeeder.php](file:///d:/laragon/www/laravel-admin/database/seeders/RolesAndPermissionsSeeder.php) via `firstOrCreate`.
- **SuperAdmin**: Automatically bypasses checks (`Gate::before` in [AppServiceProvider.php](file:///d:/laragon/www/laravel-admin/app/Providers/AppServiceProvider.php)).

## 3. Security & Quality
- Block self-deletion (`auth()->id() === $user->id`).
- Prevent changing email/deleting seed account `superadmin@example.com`.
- Always hash passwords with `Hash::make()`.
- Use CSRF `@csrf` and validate requests.

## 4. Code Quality & Principles
- **SOLID Principles**: Write modular, extensible code adhering to Single Responsibility, Open-Closed, Liskov Substitution, Interface Segregation, and Dependency Inversion.
- **Clean Code**: Use self-documenting names, keep methods/classes focused, avoid duplication (DRY), and minimize unnecessary complexity.
- **Laravel & PHP Best Practices**: Leverage Laravel features (e.g., Form Requests for validation, Eloquent relationships, Dependency Injection), adhere to PHP/PSR coding standards, and write secure, readable, and highly maintainable code.


