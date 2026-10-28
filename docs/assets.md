# Assets and licences

Every visual and sound asset shipped with MossyDew, with its source and licence. No AI-generated images.

| Asset | Source | Licence |
|---|---|---|
| Dewdrop check, moss tuft | Hand-drawn SVG in `assets/vue/components/tasks/DropCheck.vue`, `ui/EmptyState.vue` | Project code |
| Herbarium moss photos | iNaturalist observers, imported by `bin/console app:herbarium:import` into `public/herbarium/`; author, licence and source of each photo in `assets/vue/herbarium/species.json` | CC0 or CC BY (only these are imported) |
| App icon | Lucide `Leaf` on a moss square: `public/icons/mossydew.svg`, PNG exports `mossydew-192.png` / `mossydew-512.png` / `mossydew-maskable-512.png`, Android `ic_launcher_*.xml`, iOS `AppIcon.png` | ISC |
| Dewdrop sound | Synthesised at runtime with the Web Audio API (`composables/useSound.js`) | Project code |
| Patua One (display font) | https://fonts.google.com/specimen/Patua+One via `@fontsource/patua-one` | SIL OFL 1.1 |
| Inter (text font) | https://rsms.me/inter/ via `@fontsource-variable/inter` | SIL OFL 1.1 |
| Icons | Lucide, `@lucide/vue` | ISC |

Add any new third-party asset here **and** on the `/credits` page (`assets/vue/pages/CreditsPage.vue`).
