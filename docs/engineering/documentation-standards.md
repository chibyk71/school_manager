# School Manager — Code Documentation Standard

## 1. Purpose

Code documentation in School Manager must preserve not only implementation details but also the business reasoning, architectural decisions, assumptions, constraints, and intended behaviour behind the implementation.

A future developer should be able to understand why a workflow exists and what must remain true when modifying it, without having to reconstruct the original design discussions.

Documentation is part of the implementation, not an optional finishing activity.

## 2. Method and Function Documentation

All public methods, service operations, and important internal methods must have meaningful PHPDoc.

Documentation must explain:

- **Purpose:** What the method does and why it exists.
- **Parameters:** Every parameter, its PHP type, semantic meaning, expected values, and relevant constraints.
- **Return value:** The PHP return type and what the returned value represents. Explain `void` where the operation's effects are important.
- **Preconditions:** What must already be true before the method is called.
- **Expected behaviour:** How the method behaves, including important branches and relevant business rules.
- **Postconditions:** What should be true after successful execution.
- **Side effects:** Database changes, session changes, events, notifications, external calls, or other state changes.
- **Failure conditions:** Relevant exceptions, validation failures, and authorization failures.

Do not merely restate the method name or translate its implementation into English.

## 3. Domain and Business-Rule Documentation

Document important business rules and invariants close to the code that enforces them.

Explain the reasoning behind rules that could otherwise appear unnecessary, restrictive, or unconventional.

Examples include:

- Why a Person can exist without an Authentication Account.
- Why email equality alone cannot establish Person identity.
- Why School workspace access depends on explicit membership.
- Why a Provisioned Authentication Account cannot authenticate.
- Why changing a Person's primary email resets authentication email verification.

Documentation should explain both what the rule requires and why the system follows it.

## 4. Architectural Decision Documentation

Decisions affecting multiple classes, services, modules, or future workflows must be recorded in the appropriate architecture or domain documentation.

Where a decision is significant, use an Architecture Decision Record (ADR) containing:

- Context and problem.
- Decision made.
- Alternatives considered where relevant.
- Reasons for selecting the chosen approach.
- Consequences and trade-offs.
- Constraints future implementations must respect.

Do not rely on inline comments alone to preserve decisions with broader architectural significance.

## 5. Inline Comments

Use inline comments to explain non-obvious implementation choices, security restrictions, domain invariants, and behaviour that a future developer might mistakenly simplify or remove.

Prefer explaining **why** the code behaves a certain way rather than describing what an obvious line of code does.

Comments should provide useful context without duplicating the implementation.

## 6. Documentation Maintenance

Whenever implementation behaviour changes:

- Review the affected PHPDoc.
- Update relevant inline comments.
- Update domain or architecture documentation when the change affects documented rules.
- Update ADRs when a previously accepted decision is superseded.
- Remove obsolete or misleading comments.

Outdated documentation is a correctness problem.

## 7. Tests and Documentation

Documentation explains intended behaviour and architectural reasoning. Tests verify executable behaviour.

Neither replaces the other.

Security-sensitive and domain-critical invariants should have meaningful automated tests wherever practical.

## 8. Quality Standard

Documentation must be sufficiently detailed for a developer who did not participate in the original design discussions to understand:

1. What the code is responsible for.
2. Why the responsibility exists.
3. What inputs and outputs mean.
4. Which conditions and constraints apply.
5. What side effects occur.
6. Which business or architectural decisions must not be violated.
7. What should be considered before changing the implementation.

Avoid superficial, repetitive, or misleading comments. Prioritize clarity, accuracy, and preservation of design intent.
