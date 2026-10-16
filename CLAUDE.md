# MossyDew — project guide for Claude

Gamified task manager from the MossyTrunk ecosystem, with a moss herbarium to fill. Tasks (with a planned day and an optional deadline) live in projects or in the inbox; **Today** is a work context that gathers tasks from every project; the **Eisenhower matrix** orders every list; completing tasks earns XP and streaks; every level (flat 150 XP) crossed adds a random moss species (iNaturalist photo, rarity-weighted) to the user's herbarium, alongside achievements; collected mosses grow in an upgradable **greenhouse** that never produces over time: completed tasks bring dew (Plant most), and the bigger the greenhouse, the more dew each task brings; dew is spent on instant facility upgrades and expeditions for new mosses.
UI languages: **English and French**. URLs, code, commits: **English**.

**Business rules live in [`docs/business/`](docs/business/README.md)** — read the relevant page before touching a domain concept, and update it in the same commit when a rule changes.

Architecture and conventions mirror `~/Sites/mossyleaf-studio/mossytrunk` (read its `CLAUDE.md` when in doubt); deliberate differences are listed at the end of this file.

## Code style

- **Never write comments** — no docblocks, no inline `//`/`#`, no `<!-- -->`, no `{# #}`. Only type-only PHPDoc the type system needs (`@return list<Task>`, `@param iterable<AchievementRule>`) is allowed.
- **SOLID in every layer**: one use case per handler, one route per controller, one exception per file; extend by adding a class (an `AchievementRule`, a controller, an exception subclass), not by growing `switch`/`instanceof` branches; Domain and Application own their ports.

Generic Symfony conventions: see `AGENTS.md` (this file wins when they disagree).

## Stack

- Symfony 8.1, PHP 8.5 (FrankenPHP), Doctrine ORM 3, PostgreSQL 18, ULIDs.
- Vue 3 SPA (vue-router) mounted in one Twig shell, bundled by **Vite** through `pentatrion/vite-bundle` (`vite_entry_script_tags('app')`). The sign-in error page is a second entry (`auth`).
- Tests: PHPUnit 13 (unit + functional, DAMA rollback), Vitest (pure JS modules), Playwright (e2e).

## Running things (always in Docker)

```bash
make up              # php + database + node (Vite dev server :5174) + mock mossyleaf accounts (:8091) → http://localhost:8090
make db              # create + migrate dev DB
make fixtures        # demo data (fixtures/Story)
make migration       # doctrine:migrations:diff after mapping changes
make test            # PHPUnit; make test-unit / test-functional
make test-js         # Vitest
make deptrac / make cs / make cs-fix / make phpstan   # keep all at 0
make e2e             # Playwright against php-e2e (APP_ENV=test)
```

## Backend architecture (Onion) — `src/`

| Layer | Contains | May depend on |
|---|---|---|
| `Domain/` | Entities (rich, **no setters**), value objects, domain services (`RewardPolicy`, `AchievementReferee`), repository **interfaces**, exceptions | nothing |
| `Application/` | Use cases `<Context>/<UseCase>/{Command, Handler}`, views (`TaskView`, `PlayerView`), ports (`TaskQueries`, `PlayerStatsLedger`, `CurrentUser`, `Transaction`, `SingleSignOn`) | Domain |
| `Infrastructure/` | Doctrine repositories and read ports, security adapters (mossyleaf accounts OIDC client), console | Domain, Application |
| `Presentation/` | `Api/` JSON controllers + payload DTOs, `Web/` Twig shells and auth pages | Application, Domain |

Contexts: `Identity`, `Planning`, `Gamification`.

- Doctrine mapping = attributes on Domain entities. Dates without time (`plannedOn`, `dueOn`, streak days) are `date_immutable` built through `Domain\Shared\Day` — never `new \DateTimeImmutable()` for a day.
- **"Today" depends on the user's time zone** (`User::today($now)`, `Application\Planning\Today`): never compare days with the server clock directly.
- Invariants throw `DomainException` subclasses (`<Context>/Exception/<Violation>.php`) → 422 `problem+json` (404 for `NotFound`) through `DomainExceptionListener`, message translated from `translations/exceptions+intl-icu.<locale>.yaml`.
- Handlers are invokable, end with `Transaction::commit()`. No bus, no domain events: `CompleteTaskHandler` orchestrates task → reward → player → greenhouse dew → `AchievementCheck`.
- **Owner scoping**: every user-owned aggregate (`Task`, `Project`, …) has an `owner`; Doctrine repositories/read ports filter through `Infrastructure\Persistence\Doctrine\OwnerScope` (a new query must too). Another user's id answers 404.
- Achievements: one `AchievementRule` class per achievement (auto-tagged `app.achievement_rule`, listed by `#[AsTaggedItem(priority)]`, highest first), translations for its title/description in `assets/vue/i18n/<locale>/achievements.json`.

## Accounts & security

