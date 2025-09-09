## [0.5.25] - 2025-09-09
### Changed
- Renderer modularisiert (header/ingredients/instructions/helpers/dev_badge), keine Funktionsänderung.
### Files
- includes/renderer.php
- includes/renderer/*.php (neu)

## [0.5.24] - 2025-09-09
### Changed
- Rollback: Repository-Code auf Stand 0.5.16 zurückgesetzt (Baseline).
- CHANGELOG und PROJECT_PLAN synchronisiert; Doku-Einträge >0.5.16 (0.5.17–0.5.23) verworfen.
- Ausgangspunkt für nächste Schritte: Modularisierung des Renderers, danach Parser-Fixes neu.
### Files
- skipintro-recipe-crawler.php
- assets/, includes/, tools/
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.16] - 2025-09-04
### Fixed
- Zentrale Render-Pipeline erzwungen (sanitize → pre-normalize → parse → format (DE)) in allen Renderpfaden; keine Rohstrings mehr im Frontend.
- Dev-Diagnostik-Badge (nur Dev-Mode) zeigt Pipeline für die ersten 5 Zutatenzeilen (raw/sanitized/prenorm/parsed/display + source).
- Refresh-Flow: Dev-Option „Force refresh“ ignoriert Locks, geparste Felder werden überschrieben; Feldquellen gespeichert und genutzt.
- Mojibake-Quelle entschärft: Sanitize-Reihenfolge auf Entities → NFC → Mojibake → Whitespace umgestellt; „Stück“ normalisiert vor dem Parsen.
- DE-Einheiten und „ca.“/Stopwörter konsolidiert; Unicode-Brüche am Zeilenanfang sicher erkannt und skaliert.
### Files
- includes/renderer.php
- includes/parser.php (sanitize helpers)
- includes/refresh.php 
- skipintro-recipe-crawler.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.15] - 2025-09-04
### Fixed
- Globale Pipeline fÃ¼r Zutaten-Normalisierung (DE) durchgÃ¤ngig erzwungen: Sanitize â†’ Pre-Normalize â†’ Parse â†’ Format (DE) â†’ Render; keine Rohstrings mehr in der Anzeige.
- Unicode-BrÃ¼che am Zeilenanfang (Â½, â…“, Â¼, â…”, Â¾) robust erkannt und in skalierbare Dezimalzahlen Ã¼berfÃ¼hrt.
- Text-Sanitisierung mit HTML-Entities, UTF-8/NFC und Mojibake-Fixes (z. B. â€žStÃƒÂ¼ckâ€œ â†’ â€žStÃ¼ckâ€œ).
- StopwÃ¶rter â€žca.â€œ u. a. (ca, circa, etwa, ungefÃ¤hr, about, approx., (ca.), (ca)) entfernt.
- Einheitenschreibung in der Anzeige vereinheitlicht (ml, g, kg, l klein; TL/EL groÃŸ; StÃ¼ck korrekt mit Umlaut).
### Files
- includes/parser.php
- includes/renderer.php
- skipintro-recipe-crawler.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.14] - 2025-09-04
### Fixed
- Renderer: Syntaxfehler um Zeile ~365 behoben (String-Verkettung / Artefakt entfernt).
- Vor-Normalisierung & Mengenparser (DE): StopwÃ¶rter (â€žca.â€œ), BrÃ¼che/Dezimal, Bereiche; Ausgabe immer Dezimal mit Komma.
- EOL/Zeilenenden normalisiert (LF), `.gitattributes` erweitert; Git-Warnungen beseitigt.
- Mojibake in Renderer-Strings (UTF-8/NFC) bereinigt.
### Changed
- (optional) Gruppen-Ãœberschriften vorbereitet/angepasst, falls bereits enthalten.
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
- Zutaten-Gruppen: Verhindert Fehlklassifikation von echten Zutatenzeilen als ZwischenÃƒÆ’Ã‚Â¼berschrift (z. B. ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â½ TK Enten ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ wurde durch das Wort ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾EnteÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ irrtÃƒÆ’Ã‚Â¼mlich als Header erkannt). Heuristik prÃƒÆ’Ã‚Â¼ft jetzt zunÃƒÆ’Ã‚Â¤chst auf Mengen/Einheiten und wertet solche Zeilen nicht als Header.
### Files
- includes/renderer.php

## [0.5.9] - 2025-09-02
### Fixed
- Vor-Normalisierung fÃƒÆ’Ã‚Â¼r Mengen: Entfernt generische ZusÃƒÆ’Ã‚Â¤tze wie "ca.", "circa", "etwa", "ungef.", "about", "approx.", "approximately" am Zeilenanfang bzw. direkt vor der Zahl sowie unmittelbar nach der Menge (z. B. "60 (ca.) ml").
- Vereinheitlicht Whitespaces um "/" und Bereichsseparatoren (en/em dash, "-") vor dem Mengen-Parsing. Dadurch werden Zeilen wie "ca. 60 ml ÃƒÆ’Ã¢â‚¬â€œl (ca.)" korrekt als skalierbare Basis erkannt.
### Files
- includes/parser.php
- skipintro-recipe-crawler.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.8] - 2025-09-02
### Fixed
- Generische De-Dupe-Logik fÃƒÆ’Ã‚Â¼r Zutaten nach dem Merge (JSON-LD > Microdata > DOM). Normalisiert Menge (inkl. Bereiche), Einheit (kanonisch) und Zutatentext (Whitespace/Case/Diakritika/StopwÃƒÆ’Ã‚Â¶rter/Lemma) zu einem robusten SchlÃƒÆ’Ã‚Â¼ssel; doppelte EintrÃƒÆ’Ã‚Â¤ge werden zusammengefÃƒÆ’Ã‚Â¼hrt, Notizen vereinigt.
### Files
- includes/parser.php
- skipintro-recipe-crawler.php

## [0.5.7] - 2025-09-02
### Fixed
- Mengen-Parser robust gegen Unicode- und ASCII-BrÃƒÆ’Ã‚Â¼che (Ãƒâ€šÃ‚Â½, ÃƒÂ¢Ã¢â‚¬Â¦Ã¢â‚¬Å“, Ãƒâ€šÃ‚Â¼, ÃƒÂ¢Ã¢â‚¬Â¦Ã¢â‚¬Â, Ãƒâ€šÃ‚Â¾ sowie 1/2, 1 1/2, 1 /2), Dezimalzahlen mit Komma/Punkt und Bereiche (2ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Å“3 / 2-3). Beide Bereichsenden werden korrekt skaliert und in DE-Format angezeigt.
- Ausgabe der Mengen konsistent in deutscher Locale (Komma), ganzzahlnahe Werte werden gerundet, ansonsten max. 2 Dezimalstellen.
- Freitext wie ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Saft einer halben ZitroneÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ wird als 0,5 StÃƒÆ’Ã‚Â¼ck Zitrone (Note ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾SaftÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ) modelliert und korrekt skaliert.
### Changed
- Renderer und Frontend-Skalierung auf Bereichsmodell (low/high) erweitert; Datenattribute `data-qty-low`/`data-qty-high` fÃƒÆ’Ã‚Â¼r Bereiche.
### Files
- includes/parser.php
- includes/renderer.php
- assets/js/recipe-frontend.js
- skipintro-recipe-crawler.php
- PROJECT_PLAN.md

## [0.5.6] - 2025-09-01
### Fixed
- Zutaten-Duplikate entfernt: Mergen mit PrioritÃƒÆ’Ã‚Â¤t JSON-LD > Microdata > DOM; DOM wird nur fÃƒÆ’Ã‚Â¼r fehlende Felder als Fallback herangezogen.
- Nach dem Mergen werden Zutaten zeilenbasiert dedupliziert (insensitiv gegenÃƒÆ’Ã‚Â¼ber GroÃƒÆ’Ã…Â¸-/Kleinschreibung, Diakritika und Whitespace), sodass doppelte EintrÃƒÆ’Ã‚Â¤ge wie ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾1 LorbeerblattÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ nicht mehrfach erscheinen.
### Files
- includes/parser.php
- skipintro-recipe-crawler.php

## [0.5.5] - 2025-08-29
### Changed
- Ausgabe der Einheiten auf Deutsch umgestellt (EL/TL, g, kg, ml, l, ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦) mit AliasÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬ËœMapping; Parser kann intern weiterhin kanonische Kurzformen verwenden.
- Zahlenformat fÃƒÆ’Ã‚Â¼r Mengen konsistent mit DezimalÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬ËœKomma; BrÃƒÆ’Ã‚Â¼che (Ãƒâ€šÃ‚Â½, 1/2, 1 1/2) werden korrekt erkannt und skaliert.
### Files
- includes/renderer.php
- DEV_NOTES.md
- tools/UNIT_EXAMPLES.md
- skipintro-recipe-crawler.php

## [0.5.4] - 2025-08-29
### Fixed
- Refresh-Button jetzt klar sichtbar: zusÃƒÆ’Ã‚Â¤tzlich im ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾VerÃƒÆ’Ã‚Â¶ffentlichenÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ-Block (`post_submitbox_misc_actions`) und weiterhin in eigener MetaÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬ËœBox.
- Button nur fÃƒÆ’Ã‚Â¼r berechtigte Nutzer (`edit_post`) sichtbar; Nonce wird per URL-Nonce geprÃƒÆ’Ã‚Â¼ft.
- Klick fÃƒÆ’Ã‚Â¼hrt zuverlÃƒÆ’Ã‚Â¤ssig die Refresh-Logik via `admin-post.php` aus (GET/POST), inkl. Erfolg-/Fehler-Hinweisen.
### Files
- includes/refresh.php
- skipintro-recipe-crawler.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.3] - 2025-08-29
### Added
- Backend-Button ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Rezept aktualisierenÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ in der Beitragsbearbeitung (nur mit Berechtigung), inkl. Nonce-PrÃƒÆ’Ã‚Â¼fung.
- Refresh-Logik mit Parser v2: Quelle bestimmen (schema.url ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ canonical/og:url ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ gespeicherte Quelle), Ergebnis validieren (Flags/Confidence) und nur parser-verwaltete Felder selektiv ÃƒÆ’Ã‚Â¼berschreiben.
- Lock-Liste per Post-Meta (`_sitc_lock`) zum Schutz manueller ÃƒÆ’Ã¢â‚¬Å¾nderungen (z. B. `title`, `description`, `image`, `ingredients`, `instructions`, `times`, `yield`, `nutrition`, `rating`).
- Metadaten setzen: `_sitc_last_refreshed` (UTC), `_sitc_parser_version` (aktuelle Plugin/Parser-Version), `_sitc_refresh_log` (gekappte JSON-Historie).
- Dry-Run in Dev-Mode (Option): zeigt Diff, schreibt nichts.
- Einfache Dev-Mode-Option (Settings), die die Sichtbarkeit des Dry-Run steuert (statt `WP_DEBUG`).
- Interne Hooks: `sitc_before_refresh` und `sitc_after_refresh` fÃƒÆ’Ã‚Â¼r Add-ons.
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
- Neue Parser-Pipeline `parseRecipe()` mit PrioritÃƒÆ’Ã‚Â¤t JSON-LD > Microdata > DOM, inkl. Normalisierung (Zeiten, Bilder, Autoren), Flags und Confidence-Score.
- Wrapper `sitc_parse_recipe_from_url_v2()` mit Legacy-Mapping und Meta (schema_recipe, sources, confidence, flags).
### Changed
- Frontend-Importer nutzt den neuen Parser (v2) und persistiert zusÃƒÆ’Ã‚Â¤tzliche Metadaten.
- Admin-Importer auf v2 umgestellt; kanonische URL als Quelle bevorzugt.
- Parser-Hilfsfunktionen fÃƒÆ’Ã‚Â¼r Zeit-/Zutaten-Parsing, HowTo-Flattening und Noise-Filter ergÃƒÆ’Ã‚Â¤nzt.
### Files
- includes/parser.php
- includes/frontend-import.php
- includes/admin-page.php

## [0.5.1] - 2025-08-28
### Added
- Projekt-Tagebuch angelegt (Roadmap F0ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Å“F5).
- CHANGELOG.md erstellt, um kÃƒÆ’Ã‚Â¼nftige ÃƒÆ’Ã¢â‚¬Å¾nderungen zentral zu dokumentieren.

### Fixed
- Noch nichts.

### Changed
- Noch nichts.

---
## Format
- [Version] ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Å“ Datum
- BlÃƒÆ’Ã‚Â¶cke: Added, Changed, Fixed, Removed.


