## Changelog-Regel

- Neue Einträge immer oben (neu → alt). Keine Umbauten bestehender Blöcke.
- Prüfen: `php tools/dev/changelog_guard.php`

## Local-First Release-Workflow

- Anleitung: siehe `RELEASE.md` (Pflichtlauf, Flags, Smoke-Checks, Debug-Log).
- Seeds-Dokumentation: `docs/SEEDS.md`


### Strict Types Policy
- Core (parser, ingredients): strict_types allowed and encouraged; must appear as the first statement after `<?php`.
- Dev/Tools and UI (tools/**, includes/renderer/**, includes/admin/**): no strict_types; scripts must start cleanly with `<?php` (no BOM/whitespace/comments before).
- Use `tools/dev/strict_guard.php` to check/fix these rules locally.
### Minimal Syntax Policy
- Use classic control structures only; avoid `match`, arrow functions (`fn () =>`), and destructuring assignments (`[$a, $b] = ...`).
- Prefer explicit conditionals (`isset(...) ? ... : ...`) and anonymous functions declared with `function (...) { ... }`.
- Stick to compatible syntax so parser_lab tools and legacy PHP runtimes never hit "unexpected token" parse errors.
