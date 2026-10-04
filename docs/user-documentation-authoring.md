# User documentation authoring (Docent)

User-facing documentation is separate from the engineering docs in this `docs/` directory.

| Location | Audience | Purpose |
|----------|----------|---------|
| `docs/` | Developers | Architecture, phase notes, engineering decisions |
| `resources/docs/` | Authenticated School Manager users | Product how-to guides |

## Source of truth

- Markdown files under `resources/docs/` are committed to Git.
- Do **not** use Docent’s optional database authoring CMS for product docs (`docent.database.enabled` stays `false`).
- Do **not** invent documentation-specific roles or permissions.

## Structure

```
resources/docs/
  index.md                 # Landing
  getting-started/
    introduction.md
  students/
    _group.yml             # Nav group title/order
    overview.md
    viewing-a-student.md
    administration.md      # Restricted page example
    images/
      *.svg|*.png
```

## Front matter

Common keys:

- `title`, `description`, `order`
- `search.keywords` — search ranking aliases (not rendered)
- `authorize: student.view` — page requires an existing School Manager permission name
- `layout: landing` — optional chrome variant

Example:

```yaml
---
title: Viewing a student
description: Open and read a student profile safely.
order: 2
search:
  keywords:
    - view student
    - student profile
authorize: student.view
---
```

Use **real** permission names from the permission catalogue (`permissions.name`). Do not invent `documentation.*` permissions.

## Conditional content

```markdown
:::can ability="student.view"
Visible only when Authorization allows the ability for the current user/school context.
:::

:::cannot ability="student.view"
Visible when the user lacks the ability.
:::
```

## Links

- Internal doc links: relative Markdown links (`[Overview](overview)`).
- Application → documentation: use the Docent route name, e.g. `route('docent.docs.home')` or `route('docent.docs.show', 'students/overview')`. Do not hard-code `/docs/...` in application code.

## Images

Store images next to the page (e.g. `students/images/...`) and reference them with relative paths. Images inherit the docs site middleware (authenticated). Do **not** put sensitive, permission-gated screenshots in the corpus — image protection is site-level, not page-level.

## Validation

```bash
php artisan docent:guide
php artisan docent:check --strict
```

Fix every strict check failure before merging documentation changes.

When product behaviour changes in a way that affects users, update the relevant pages under `resources/docs/`.
