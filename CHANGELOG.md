## [0.5.14] - 2025-09-04
### Fixed
- Renderer: Syntaxfehler um Zeile ~365 behoben (String-Verkettung / Artefakt entfernt).
- Vor-Normalisierung & Mengenparser (DE): Stopwörter („ca.“), Brüche/Dezimal, Bereiche; Ausgabe immer Dezimal mit Komma.
- EOL/Zeilenenden normalisiert (LF), `.gitattributes` erweitert; Git-Warnungen beseitigt.
- Mojibake in Renderer-Strings (UTF-8/NFC) bereinigt.
### Changed
- (optional) Gruppen-Überschriften vorbereitet/angepasst, falls bereits enthalten.
### Files
- skipintro-recipe-crawler.php
- includes/renderer.php
- .gitattributes
- CHANGELOG.md
- PROJECT_PLAN.md
- includes/admin-page.php
- includes/frontend-import.php
- includes/refresh.php
- includes/settings.php
- notes.html
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
- Zutaten-Gruppen: Verhindert Fehlklassifikation von echten Zutatenzeilen als ZwischenÃƒÂ¼berschrift (z. B. Ã¢â‚¬Å¾Ã‚Â½ TK Enten Ã¢â‚¬Â¦Ã¢â‚¬Å“ wurde durch das Wort Ã¢â‚¬Å¾EnteÃ¢â‚¬Å“ irrtÃƒÂ¼mlich als Header erkannt). Heuristik prÃƒÂ¼ft jetzt zunÃƒÂ¤chst auf Mengen/Einheiten und wertet solche Zeilen nicht als Header.
### Files
- includes/renderer.php

## [0.5.9] - 2025-09-02
### Fixed
- Vor-Normalisierung fÃƒÂ¼r Mengen: Entfernt generische ZusÃƒÂ¤tze wie "ca.", "circa", "etwa", "ungef.", "about", "approx.", "approximately" am Zeilenanfang bzw. direkt vor der Zahl sowie unmittelbar nach der Menge (z. B. "60 (ca.) ml").
- Vereinheitlicht Whitespaces um "/" und Bereichsseparatoren (en/em dash, "-") vor dem Mengen-Parsing. Dadurch werden Zeilen wie "ca. 60 ml Ãƒâ€“l (ca.)" korrekt als skalierbare Basis erkannt.
### Files
- includes/parser.php
- skipintro-recipe-crawler.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.8] - 2025-09-02
### Fixed
- Generische De-Dupe-Logik fÃƒÂ¼r Zutaten nach dem Merge (JSON-LD > Microdata > DOM). Normalisiert Menge (inkl. Bereiche), Einheit (kanonisch) und Zutatentext (Whitespace/Case/Diakritika/StopwÃƒÂ¶rter/Lemma) zu einem robusten SchlÃƒÂ¼ssel; doppelte EintrÃƒÂ¤ge werden zusammengefÃƒÂ¼hrt, Notizen vereinigt.
### Files
- includes/parser.php
- skipintro-recipe-crawler.php

## [0.5.7] - 2025-09-02
### Fixed
- Mengen-Parser robust gegen Unicode- und ASCII-BrÃƒÂ¼che (Ã‚Â½, Ã¢â€¦â€œ, Ã‚Â¼, Ã¢â€¦â€, Ã‚Â¾ sowie 1/2, 1 1/2, 1 /2), Dezimalzahlen mit Komma/Punkt und Bereiche (2Ã¢â‚¬â€œ3 / 2-3). Beide Bereichsenden werden korrekt skaliert und in DE-Format angezeigt.
- Ausgabe der Mengen konsistent in deutscher Locale (Komma), ganzzahlnahe Werte werden gerundet, ansonsten max. 2 Dezimalstellen.
- Freitext wie Ã¢â‚¬Å¾Saft einer halben ZitroneÃ¢â‚¬Å“ wird als 0,5 StÃƒÂ¼ck Zitrone (Note Ã¢â‚¬Å¾SaftÃ¢â‚¬Å“) modelliert und korrekt skaliert.
### Changed
- Renderer und Frontend-Skalierung auf Bereichsmodell (low/high) erweitert; Datenattribute `data-qty-low`/`data-qty-high` fÃƒÂ¼r Bereiche.
### Files
- includes/parser.php
- includes/renderer.php
- assets/js/recipe-frontend.js
- skipintro-recipe-crawler.php
- PROJECT_PLAN.md

