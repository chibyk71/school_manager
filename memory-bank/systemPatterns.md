# School Manager — System Patterns & Conventions

## Established Coding Conventions

### PHP/Backend

1. **Controllers are thin**: Delegate business logic to **Service** classes. Controllers handle HTTP concerns (request validation, response/Inertia rendering).
2. **Services contain domain logic**: Named by domain (e.g., `SchoolService`, `UserService`, `FileService`). Services may call other services but not controllers.
3. **Models are Eloquent**: Use traits for reusable behaviour (`HasUuids`, `BelongsToSchool`, `SoftDeletes`, `LogsActivity`). Global scopes for multi-tenancy.
4. **Policies for authorization**: `Gate::policy()` registration in `AppServiceProvider`. Gate bridge routes permission-name checks through `AuthorizationService`.
5. **Enums over string constants**: PHP enums (backed) for finite state/type values.
6. **State machines**: Spatie `laravel-model-states` for lifecycle transitions (School states).
7. **Contract/Implementation separation**: Interfaces in `Contracts/`, implementations in `Services/`.
8. **Settings registry pattern**: Definitions register in a registry (sealed at boot), resolver provides values.
9. **Entitlement registry pattern**: Feature definitions register entitlement keys; resolver resolves effective values (default + optional SaaS provider).
10. **Events for domain boundaries**: Domain events for cross-module communication (e.g., Auth events, Promotion events).

### Frontend/Vue

1. **Composition API + `<script setup>`**: All Vue components use the Composition API with `<script setup lang="ts">`.
2. **TypeScript**: Strict mode, proper type definitions for props and composables.
3. **PrimeVue components**: Primary UI component library. Use PrimeVue for tables, dialogs, forms, buttons, etc.
4. **Tailwind CSS 4**: Utility-first CSS. Layout and spacing with Tailwind classes.
5. **AuthenticatedLayout must receive `crumb` prop**: Required breadcrumb array — never made optional.
6. **Pinia stores**: Global state in `resources/js/store/`.
7. **Inertia form handling**: `useForm` from `@inertiajs/vue3` for form submissions.
8. **Axios for non-form requests**: Wrapped through Inertia or direct Axios calls.

## Architectural Patterns

### Multi-Tenancy
- **`BelongsToSchool` trait** on models — adds `school_id` column, defines relationship.
- **`SchoolScope` global scope** — automatically filters all queries by current school.
- **`SchoolContext` middleware** — resolves current school from authenticated user's session.
- School is the **Laratrust Team** — authorization pivots (`role_user`, `permission_user`) use `school_id` as team foreign key.

### Authorization
- Laratrust `HasRolesAndPermissions` on User model (teams mode).
- Gate bridge (`Gate::before`) resolves permission-name abilities through `AuthorizationService`.
- Permission catalogue defined in `database/seeders/Settings/permission_catalogue_part*.php`.
- `Docent::abilities` provides the same surface for documentation validation.

### Lifecycle States (School)
```
Draft → Ready → Active → Suspended
                    ↘         ↘
                    Archived   Archived
```
Recovery from Archived is supported. Readiness checks: name, code (unique), email, primary contact, primary address.

### Feature/Entitlement Registry
- Registry is sealed at boot — no silent overwrites.
- Default profile provides code-defined values.
- Future SaaS provider implements `EntitlementValueProvider` and binds ahead of default.
- Unknown features/entitlements **fail closed**.

### Settings Registry
- Collects settings from definitions (Authentication, FileManager, SchoolOperational).
- Sealed at boot.
- Resolver provides typed setting values.

## UI Conventions

1. **Breadcrumb trail**: Every AuthenticatedLayout page provides a meaningful `crumb` array.
2. **Page titles**: Set via `title` prop on AuthenticatedLayout.
3. **Resource dialogs**: Use `ResourceDialogWrapper` for modal CRUD operations.
4. **DataTables**: Built with `resources/js/datatable/` infrastructure (PrimeVue-based).
5. **Sidebar**: Collapsible sidebar with navigation menu (Pinia store for state).

## Reusable Implementation Patterns

| Pattern | Location | Usage |
|---------|----------|-------|
| School ownership | `BelongsToSchool` trait + `SchoolScope` | All school-scoped models |
| File handling | `HasAvatar` trait (Spatie), File Manager services | Profile avatars, school branding, FM uploads |
| Custom fields | `HasCustomFields` trait | Dynamic field sets on profiles |
| Address management | `HasAddress` trait + Address service | School, Profile addresses |
| Dynamic enums | `HasDynamicEnum` trait | Configurable dropdown options |
| Activity logging | `LogsActivity` trait (Spatie) | Model change audit trail |
| Bulk actions | `Actions/BulkActions/` | Batch operations on collections |
| State machines | `HasStates` trait (Spatie) | Model lifecycle transitions |
| Table query helpers | `HasTableQuery` trait | Filtering, sorting, pagination |
| Academic period usage | `TracksAcademicUsage` trait | Academic session/term period tracking |