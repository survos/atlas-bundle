# Atlas Bundle — Agent Notes

Bundle-specific rules for agents working in `bu/atlas-bundle/`. The workspace-wide bundle conventions in `bu/AGENTS.md` apply on top of this; this file only adds what's specific to atlas.

## Scope

This bundle is **compile-time discovery infrastructure** for the rest of survos:

- It walks controllers (via the `container.service_subscriber` tag) and entity directories.
- It records what it finds into a runtime `Atlas` service.
- That's it.

It is **not**:

- A metadata bundle. Domain attributes like `EntityMeta` and `RouteMeta` live in `field-bundle`. Atlas just records the raw attribute payloads.
- A sitemap generator, a route translator, an admin dashboard, or an MCP server. Those are downstream consumers and belong in their own bundles.
- A runtime scanner. Nothing reflects on classes at runtime — everything happens in `AtlasPass` at compile time.

## Design invariants — don't break these without a deliberate decision

1. **Attributes are stored as `['class' => FQCN, 'args' => array]`, never as instantiated objects.** This keeps the snapshot trivially serializable (for `atlas:export`) and means a broken/missing attribute class can't blow up the Atlas. If you need typed access, instantiate in the consumer.

2. **A `#[Route]` without an explicit `name:` is skipped.** Atlas needs a stable identifier and won't guess at Symfony's name-derivation rules. This is intentional — document it in the consumer if a user runs into it.

3. **Builders (`ControllerAtlasBuilder`, `EntityAtlasBuilder`) are pure compile-time helpers.** They are not services, do not get registered in the container, and have no runtime dependencies. Consumers may call them directly from their own compiler pass, or they may read the populated `Atlas` service.

4. **`Atlas` is immutable after construction.** No `add*()` / `set*()` methods. The whole point of a compile-time atlas is that the snapshot is fixed.

5. **The bundle ships exactly one console command: `atlas:export`.** No `atlas:list`, no `atlas:show`, no `atlas:diff`. Tabular browsing is a downstream concern. If a consumer wants a richer view, they ship their own command.

## Adding a new attribute kind

There is nothing to add to atlas-bundle when a consumer introduces a new attribute. Atlas already records every class- and method-level attribute as `{class, args}`. Consumers filter by FQCN with `Atlas::routesWithAttribute()` / `Atlas::entitiesWithAttribute()` or by reflecting on `RouteEntry::$methodAttributes` themselves.

## Adding a new discovery surface

If you need to discover something other than controllers and entities (e.g. messenger handlers, workflows), add a new builder under `src/Compiler/` — for example `WorkflowAtlasBuilder` — and a corresponding `WorkflowEntry` model. Wire it into `AtlasPass` so it populates an additional argument on the `Atlas` service. Do **not** add a runtime scanning service.

## Dependencies

- Required: `symfony/dependency-injection`, `symfony/finder`, `symfony/routing`, `symfony/console`, `symfony/yaml`, `symfony/http-kernel`.
- Forbidden without strong justification: anything domain-specific (api-platform, doctrine/orm, meili, etc.). Atlas describes what's there; it does not interpret it.

## Symfony version

Symfony 8 only (`^8.0`). PHP 8.4+. Do not add `^7.0` constraints on a "just in case" basis.
