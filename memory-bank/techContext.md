# School Manager — Technology Context

## Verified Technology Stack

### Backend

| Component | Technology | Version |
|-----------|-----------|---------|
| Language | PHP | ^8.3 |
| Framework | Laravel | ^12.44.0 |
| ORM | Eloquent | (Laravel built-in) |
| Auth tokens | Laravel Sanctum | ^4.2.1 |
| Role/Permissions | Laratrust | ^8.5.1 (teams mode, School = Team) |
| MFA | Laragear Two-Factor | ^3.0 |
| OTP | ichotrojan/laravel-otp | * |
| Activity Log | Spatie laravel-activitylog | ^4.10.2 |
| Media Library | Spatie laravel-medialibrary | ^11.17.7 |
| Model States | networkrailbusinesssystems/laravel-model-states | ^1.0 |
| Data/DTOS | Spatie laravel-data | ^4.0 |
| Schemaless Attributes | Spatie laravel-schemaless-attributes | ^2.5 |
| Settings | oi-lab/oi-laravel-settings | ^1.0 |
| Filtering/Sorting | abbasudo/laravel-purity | ^3.3 |
| Excel | maatwebsite/excel | ^4.0 |
| World data | nnjeim/world | ^1.1 |
| Metrics | sakanjo/laravel-easy-metrics | ^1.1 |
| Backup | Spatie laravel-backup | * |
| Mail Templates | Spatie laravel-database-mail-templates | ^3.7.1 |
| Device Tracking | diego-ninja/laravel-devices | ^2.0 |
| SMS | djunehor/laravel-sms | ^2.2 |
| Inertia | inertiajs/inertia-laravel | ^2.0 |
| Ziggy | tightenco/ziggy | ^2.6.0 |
| Docent Docs | stechstudio/laravel-docent | ^1.7 |

### Frontend

| Component | Technology | Version |
|-----------|-----------|---------|
| Language | TypeScript | ~5.9.3 |
| Framework | Vue | ^3.4.0 |
| Inertia | @inertiajs/vue3 | ^2.0.0 |
| Build | Vite | ^6.0 |
| CSS | Tailwind CSS | ^4.1.12 |
| UI Components | PrimeVue | ^4.4.1 |
| Icons | PrimeIcons | ^7.0.0 |
| Charts | ApexCharts (vue3-apexcharts), Chart.js | ^5.3.6 / ^4.5.1 |
| Calendar | FullCalendar (core, daygrid, vue3) | ^6.1.15 |
| Rich Text | Quill | ^2.0.3 |
| State | Pinia (via store directory) | Vue 3 built-in |
| HTTP | Axios | ^1.7.4 |
| Date Utils | date-fns | ^4.1.0 |
| TanStack Query | @tanstack/vue-query | ^5.66.0 |
| Swiper | swiper | ^11.2.4 |
| UUID | uuid | ^13.0.0 |

### Testing

| Tool | Usage |
|------|-------|
| Pest PHP 3 | Primary test framework |
| Pest Laravel Plugin | Integration with Laravel |
| SQLite :memory: | Default test database |
| Mockery | Mock objects where needed |
| Faker | Test data generation |

## Development Commands

| Task | Command |
|------|---------|
| Run test suite | `php artisan test` |
| Run single test | `php artisan test --filter=TestName` |
| Build frontend | `npm run build` |
| Dev server | `composer run dev` (Laravel + queue + Vite concurrently) |
| Format check | `./vendor/bin/pint --test` |
| Apply formatting | `./vendor/bin/pint` |
| List routes | `php artisan route:list` |
| Run migrations | `php artisan migrate` |
| Docent guide | `php artisan docent:guide` |
| Docent check | `php artisan docent:check` |

## Environment Requirements

- PHP 8.3+ with required Laravel extensions
- Composer
- Node.js + npm
- SQLite (default) or MySQL/MariaDB (for concurrency tests)
- Queue driver configured (default: database)
- Cache driver configured (default: database)

### PHP Configuration (for testing)
- `BCRYPT_ROUNDS=4` (test environment)
- `CACHE_STORE=array` (test environment)
- `SESSION_DRIVER=array` (test environment)
- `QUEUE_CONNECTION=sync` (test environment)