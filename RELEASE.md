# Local-First Release-Workflow (Stand: 2025-09-14)

Ziel: Reproduzierbare Qualität vor Live. Reduziert Rückläufer nach Deploy.

## Prinzipien
- Local = Prüfbett: Parser-Änderungen lokal verifizieren (Auto-Eval, Real-Data-Refresh, Frontend-Smoke).
- Determinismus: Fixtures + definierte Rezept-Seeds → gleiche Ergebnisse auf jeder Dev-Box.
- No surprises: Feature-Flag `SITC_ING_V2` und Query-Override `?engine=` erlauben kontrollierte Umschaltung.

## Pflichtlauf vor jedem Deploy
1) Auto-Eval grün
- Beiträge → „Validierung“ → alle Fixtures grün (Engine: auto, mod, legacy bei Bedarf).
- Golden-Gruppe (Qty) grün: tools/parser_lab/auto_eval_web.php zeigt Abschnitt „Golden (Qty)“ mit 100% Pass für alle betroffenen Fälle.
- Bei Rot: nicht deployen.

2) Real-Data-Refresh
- Beiträge bearbeiten → Meta-Box „Rezept-Tools“ → „Rezept aktualisieren“ (Parser v2).
- Erwartung: Keine Fatals, keine „empty source“.
- Inline-Log (Dev-Mode aktiv): keine neuen Fehlercluster.

3) Frontend-Smoke (lokal)
- 3–5 repräsentative Seeds-Posts (siehe docs/SEEDS.md):
  - Ansicht mit `?engine=mod` vs. `?engine=legacy` vergleichen.
  - Prüfen: Qty (Brüche/Ranges), Units (Mapping), Notes (Klammern/Komma), Item sauber.
  - Erwartung: Modular ≥ Legacy. Auto-Fallback greift still bei Low-Confidence/Fehlern.

4) Logs sauber
- Beiträge → „Validierung“ → Tab „Debug-Log“: keine anhaltenden Exceptions.
- Optional Log leeren → kurzer erneuter Lauf.
- „Array to string“/Strict-Types-Fehler: 0.

## Release-Prozess
1) Changelog aktualisieren
- Kurz notieren: Flag-Änderungen, Parser-Fixes, Logging/UI-Tools.

2) Flag-Strategie
- Privat/Dev: `SITC_ING_V2 = ON (Global)` → wp-config.php oder per Default (siehe `includes/settings.php`).
- Öffentlich: erst Beta (kleiner Scope/Segment), dann 100%.

3) Deploy
- Hochladen nur, wenn Pflichtlauf erfolgreich.
- Nach Deploy: kurzer Frontend-Smoke auf Live mit `?engine=auto|mod|legacy`.

4) Rollback-Pfad
- Im Fehlerfall: `SITC_ING_V2 = OFF` → sofort Legacy überall.
- Optional: Plugin-Version zurückrollen.

## Observability (lokal)
- Inline-Log bei Refresh-Fehlern: sofortiger Kontext (siehe `includes/refresh.php`).
- Debug-Log Viewer: Beiträge → Validierung → Debug-Log (Tail, Reload, „Log löschen“).
- Logging nur bei `WP_DEBUG` oder Dev-Mode aktiv (siehe `includes/debug.php`).

## Datenbasis
- Fixtures: zentral in `tools/parser_lab/fixtures` gepflegt; Pfade stabil.
- Rezept-Seeds: 3–5 echte Posts, die wir immer für Smoke nutzen. IDs in `docs/SEEDS.md` dokumentieren.
- Golden Fixtures (Qty): `tools/parser_lab/fixtures/golden/YYYYMMDD/*.txt` + `.expected.json` je Fall.

## QA-Checkliste (Mini)
- [ ] Auto-Eval grün (alle relevanten Fixtures).
- [ ] 3–5 Seeds: Modular ≥ Legacy (Qty/Unit/Notes/Item).
- [ ] Keine Fatals/Warnings im Dev-Badge/Log.
- [ ] Fallback arbeitet still (wenn nötig).

## Umsetzung im Plugin (Status)
- Feature-Flag: `SITC_ING_V2` (Default ON) in `includes/settings.php`.
- Engine-Override: global `?engine=legacy|mod|auto` in `skipintro-recipe-crawler.php`.
- Auto-Eval UI: Beiträge → „Validierung“ (`includes/Admin/ValidationPage.php`, `ValidationRunner.php`).
- Debug-Log: dev-only, Rotation in `uploads/skipintro-recipe-crawler/debug.log` (`includes/debug.php`).
- Real-Data-Refresh: Post → „Rezept aktualisieren“ mit Inline-Log bei Fehlern (`includes/refresh.php`).
- Frontend Dev-Badge: Pipeline/Engine/Flags im Dev-Mode (`includes/renderer/dev_badge.php`).

## Nicht-Ziele
- Kein direktes Testen auf Live ohne Local-Pflichtlauf.
- Keine öffentlichen Debug-UIs/Badges (Dev-Mode gated).

## Warum das mehr Wert bringt
- Reproduzierbarkeit: gleiche Seeds/Fixtures ⇒ gleiche Ergebnisse.
- Vorab-Transparenz: Fehler sichtbar im Local-Log statt „plötzlich online“.
- Schnelleres Debugging: Query-Override + Inline-Logs sparen Upload-Runden.

## Preflight (Strict Types Guard)
Before release or merging, run the strict guard to ensure no dev/UI tools use strict_types and that core files start cleanly without BOM or leading whitespace.

Command:

- php tools/dev/strict_guard.php           # report only
- php tools/dev/strict_guard.php --fix     # auto-fix BOM/whitespace and strict_types placement/removal

Policy summary:
- Allowed: includes/ingredients/**, includes/parser*.php (declare(strict_types=1) at top, directly after <?php)
- Forbidden: tools/**, includes/renderer/**, includes/admin/** (no strict_types)
