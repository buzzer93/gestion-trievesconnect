# Project Context

## Project summary

This is a Symfony project. The exact stack, architecture, commands, and deployment details are described in `.claude/project-profile.md`.

## Main goals

Claude should help with:
- implementing features faster;
- avoiding bugs and regressions;
- improving readability and maintainability;
- respecting the selected architecture;
- reviewing code changes after implementation;
- writing or updating tests when business logic changes;
- identifying security risks before sensitive changes.

## Working principles

- Understand the existing code before modifying it.
- Prefer simple, explicit, maintainable solutions.
- Avoid unnecessary abstractions.
- Do not introduce new dependencies unless they clearly improve robustness or maintainability.
- Keep controllers thin and move business logic to appropriate services, handlers, or domain classes.
- Keep Twig templates focused on presentation.
- Test every meaningful business implementation.

## Project files

Important project context:
- `.claude/project-profile.md`
- `.claude/rules/code-style.md`
- `.claude/rules/symfony.md`
- `.claude/rules/architecture.md`
- `.claude/rules/doctrine.md`
- `.claude/rules/twig.md`
- `.claude/rules/frontend.md`
- `.claude/rules/testing.md`
- `.claude/rules/security.md`
- `.claude/rules/deployment.md`

## Common checks

Before considering a task complete, Claude should usually check:
- code readability;
- architecture consistency;
- tests to add or update;
- security impact;
- possible regressions;
- GrumPHP compatibility when configured.

## Sensitive areas

For payment, authentication, authorization, deployment, migrations, and security-related changes, Claude should first explain the plan before editing code.

## Design system

If a `DESIGN.md` file exists at the project root, Claude must use it as the active design system for frontend work.

If `.claude/design/design-brief.md` exists, Claude must combine it with `DESIGN.md` and the existing Twig/Tailwind conventions.

Do not load the full `.claude/_templates/design/` catalog unless the user asks to choose or compare design templates.