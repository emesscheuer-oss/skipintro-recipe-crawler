## [0.5.15] - 2025-09-04
### Fixed
- .gitattributes erweitert, Zeilenenden auf LF für alle Textdateien normalisiert
### Files
- .gitattributes
- CHANGELOG.md
- PROJECT_PLAN.md
- includes/admin-page.php
- includes/frontend-import.php
- includes/refresh.php
- includes/renderer.php
- includes/settings.php
- notes.html
- skipintro-recipe-crawler.php
- temp_renderer_dump.txt (removed)

## [0.5.13] - 2025-09-04
### Fixed
- Mengen: Vor-Normalisierung (ca./circa/etwa/ungef./about/approx.), Unicode/ASCII-Brueche, Dezimal (Komma/Punkt), Bereich a-b skaliert beide Enden; Anzeige immer DE-Format (Komma), kein Rohstring in der Ausgabe.
### Files
- includes/renderer.php
- CHANGELOG.md
- PROJECT_PLAN.md
## [0.5.12] - 2025-09-04
### Fixed
- Quarantaene Temp-Dateien; Encoding UTF-8 normalisiert
### Files
- includes/renderer.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.11] - 2025-09-04
### Fixed
- Stabilitaetshotfix: Safe Mode + Fallback-Renderer. Parser/Renderer mit Guardrails (try/catch, Flags) toeten das Frontend nicht mehr. Safe Mode deaktiviert Gruppierung und Skalierung; einfache Zutatenliste als vorlaeufige Darstellung.
### Files
- includes/settings.php
- includes/admin-page.php
- includes/renderer.php
- skipintro-recipe-crawler.php

## [0.5.10] - 2025-09-03
### Fixed
- Zutaten-Gruppen: Verhindert Fehlklassifikation von echten Zutatenzeilen als ZwischenÃ¼berschrift (z. B. â€žÂ½ TK Enten â€¦â€œ wurde durch das Wort â€žEnteâ€œ irrtÃ¼mlich als Header erkannt). Heuristik prÃ¼ft jetzt zunÃ¤chst auf Mengen/Einheiten und wertet solche Zeilen nicht als Header.
### Files
- includes/renderer.php

## [0.5.9] - 2025-09-02
### Fixed
- Vor-Normalisierung fÃ¼r Mengen: Entfernt generische ZusÃ¤tze wie "ca.", "circa", "etwa", "ungef.", "about", "approx.", "approximately" am Zeilenanfang bzw. direkt vor der Zahl sowie unmittelbar nach der Menge (z. B. "60 (ca.) ml").
- Vereinheitlicht Whitespaces um "/" und Bereichsseparatoren (en/em dash, "-") vor dem Mengen-Parsing. Dadurch werden Zeilen wie "ca. 60 ml Ã–l (ca.)" korrekt als skalierbare Basis erkannt.
### Files
- includes/parser.php
- skipintro-recipe-crawler.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.8] - 2025-09-02
### Fixed
- Generische De-Dupe-Logik fÃ¼r Zutaten nach dem Merge (JSON-LD > Microdata > DOM). Normalisiert Menge (inkl. Bereiche), Einheit (kanonisch) und Zutatentext (Whitespace/Case/Diakritika/StopwÃ¶rter/Lemma) zu einem robusten SchlÃ¼ssel; doppelte EintrÃ¤ge werden zusammengefÃ¼hrt, Notizen vereinigt.
### Files
- includes/parser.php
- skipintro-recipe-crawler.php

## [0.5.7] - 2025-09-02
### Fixed
- Mengen-Parser robust gegen Unicode- und ASCII-BrÃ¼che (Â½, â…“, Â¼, â…”, Â¾ sowie 1/2, 1 1/2, 1 /2), Dezimalzahlen mit Komma/Punkt und Bereiche (2â€“3 / 2-3). Beide Bereichsenden werden korrekt skaliert und in DE-Format angezeigt.
- Ausgabe der Mengen konsistent in deutscher Locale (Komma), ganzzahlnahe Werte werden gerundet, ansonsten max. 2 Dezimalstellen.
- Freitext wie â€žSaft einer halben Zitroneâ€œ wird als 0,5 StÃ¼ck Zitrone (Note â€žSaftâ€œ) modelliert und korrekt skaliert.
### Changed
- Renderer und Frontend-Skalierung auf Bereichsmodell (low/high) erweitert; Datenattribute `data-qty-low`/`data-qty-high` fÃ¼r Bereiche.
### Files
- includes/parser.php
- includes/renderer.php
- assets/js/recipe-frontend.js
- skipintro-recipe-crawler.php
- PROJECT_PLAN.md

