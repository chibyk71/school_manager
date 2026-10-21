<!-- docent:guide start -->
## Documentation (Docent)

Docs live in `resources/docs`. Before writing or editing docs, run
`php artisan docent:guide` to get the authoring dialect and this app's
registered values, links, conditions, audiences, and components. Validate
changes with `php artisan docent:check` and fix everything it reports.
<!-- docent:guide end -->

## Frontend layout conventions

- Every page using `resources/js/Layouts/AuthenticatedLayout.vue` must pass the required `crumb` prop. Do not make this prop optional to silence type errors; fix the calling page.
- Follow `docs/development/frontend-layout-conventions.md` for breadcrumb and shared-layout rules.
- For focused UI work, prioritize errors in files changed by the task. Fix regressions introduced by the change, but do not expand the scope into unrelated legacy cleanup without an explicit reason.