- **Sign-in = mossyleaf accounts** (Authentik at `accounts.mossyleaf.studio`, project `~/Sites/mossyleaf-studio/accounts`, shared with MossyTrunk): no password, sign-up or invitation code here. Accounts are invited in Authentik and need its `mossydew` group. `/login` redirects there (OIDC code flow + PKCE, `OidcSingleSignOn`), `/login/check` (`AccountsAuthenticator`) exchanges the code, reads userinfo and runs `SignInHandler`: user found by `accountId` (OIDC `sub`), else an existing user with the same email is linked, else a new user + player is created. Config: `ACCOUNTS_URL`, `OIDC_*` env vars.
- Dev and e2e use a mock OIDC server (`oidc` service, http://localhost:8091): type `demo` (fixture account) or any name plus claims `{"email": "…", "name": "…"}` for a new account. PHPUnit uses `Tests\Support\FakeAccounts` (`https://accounts.test`).
- Session firewall with the `AccountsAuthenticator`, sessions in PostgreSQL (`PdoSessionHandler`), remember-me always on (so phones stay signed in), CSRF logout that also ends the mossyleaf session. The JSON API uses the session cookie; `SameOriginGuard` rejects cross-site writes.
- Sync between devices = same account; the server is the source of truth, the SPA refetches on focus and every minute while visible.

## Frontend — `assets/`

- `app.js` mounts the SPA in `templates/app.html.twig` (`#app-session`, `#app-preload` filled by `AppShellController` through `ApiPreload`). A new page that loads data on mount adds its URL to `AppShellController::pageUrls()`.
- `vue/layouts/AppShell.vue` keeps the rail (with **PlayerProgress**: level, XP bar, herbarium link, streak) or the phone strip and tab bar mounted across routes. Pages are thin orchestrators.
- Data goes through `composables/useApi.js` (`load` = stale-while-revalidate, writes send `X-Refresh`, see MossyTrunk). Task ordering on the client uses `vue/tasks/compareTasks.js`, which must match `DoctrineTaskQueries::ordered()` (Vitest covers it).
- Interactive widgets on **Reka UI**; icons **Lucide** (`size` in rem); no native select/checkbox/date inputs.
- CSS: BEM, `<style scoped>`, tokens in `assets/styles/tokens.css`, **rem only** (except inside SVG drawings, whose transforms and font sizes are in viewBox user units). Same look as MossyTrunk: tokens derived from the user's theme (`--theme-background`, `--theme-accent` on `<html>`, chosen in Settings › Appearance, same five presets as MossyTrunk, default mossy green on light grey): never hardcode a colour outside SVG drawings. White surfaces, thin borders, `--radius` 0.5rem. Fonts: Patua One (display, lowercase `mossydew` wordmark), Inter (text), self-hosted via `@fontsource`.
- **Never hardcode a user-visible string**: vue-i18n keys from `assets/vue/i18n/<locale>/<namespace>.json` (same keys in every locale).
- Motion: one bold moment — the level-up reveal (`LevelUpReveal.vue`: level rolls over, the card flips and develops amid a spore burst). `prefers-reduced-motion` disables it.
- Assets: hand-built SVG for drawings, CC0/CC BY iNaturalist photos for the herbarium (`bin/console app:herbarium:import` after changing `SpeciesCatalog`, self-hosted in `public/herbarium/`), no AI-generated images; every third-party asset is listed with its licence in `docs/assets.md` and on `/credits`.

## Android and iPad apps — `android/`, `ios/`

- WebView shells of the production site plus a home-screen **Today** widget that calls `/widget/*` with a per-device bearer token (rules: `docs/business/apps.md`, build and install: `docs/apps.md`). A change to `GET /widget/today` updates both clients.
- Built on the Mac, not in Docker. Android: Kotlin + Jetpack Glance; `make android-sdk` once, then `make apk`, `make apk-install`, `make android-test`; strings in `android/app/src/main/res/values{,-fr}/strings.xml`; `android/keystore.*` is never committed (updates need the same key).
- iOS: XcodeGen, `ios/project.yml` is the source (generated project, plists and entitlements are git-ignored), Swift 6, strings in `ios/Shared/Localizable.xcstrings` (en + fr); `make ios` generates and opens the project (needs Xcode), `make ios-typecheck` checks both targets with the Command Line Tools only.

## Testing expectations

- PHPStan level 10 on `src`, `tests`, `fixtures`; ignores only in `phpstan.dist.neon`.
- Unit test every business rule; functional test every handler (KernelTestCase, real DB) and endpoint (WebTestCase); Playwright for user journeys.

## Differences from MossyTrunk

- Vite + vue-router SPA instead of Encore + UX Vue + Turbo (the rail progress stays mounted between pages).
- Owner scoping instead of workspaces; every user has their own data.
- Vitest for pure JS modules.

## Git

One commit per feature, conventional messages. No co-author lines.
