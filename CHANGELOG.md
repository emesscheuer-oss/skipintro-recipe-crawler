# Changelog - SkipIntro Recipe Crawler

## [0.5.7] - 2025-09-02
### Fixed
- Mengen-Parser robust gegen Unicode- und ASCII-Brüche (½, ⅓, ¼, ⅔, ¾ sowie 1/2, 1 1/2, 1 /2), Dezimalzahlen mit Komma/Punkt und Bereiche (2–3 / 2-3). Beide Bereichsenden werden korrekt skaliert und in DE-Format angezeigt.
- Ausgabe der Mengen konsistent in deutscher Locale (Komma), ganzzahlnahe Werte werden gerundet, ansonsten max. 2 Dezimalstellen.
- Freitext wie „Saft einer halben Zitrone“ wird als 0,5 Stück Zitrone (Note „Saft“) modelliert und korrekt skaliert.
### Changed
- Renderer und Frontend-Skalierung auf Bereichsmodell (low/high) erweitert; Datenattribute `data-qty-low`/`data-qty-high` für Bereiche.
### Files
- includes/parser.php
- includes/renderer.php
- assets/js/recipe-frontend.js
- skipintro-recipe-crawler.php
- PROJECT_PLAN.md

## [0.5.6] - 2025-09-01
### Fixed
- Zutaten-Duplikate entfernt: Mergen mit Priorität JSON-LD > Microdata > DOM; DOM wird nur für fehlende Felder als Fallback herangezogen.
- Nach dem Mergen werden Zutaten zeilenbasiert dedupliziert (insensitiv gegenüber Groß-/Kleinschreibung, Diakritika und Whitespace), sodass doppelte Einträge wie „1 Lorbeerblatt“ nicht mehrfach erscheinen.
### Files
- includes/parser.php
- skipintro-recipe-crawler.php

## [0.5.5] - 2025-08-29
### Changed
- Ausgabe der Einheiten auf Deutsch umgestellt (EL/TL, g, kg, ml, l, …) mit Alias‑Mapping; Parser kann intern weiterhin kanonische Kurzformen verwenden.
- Zahlenformat für Mengen konsistent mit Dezimal‑Komma; Brüche (½, 1/2, 1 1/2) werden korrekt erkannt und skaliert.
### Files
- includes/renderer.php
- DEV_NOTES.md
- tools/UNIT_EXAMPLES.md
- skipintro-recipe-crawler.php

## [0.5.4] - 2025-08-29
### Fixed
- Refresh-Button jetzt klar sichtbar: zusätzlich im „Veröffentlichen“-Block (`post_submitbox_misc_actions`) und weiterhin in eigener Meta‑Box.
- Button nur für berechtigte Nutzer (`edit_post`) sichtbar; Nonce wird per URL-Nonce geprüft.
- Klick führt zuverlässig die Refresh-Logik via `admin-post.php` aus (GET/POST), inkl. Erfolg-/Fehler-Hinweisen.
### Files
- includes/refresh.php
- skipintro-recipe-crawler.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.3] - 2025-08-29
### Added
- Backend-Button „Rezept aktualisieren“ in der Beitragsbearbeitung (nur mit Berechtigung), inkl. Nonce-Prüfung.
- Refresh-Logik mit Parser v2: Quelle bestimmen (schema.url → canonical/og:url → gespeicherte Quelle), Ergebnis validieren (Flags/Confidence) und nur parser-verwaltete Felder selektiv überschreiben.
- Lock-Liste per Post-Meta (`_sitc_lock`) zum Schutz manueller Änderungen (z. B. `title`, `description`, `image`, `ingredients`, `instructions`, `times`, `yield`, `nutrition`, `rating`).
- Metadaten setzen: `_sitc_last_refreshed` (UTC), `_sitc_parser_version` (aktuelle Plugin/Parser-Version), `_sitc_refresh_log` (gekappte JSON-Historie).
- Dry-Run in Dev-Mode (Option): zeigt Diff, schreibt nichts.
- Einfache Dev-Mode-Option (Settings), die die Sichtbarkeit des Dry-Run steuert (statt `WP_DEBUG`).
- Interne Hooks: `sitc_before_refresh` und `sitc_after_refresh` für Add-ons.
### Changed
- Plugin-Version auf 0.5.3 angehoben.
### Files
- includes/refresh.php
- includes/settings.php
- includes/admin-page.php
- skipintro-recipe-crawler.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.2] - 2025-08-29
### Added
- Neue Parser-Pipeline `parseRecipe()` mit Priorität JSON-LD > Microdata > DOM, inkl. Normalisierung (Zeiten, Bilder, Autoren), Flags und Confidence-Score.
- Wrapper `sitc_parse_recipe_from_url_v2()` mit Legacy-Mapping und Meta (schema_recipe, sources, confidence, flags).
### Changed
- Frontend-Importer nutzt den neuen Parser (v2) und persistiert zusätzliche Metadaten.
- Admin-Importer auf v2 umgestellt; kanonische URL als Quelle bevorzugt.
- Parser-Hilfsfunktionen für Zeit-/Zutaten-Parsing, HowTo-Flattening und Noise-Filter ergänzt.
### Files
- includes/parser.php
- includes/frontend-import.php
- includes/admin-page.php

## [0.5.1] - 2025-08-28
### Added
- Projekt-Tagebuch angelegt (Roadmap F0–F5).
- CHANGELOG.md erstellt, um künftige Änderungen zentral zu dokumentieren.

### Fixed
- Noch nichts.

### Changed
- Noch nichts.

---
## Format
- [Version] – Datum
- Blöcke: Added, Changed, Fixed, Removed.
