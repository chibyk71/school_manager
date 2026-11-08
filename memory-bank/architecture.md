# School Manager — Architecture

## Application Architecture

### High-Level Overview

Laravel 12 monolith backend serving a Vue 3 SPA via **Inertia.js 2**. No separate API layer — server-rendered pages with Inertia bridging. Vite for frontend asset bundling.

```
Browser ← Inertia.js → Laravel (Controllers → Services → Models → DB)
                            │
                            ├── SettingsRegistry / SettingsResolver
                            ├── EntitlementRegistry / EntitlementResolver
                            └── Gate (AuthorizationService)
```

### Multi-Tenant Model

- **Database-per-tenant-lite**: All schools share a single database instance, scoped via `school_id`.
- **School** is the central tenant entity, also serves as the **Laratrust Team** for authorization pivots.
- **BelongsToSchool** trait + **SchoolScope** global scope automatically scope queries.
- **SchoolContext** middleware resolves the current school from the session.

### Backend Structure (`app/`)

```
app/
├── Actions/           — Bulk action orchestration
├── Checkers/          — School readiness checks
├── Console/Commands/  — Artisan commands
├── Contracts/         — Interface contracts (Academic, Authorization, BulkActions)
├── DataTransferObjects/ — Structured data transfer
├── Entitlements/      — Feature/entitlement definitions
├── Enums/             — PHP enums (Auth, FileManager)
├── Events/            — Domain events
├── Exports/           — Excel/CSV exports
├── Facades/           — Laravel facades
├── Helpers/           — Global helper functions
├── Http/
│   ├── Controllers/   — Thin controllers, delegate to Services
│   ├── Middleware/     — Request middleware pipeline
│   │   ├── SchoolContext
│   │   ├── EnsureAccountEligible
│   │   ├── EnsurePasswordChanged
│   │   ├── EnsureTwoFactorVerified
│   │   ├── CheckMaintenanceMode
│   │   └── HandleInertiaRequests
│   └── Requests/      — Form requests (validation)
├── Jobs/              — Queued jobs
├── Listeners/         — Event listeners
├── Metrics/           — Metric resolution
├── Models/            — Eloquent models
│   └── Academic/      — Domain-specific models
├── Notifications/     — Mail/database notifications
├── Observers/         — Eloquent observers
├── Policies/          — Authorization policies (Gates)
├── Providers/         — Service providers
├── Rules/             — Validation rules
├── Services/          — Domain services (business logic)
│   ├── Academic/
│   ├── Auth/
│   ├── FileManager/
│   ├── Permission/
│   ├── School/
│   └── Student/
├── Settings/          — Setting definitions
├── States/            — Model state machine definitions
├── Support/           — Infrastructure support classes
│   ├── Auth/
│   ├── DataTable/
│   ├── Entitlements/
│   ├── FileManager/
│   ├── Permission/
│   ├── School/
│   └── Settings/
└── Traits/            — Reusable model traits
    ├── BelongsToSchool
    ├── BelongsToSections
    ├── HasAddress
    ├── HasAvatar
    ├── HasCustomFields
    ├── HasDynamicEnum
    ├── HasProfile
    ├── HasSchemalessAttributes
    ├── HasTableQuery
    ├── HasTransaction
    └── TracksAcademicUsage
```
### Frontend Structure (`resources/js/`)

```
resources/js/
├── Components/     — Vue components (Academic, Address, datatable, forms, menu, Modals, etc.)
├── composables/    — Vue composables (shared state logic)
├── datatable/      — DataTable infrastructure (adapters, tests)
├── Layouts/        — Page layouts (AuthenticatedLayout with required `crumb` prop)
├── Pages/          — Inertia page components (mirrors routing structure)
├── store/          — Pinia stores (layout, etc.)
└── types/          — TypeScript type definitions
```

### Route Structure (`routes/`)

```
routes/
├── web.php           — Main web routes (dashboard, academic, finance, transport, hostel, etc.)
├── auth.php          — Authentication routes
├── settings.php      — Settings (admin + system)
├── user_management.php — User/Profile management
├── address.php       — Address management
├── file_manager.php  — File Manager routes
├── enrollment.php    — Student enrollment
├── lifecycle.php     — School lifecycle
├── promotion.php     — Student promotion
├── employee_management.php — HRM/Staff
├── applications.php  — Applications
├── admission.php     — Admissions
├── dynamic_enums.php — Dynamic enum management
├── options.php       — Select options
├── api.php           — API routes (limited)
└── console.php       — Artisan console routes
```

### Data Flow

1. **Browser → Inertia**: Page request goes through Inertia.js to Laravel route.
2. **Middleware pipeline**: SchoolContext → auth → EnsureAccountEligible → EnsurePasswordChanged → EnsureTwoFactorVerified.
3. **Controller**: Thin controller delegates to a **Service** class.
4. **Service**: Business logic, calls Models, enforces authorization via AuthorizationService/Gates.
5. **Authorization**: Gate bridge resolves permission identifiers through `AuthorizationService`.
6. **Response**: Inertia renders Vue page component with server-provided props.

### Key Architectural Relationships

- **School → everything**: Almost all models BelongsToSchool, scoped by SchoolScope.
- **Profile (Person) ↔ User (Account)**: Profile belongs to tenant boundary; User is the authentication account; one Person may have zero or one User account. Profile holds identity; User holds credentials.
- **School → State machine**: `Draft → Ready → Active → Suspended` (Archived from Active/Suspended).
- **Entitlement → Feature**: EntitlementRegistry resolves feature entitlement values (default or SaaS-provided).
- **Settings → Definition**: SettingsRegistry collects settings definitions; SettingsResolver provides values.
- **Permission → AuthorizationService**: Permission catalogue drives Gate bridge for authorization checks.