## [0.5.6] - 2025-09-01
### Fixed
- Zutaten-Duplikate entfernt: Mergen mit PrioritÃ¤t JSON-LD > Microdata > DOM; DOM wird nur fÃ¼r fehlende Felder als Fallback herangezogen.
- Nach dem Mergen werden Zutaten zeilenbasiert dedupliziert (insensitiv gegenÃ¼ber GroÃŸ-/Kleinschreibung, Diakritika und Whitespace), sodass doppelte EintrÃ¤ge wie â€ž1 Lorbeerblattâ€œ nicht mehrfach erscheinen.
### Files
- includes/parser.php
- skipintro-recipe-crawler.php

## [0.5.5] - 2025-08-29
### Changed
- Ausgabe der Einheiten auf Deutsch umgestellt (EL/TL, g, kg, ml, l, â€¦) mit Aliasâ€‘Mapping; Parser kann intern weiterhin kanonische Kurzformen verwenden.
- Zahlenformat fÃ¼r Mengen konsistent mit Dezimalâ€‘Komma; BrÃ¼che (Â½, 1/2, 1 1/2) werden korrekt erkannt und skaliert.
### Files
- includes/renderer.php
- DEV_NOTES.md
- tools/UNIT_EXAMPLES.md
- skipintro-recipe-crawler.php

## [0.5.4] - 2025-08-29
### Fixed
- Refresh-Button jetzt klar sichtbar: zusÃ¤tzlich im â€žVerÃ¶ffentlichenâ€œ-Block (`post_submitbox_misc_actions`) und weiterhin in eigener Metaâ€‘Box.
- Button nur fÃ¼r berechtigte Nutzer (`edit_post`) sichtbar; Nonce wird per URL-Nonce geprÃ¼ft.
- Klick fÃ¼hrt zuverlÃ¤ssig die Refresh-Logik via `admin-post.php` aus (GET/POST), inkl. Erfolg-/Fehler-Hinweisen.
### Files
- includes/refresh.php
- skipintro-recipe-crawler.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.3] - 2025-08-29
### Added
- Backend-Button â€žRezept aktualisierenâ€œ in der Beitragsbearbeitung (nur mit Berechtigung), inkl. Nonce-PrÃ¼fung.
- Refresh-Logik mit Parser v2: Quelle bestimmen (schema.url â†’ canonical/og:url â†’ gespeicherte Quelle), Ergebnis validieren (Flags/Confidence) und nur parser-verwaltete Felder selektiv Ã¼berschreiben.
- Lock-Liste per Post-Meta (`_sitc_lock`) zum Schutz manueller Ã„nderungen (z. B. `title`, `description`, `image`, `ingredients`, `instructions`, `times`, `yield`, `nutrition`, `rating`).
- Metadaten setzen: `_sitc_last_refreshed` (UTC), `_sitc_parser_version` (aktuelle Plugin/Parser-Version), `_sitc_refresh_log` (gekappte JSON-Historie).
- Dry-Run in Dev-Mode (Option): zeigt Diff, schreibt nichts.
- Einfache Dev-Mode-Option (Settings), die die Sichtbarkeit des Dry-Run steuert (statt `WP_DEBUG`).
- Interne Hooks: `sitc_before_refresh` und `sitc_after_refresh` fÃ¼r Add-ons.
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
- Neue Parser-Pipeline `parseRecipe()` mit PrioritÃ¤t JSON-LD > Microdata > DOM, inkl. Normalisierung (Zeiten, Bilder, Autoren), Flags und Confidence-Score.
- Wrapper `sitc_parse_recipe_from_url_v2()` mit Legacy-Mapping und Meta (schema_recipe, sources, confidence, flags).
### Changed
- Frontend-Importer nutzt den neuen Parser (v2) und persistiert zusÃ¤tzliche Metadaten.
- Admin-Importer auf v2 umgestellt; kanonische URL als Quelle bevorzugt.
- Parser-Hilfsfunktionen fÃ¼r Zeit-/Zutaten-Parsing, HowTo-Flattening und Noise-Filter ergÃ¤nzt.
### Files
- includes/parser.php
- includes/frontend-import.php
- includes/admin-page.php

## [0.5.1] - 2025-08-28
### Added
- Projekt-Tagebuch angelegt (Roadmap F0â€“F5).
- CHANGELOG.md erstellt, um kÃ¼nftige Ã„nderungen zentral zu dokumentieren.

### Fixed
- Noch nichts.

### Changed
- Noch nichts.

---
## Format
- [Version] â€“ Datum
- BlÃ¶cke: Added, Changed, Fixed, Removed.
## [0.5.14] - 2025-09-04
### Fixed
- Mojibake in Renderer-Strings bereinigt (UTF-8/NFC)
### Files
- includes/renderer.php
- CHANGELOG.md
- PROJECT_PLAN.md
- temp_renderer_dump.txt (removed)
