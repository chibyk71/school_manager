---
title: Student administration notes
description: Administrative guidance for staff who can view and manage student records.
order: 3
authorize: student.view
search:
  keywords:
    - student administration
    - student permissions
    - manage students
---

# Student administration notes

This page is limited to users who hold the `student.view` permission (directly or through an effective role).

## Administrative checklist

When reviewing student data:

1. Confirm you are operating in the correct active school.
2. Prefer list filters over exporting bulk data unless your role requires it.
3. Treat guardian contact details as sensitive.
4. Use restore / force-delete flows only when your role explicitly allows them.

## Conditional detail

:::can ability="student.update"
You can update student profile fields where the UI exposes edit actions. Changes are scoped to the active school and audited according to School Manager activity logging.
:::

:::can ability="student.delete"
Soft-delete is available for roles that include `student.delete`. Soft-deleted students can be restored when `student.restore` is also granted.
:::

## Related

- [Students overview](overview)
- [Viewing a student](viewing-a-student)