## [0.5.6] - 2025-09-01
### Fixed
- Zutaten-Duplikate entfernt: Mergen mit PrioritÃƒÂ¤t JSON-LD > Microdata > DOM; DOM wird nur fÃƒÂ¼r fehlende Felder als Fallback herangezogen.
- Nach dem Mergen werden Zutaten zeilenbasiert dedupliziert (insensitiv gegenÃƒÂ¼ber GroÃƒÅ¸-/Kleinschreibung, Diakritika und Whitespace), sodass doppelte EintrÃƒÂ¤ge wie Ã¢â‚¬Å¾1 LorbeerblattÃ¢â‚¬Å“ nicht mehrfach erscheinen.
### Files
- includes/parser.php
- skipintro-recipe-crawler.php

## [0.5.5] - 2025-08-29
### Changed
- Ausgabe der Einheiten auf Deutsch umgestellt (EL/TL, g, kg, ml, l, Ã¢â‚¬Â¦) mit AliasÃ¢â‚¬â€˜Mapping; Parser kann intern weiterhin kanonische Kurzformen verwenden.
- Zahlenformat fÃƒÂ¼r Mengen konsistent mit DezimalÃ¢â‚¬â€˜Komma; BrÃƒÂ¼che (Ã‚Â½, 1/2, 1 1/2) werden korrekt erkannt und skaliert.
### Files
- includes/renderer.php
- DEV_NOTES.md
- tools/UNIT_EXAMPLES.md
- skipintro-recipe-crawler.php

## [0.5.4] - 2025-08-29
### Fixed
- Refresh-Button jetzt klar sichtbar: zusÃƒÂ¤tzlich im Ã¢â‚¬Å¾VerÃƒÂ¶ffentlichenÃ¢â‚¬Å“-Block (`post_submitbox_misc_actions`) und weiterhin in eigener MetaÃ¢â‚¬â€˜Box.
- Button nur fÃƒÂ¼r berechtigte Nutzer (`edit_post`) sichtbar; Nonce wird per URL-Nonce geprÃƒÂ¼ft.
- Klick fÃƒÂ¼hrt zuverlÃƒÂ¤ssig die Refresh-Logik via `admin-post.php` aus (GET/POST), inkl. Erfolg-/Fehler-Hinweisen.
### Files
- includes/refresh.php
- skipintro-recipe-crawler.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.3] - 2025-08-29
### Added
- Backend-Button Ã¢â‚¬Å¾Rezept aktualisierenÃ¢â‚¬Å“ in der Beitragsbearbeitung (nur mit Berechtigung), inkl. Nonce-PrÃƒÂ¼fung.
- Refresh-Logik mit Parser v2: Quelle bestimmen (schema.url Ã¢â€ â€™ canonical/og:url Ã¢â€ â€™ gespeicherte Quelle), Ergebnis validieren (Flags/Confidence) und nur parser-verwaltete Felder selektiv ÃƒÂ¼berschreiben.
- Lock-Liste per Post-Meta (`_sitc_lock`) zum Schutz manueller Ãƒâ€žnderungen (z. B. `title`, `description`, `image`, `ingredients`, `instructions`, `times`, `yield`, `nutrition`, `rating`).
- Metadaten setzen: `_sitc_last_refreshed` (UTC), `_sitc_parser_version` (aktuelle Plugin/Parser-Version), `_sitc_refresh_log` (gekappte JSON-Historie).
- Dry-Run in Dev-Mode (Option): zeigt Diff, schreibt nichts.
- Einfache Dev-Mode-Option (Settings), die die Sichtbarkeit des Dry-Run steuert (statt `WP_DEBUG`).
- Interne Hooks: `sitc_before_refresh` und `sitc_after_refresh` fÃƒÂ¼r Add-ons.
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
- Neue Parser-Pipeline `parseRecipe()` mit PrioritÃƒÂ¤t JSON-LD > Microdata > DOM, inkl. Normalisierung (Zeiten, Bilder, Autoren), Flags und Confidence-Score.
- Wrapper `sitc_parse_recipe_from_url_v2()` mit Legacy-Mapping und Meta (schema_recipe, sources, confidence, flags).
### Changed
- Frontend-Importer nutzt den neuen Parser (v2) und persistiert zusÃƒÂ¤tzliche Metadaten.
- Admin-Importer auf v2 umgestellt; kanonische URL als Quelle bevorzugt.
- Parser-Hilfsfunktionen fÃƒÂ¼r Zeit-/Zutaten-Parsing, HowTo-Flattening und Noise-Filter ergÃƒÂ¤nzt.
### Files
- includes/parser.php
- includes/frontend-import.php
- includes/admin-page.php

## [0.5.1] - 2025-08-28
### Added
- Projekt-Tagebuch angelegt (Roadmap F0Ã¢â‚¬â€œF5).
- CHANGELOG.md erstellt, um kÃƒÂ¼nftige Ãƒâ€žnderungen zentral zu dokumentieren.

### Fixed
- Noch nichts.

### Changed
- Noch nichts.

---
## Format
- [Version] Ã¢â‚¬â€œ Datum
- BlÃƒÂ¶cke: Added, Changed, Fixed, Removed.
