# School Manager

School Manager is a school administration platform built with Laravel and Vue. It brings day-to-day academic and operational workflows into one application, with a domain-oriented approach to school records, permissions, lifecycle management, and configurable platform capabilities.

> **Project status:** Active development. Features are delivered in scoped phases; planned work should not be assumed to be available until it is implemented and merged.

## What it covers

The application includes academic and administrative workflows such as:

- **School management** — school identity and contact details, lifecycle management, readiness checks, and capacity enforcement.
- **Academic administration** — sections, class levels, subjects, academic sessions and terms, timetables, lesson plans, assignments, attendance, examinations, score entry, and report cards.
- **Admissions and people** — admissions, students/guardians, staff, and related records.
- **Finance** — fees, payments, concessions, installments, expenses, transactions, and financial reports.
- **Operations** — notices, events, feedback, leave management, payroll, transport, hostel management, and library/book workflows.
- **File Manager** — managed file and folder workflows integrated with school ownership and lifecycle operations.
- **Platform foundations** — role/permission management, settings infrastructure, activity logging, media handling, notifications, and feature/entitlement registration.

The available workflows and maturity of individual areas vary. Consult the application routes, tests, and in-app navigation for the current implementation.

## School Management: current scope

The initial School Management lifecycle supports these states:

`Draft → Ready → Active → Suspended`

Schools can also be archived from Active or Suspended states. Recovery from Archived is supported through the lifecycle service.

Readiness currently checks five conditions:

1. A school name is present.
2. A school code is present and unique within the tenant database, including soft-deleted schools.
3. A valid school email is present.
4. A primary contact name and at least one phone number or email are present.
5. A primary address is present.

School Type is optional and does not determine readiness or feature access. The `schools.max_count` entitlement controls counted school capacity; the current default is 1,000,000. Archived and soft-deleted schools are excluded from the capacity count.

## Technology stack

### Backend
- PHP 8.3+
- Laravel 12
- Eloquent ORM and Laravel migrations
- Laravel Sanctum
- Laratrust for role/team permissions
- Spatie packages for activity logging, media library, backups, and related application infrastructure
- Model States for lifecycle transitions

### Frontend
- Vue 3
- TypeScript
- Inertia.js 2
- Vite
- Tailwind CSS 4
- PrimeVue and PrimeIcons

Composer and npm manifests are the source of truth for dependency versions.

## Requirements

For local development, install:

- PHP 8.3 or newer and the PHP extensions required by Laravel and the installed Composer packages
- Composer
- Node.js and npm
- A supported database configured for the application
- A queue/cache/session setup appropriate for your environment

MySQL or MariaDB is recommended when verifying database-locking and concurrent capacity behavior. The default PHPUnit configuration uses an in-memory SQLite database for ordinary tests; database-specific concurrency tests need their documented database and process-control prerequisites.

## Getting started

1. Clone the repository and enter the project directory.

   ```bash
   git clone https://github.com/chibyk71/school_manager.git
   cd school_manager
   ```

2. Install PHP and JavaScript dependencies.

   ```bash
   composer install
   npm install
   ```

3. Create your environment file and application key.

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   On Windows PowerShell, use `Copy-Item .env.example .env` if the environment file does not already exist.

4. Configure the database and other required services in `.env`. Create the configured database before running migrations.

5. Run migrations.

   ```bash
   php artisan migrate
   ```

6. Start the development environment.

   ```bash
   composer run dev
   ```

   This project script starts the Laravel development server, queue listener, and Vite development server. If you prefer separate terminals, run `php artisan serve`, `php artisan queue:listen --tries=1`, and `npm run dev` individually.

Do not use production credentials or production data in a local development environment. Review the application's environment and storage configuration before deploying it.

## Common development commands

| Task | Command |
| --- | --- |
| Run the test suite | `php artisan test` |
| Build frontend assets | `npm run build` |
| Run PHP formatting checks | `./vendor/bin/pint --test` |
| Apply PHP formatting | `./vendor/bin/pint` |
| Inspect registered routes | `php artisan route:list` |
| Run database migrations | `php artisan migrate` |

Some integration tests require services or database engines beyond the default in-memory test configuration. Check the relevant test/workflow documentation before interpreting a skipped database-specific test as a pass.

## Project conventions

- Follow the existing application architecture and naming conventions before introducing new patterns.
- Preserve school, section, and ownership boundaries when adding or changing domain behavior.
- Enforce authorization and validation on the server; frontend visibility is not an authorization boundary.
- Use existing settings, lifecycle/state, address, media, and feature/entitlement infrastructure where appropriate.
- Keep changes scoped to the approved feature and add regression tests for business rules and edge cases.
- Treat documentation and test results as descriptions of the implementation that actually exists, not as promises of planned functionality.

## Contributing

Before starting a substantial change, clarify its purpose, affected workflows, domain rules, permissions, ownership boundaries, dependencies, and out-of-scope behavior. Follow the repository's current conventions, add meaningful tests, and run the relevant test, formatting, and frontend build checks before submitting a pull request.

## License

The repository currently declares the MIT license in `composer.json`. Confirm the intended project licensing and distribution terms before redistributing the application.
