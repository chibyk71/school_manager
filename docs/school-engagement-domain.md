# School Engagement Domain (Discovery)

> **Status:** Architectural / product discovery only.  
> **Not** an implementation phase.  
> **Not** “Academic Session/Term Phase 6.”

## Why this document exists

After Academic Session, Term, and Academic Context (Phases 1–5), the next candidate was a simple **Academic Calendar** (CRUD events on the academic period). Product analysis showed that scope was too narrow.

School events are not only passive calendar facts. They carry audience, visibility, communication, reminders, and often operational work. Treating “calendar” as the whole domain would force a second redesign once notices, staff vs parent experiences, and reminders appear.

**Decision:** Do **not** implement Academic Calendar as the next Academic Session/Term phase. Extract the concern into a future domain: **School Engagement**. Calendar becomes a presentation/use case of events, not the domain itself.

## New domain: School Engagement

Conceptual shape (sub-domains, not modules to build yet):

```
School Engagement
├── Events & Calendar
├── Notices & Announcements
├── Notifications & Reminders
├── Audience & Visibility
├── Scheduling
└── Operational Tasks
```

Implementation, if pursued, must be broken into **separate discovery-backed phases** after this boundary is accepted. This PR only records the boundary.

## Core distinctions

### 1. Event

An **event** is an operational or scheduled occurrence for a school, optionally tied to an academic session and/or term.

Examples: resumption, examination, PTA, inter-house sports, cultural day, staff retreat, staff development, public holiday, school activity.

### 2. Audience

**Audience** is *who the event is for* (relevance), not necessarily who sees it in a public feed.

Examples: public, students, parents, staff, teachers, administrators. Future targeting may include sections, classes, selected people or groups. **Do not** build a generic segmentation engine in early work.

### 3. Visibility

**Visibility is separate from audience.** An event can be relevant to a group without being publicly listed.

Conceptual levels (not implemented): `PUBLIC`, `AUTHENTICATED`, `AUDIENCE_ONLY`, `PRIVATE`.

### 4. Participation

Audience ≠ participation. An examination may be relevant to all parents while only specific students and staff participate. Attendance/participation records are **out of scope** for early discovery.

### 5. Event lifecycle (candidate)

Prefer an explicit **business** lifecycle over state derived only from dates:

| State       | Note |
|-------------|------|
| `DRAFT`     | Not published |
| `SCHEDULED` | Published plan (`SCHEDULED` preferred over `UPCOMING`; upcoming is temporal interpretation) |
| `ONGOING`   | In progress |
| `COMPLETED` | Finished |
| `CANCELLED` | Cancelled |

Dates remain important for display and reminders; they do not replace lifecycle.

### 6. Notifications and reminders

Events (and notices) may configure policies such as: 7 days before, 1 day before, 1 hour before, at start.

**Policy configuration** must stay separate from **delivery** (mail, SMS, in-app). Existing notification channels and school SMS/email settings are infrastructure to reuse later—not something to reimplement inside Engagement.

### 7. Notices / announcements

A notice board likely belongs in the same broader domain. A notice may eventually have title/content, category, priority, audience, visibility, publish/expiry, attachments, notification policy, and read/acknowledgement behaviour, and may link to an event.

**Existing code (do not redesign in this discovery):**

- `App\Models\Communication\Notice` — school-scoped, `title` / `body`, `is_public`, `effective_date`, sender
- `NoticeController`, `NoticePolicy`, store/update requests
- Settings under Communication (email, SMS, OTP, templates) and general notification settings
- Lifecycle-oriented reminders elsewhere (e.g. admission/enrollment reminder commands and notifications)

Future Engagement work should **evaluate reuse or evolution** of Notice rather than inventing a parallel notice system without inspection.

### 8. Operational tasks (possible future sub-domain)

Events may spawn coordinated work (e.g. staff retreat: book venue, agenda, facilitators, notify staff; examination: papers, invigilators, rooms, notify parents).

That is **not** a mandate to build a generic task or workflow engine now.

## Relationship to Academic Session / Term / Context

Academic Session and Term remain the **authoritative** academic-period model. Academic Context (`Academic` facade / `AcademicSessionService`) remains the **authoritative** resolver for the school’s current operational session and active term.

Engagement **references** academic context where business meaning requires it. It must **not** introduce a second session/term or “current period” system.

```
School
  └── Academic Session
        └── Term
              └── Engagement Event   (session-level or term-level as appropriate)
```

An event may be school-wide, session-scoped, or term-scoped according to meaning (e.g. term exam vs session resumption).

**Non-goal of this discovery:** any change to Session/Term lifecycle, Academic Context, or activation/pause/close semantics.

## Relationship to Academic Period Usage Registry

The dependency registry (`academic_period_usages`, `TracksAcademicUsage`) exists to protect **genuinely operational** academic-period data for lifecycle guards.

**Creating an engagement event must not, by default, register usage or lock periods.** Only a future, explicit business rule may opt specific engagement resources into tracking. Do not modify the registry as part of establishing this domain boundary.

## What exists nearby (inventory, not redesign)

| Area | Examples in codebase |
|------|----------------------|
| Notices | `Communication\Notice`, `NoticeController` |
| Feedback | `Communication\Feedback` |
| Notifications | Laravel notifications under `app/Notifications`, SMS channel concern, school SMS/email settings |
| Lifecycle reminders | Student admission/enrollment reminder notifications and commands |
| Timetable | Academic timetable models/services (scheduling of lessons—not school engagement events) |
| Academic periods | Session/Term states, lifecycle services, Academic Context |

Timetable and Engagement events are different concerns: timetable slots are instructional schedule; engagement events are school operational/calendar occurrences.

## Explicitly deferred (do not implement in this PR)

- `AcademicCalendarEvent` (or any Engagement event) model, migrations, CRUD, API, Vue, FullCalendar
- DynamicEnum event types, recurrence, attendance
- Audience tables, visibility implementation, participant tables
- Notification delivery, reminder jobs, notice-board rewrite
- Operational task scheduler, generic segmentation or workflow engines
- Changes to Academic Session/Term lifecycle, Academic Context, or usage registry
- Unrelated refactoring

## Architectural principles and non-goals

**Principles**

1. Calendar is a **view** of events, not the domain name or sole aggregate.
2. Audience, visibility, and participation are distinct.
3. Business lifecycle is not only “before/after date.”
4. Notification **policy** ≠ notification **delivery**.
5. Academic periods stay owned by Academic; Engagement consumes context, does not redefine it.
6. Prefer phased discovery before large schemas.

**Non-goals**

- Shipping calendar UI in this milestone.
- Absorbing all Communication settings into Engagement overnight.
- Treating every notice or timetable row as an Engagement event without product rules.

## Next steps (after this boundary is accepted)

1. Product discovery: prioritize Events vs Notices vs Reminders for the first implementation slice.
2. Map existing Notice fields to future Engagement concepts (gaps: expiry, priority, acknowledgement, event link).
3. Define minimal event model only when a concrete slice is approved.
4. Plan integration points with Academic Context (read-only) and notification infrastructure (reuse).
5. Explicitly decide whether any event types ever opt into the academic usage registry.

Until those decisions exist, **no implementation** of School Engagement features should proceed under the Academic Session/Term phase numbering.
