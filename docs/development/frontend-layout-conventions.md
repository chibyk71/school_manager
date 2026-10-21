# Frontend layout conventions

## AuthenticatedLayout breadcrumbs

Every page that uses `AuthenticatedLayout` must provide the required `crumb` prop.

- Keep `crumb` required in `resources/js/Layouts/AuthenticatedLayout.vue`. Do not make it optional merely to silence TypeScript errors.
- Fix missing breadcrumbs in the calling page. A page that omits `crumb` violates the layout contract.
- Supply a meaningful breadcrumb trail that reflects the page's place in the application. Use labels users understand and URLs for navigable ancestors where appropriate.
- When changing a shared layout's props, inspect its callers and preserve the established contract unless there is an intentional, documented design change.
- Treat TypeScript errors in files touched by the current task as potential regressions and fix those. Do not let unrelated legacy errors expand a focused UI task into a repository-wide cleanup.

Example:

```vue
<AuthenticatedLayout
    title="Users"
    :crumb="[
        { label: 'Dashboard', url: route('dashboard') },
        { label: 'User Management' },
        { label: 'Users' },
    ]"
>
    <!-- page content -->
</AuthenticatedLayout>
```

Adjust breadcrumb labels and routes to match the actual page hierarchy and available named routes. Do not copy the example routes blindly.
