<!-- SITC_CHANGELOG_TOP: new entries must be inserted BELOW this line -->

## [+ 0.0.1] [WIP]
### Fixed
- Frontend-Whitespace oberhalb des Inhalts eliminiert – Admin-Code strikt auf `is_admin()` begrenzt, Frontend-Output-Guard eingeführt, BOM/Whitespace-Risiken beseitigt; optionaler CSS-Fallback.
### Files
- skipintro-recipe-crawler.php
- includes/settings.php
- includes/assets.php
- includes/refresh.php
- tools/dev/scan_bom.php (Dev)

## [+ 0.0.1] [WIP]
### Fixed
- Parser-Lab: diag_box() akzeptiert nun Strings und Arrays; verhindert TypeError.
### Files
- tools/parser_lab/run.php
• Fixed: Modular-Pipeline lädt verlässlich (includes + MUX an allen Call-Sites); Parser-Lab Smoke-Test bei Engine=mod.
• Files: includes/parser.php; includes/settings.php; tools/parser_lab/run.php
### Added
- Modular-Parser – Qty/Unit/Notes-Erkennung (Brüche, gemischte Zahlen, Ranges, Bund/Zehe/EL/TL, TK/Klammern/Suffix), verbessert Dezimal-Komma im Parsing.
- Parser-Lab dev-dump (qty/unit/item/note) sichtbar nur bei Engine=mod.
### Changed
- Parser-Lab nutzt Engine=mod für Tests; Prod-Flag bleibt OFF.
### Files
- includes/ingredients/tokenize.php
- includes/ingredients/qty.php
- includes/ingredients/unit.php
- includes/ingredients/note.php
- includes/ingredients/parse_line.php
- includes/parser.php (MUX only)
- tools/parser_lab/run.php (Badge/Toggle unverändert, dev-dump)

## [+ 0.0.1] [WIP]
### Added
- Web-Auto-Eval (Legacy vs. Modular) unter `tools/parser_lab/auto_eval_web.php`.
### Changed
- Parser-Lab `run.php` mit Engine-Toggle (`?engine=legacy|mod|auto`), Badge und Vergleichslinks.
### Note
- `SITC_ING_V2` bleibt OFF, Modular nur für Tests.
### Files
- includes/settings.php
- includes/parser.php
- tools/parser_lab/run.php
- tools/parser_lab/auto_eval_web.php
- tools/parser_lab/out/.htaccess

## [0.5.55] - 2025-09-12 16:52
### Changed/Fixed
- Parser harmonisiert Zutaten-Ausgabe auf `{raw,qty,unit,item,note}`; robuste Mengen-Erkennung (Unicode-/ASCII-Brüche, gemischte Zahlen, Bereiche, Dezimalkomma); Legacy `{qty,unit,name}` wird sauber adaptiert.
### Files
- includes/parser.php
- includes/parser_helpers.php
- includes/ingredients/parse_line.php

## [0.5.54] - 2025-09-12 16:35
### Fixed
- strict_types-Deklaration korrigiert – in `includes/ingredients/parse_line.php` an den Dateianfang verschoben (ohne Kommentare/Whitespace davor); Projektweit geprüft (`includes/`, `tools/`): keine weiteren Verstöße gefunden; Dateien als UTF-8 ohne BOM gespeichert.
### Files
- includes/ingredients/parse_line.php

## [0.5.53] – 2025-09-12 15:20
### Hotfix
- Zutaten-Schema {raw, qty, unit, item, note} konsolidiert; Vor-Normalisierung (ca., Brüche, Dashes, Dezimal) vor Tokenizing; Range/Mixed/Fraction stabil.
### Files
- includes/ingredients/parse_line.php
- includes/ingredients/tokenize.php
- includes/ingredients/qty.php
- includes/ingredients/note.php
- includes/parser_helpers.php
- includes/parser.php

## [0.5.52] – 2025-09-12 15:05
### Refactor
- Modul-Skelett für Ingredient-Parser eingeführt (tokenize/qty/unit/note) und `sitc_parse_ingredient_line(...)` darauf verkabelt. Keine Verhaltensänderung.
### Files
- includes/ingredients/parse_line.php
- includes/ingredients/tokenize.php
- includes/ingredients/qty.php
- includes/ingredients/unit.php
- includes/ingredients/note.php
- includes/parser.php

## [0.5.51] – 2025-09-12 14:45
### Fixed
- Parser: Brüche (½/1/2/1 1/2), Ranges (2–3/2-3), Unit-aus-Item (…zehen→clove) und Note-Suffixe ("-Stück", "TK …, …") robust im Adapter `sitc_parse_ingredient_line`; Parser-Lab-Fälle grün.
### Files
- includes/ingredients/parse_line.php
- includes/parser_helpers.php
- includes/parser.php

## [0.5.50] – 2025-09-12 14:30
### Chore
- Altdateien ins Archiv verschoben; Parser-/Renderer-Helper klar getrennt (keine Funktionsänderungen), Präfixe zur weiteren Konsolidierung vorbereitet.
### Files
- tools/archive/** (neu)
- tools/parser_lab/run.php
- includes/parser_helpers.php
- includes/renderer/helpers.php

### Refactor
- Ingredients-Adapter eingeführt: `sitc_parse_ingredient_line($line, 'de')`; Parser ruft den Adapter statt Inline-Heuristiken. Verhalten unverändert.
### Files
- includes/ingredients/parse_line.php (neu)
- includes/parser.php

## [0.5.49] – 2025-09-12 14:20
### Fixed
- Mengenparser: Unicode-/ASCII-Brüche und gemischte Zahlen („1½“/„1 1/2“) sowie Leading-Decimals („.50/0,50“) robust normalisiert; Ranges unverändert korrekt.
- Units: Alias ergänzt/robust genutzt (bund→bunch, stück→piece); Heuristiken für „Knoblauchzehen“ (→ clove) und „Ingwer-Stück“ (→ note „Stück“).
- Item-Bereinigung: konsumierten Mengen-/Einheiten-Präfix zuverlässig entfernt, kein doppelter Präfix im Item.
### Files
- includes/parser_helpers.php
- includes/parser.php

## [0.5.48] – 2025-09-12 13:55
### Added
- Parser-Lab: Auto-Evaluation (`?eval=1`) für Kernfälle (Fractions, Mixed Numbers, Ranges, Leading Decimals, Notes, Units) auf 4 Referenz-Fixtures; kompakte PASS/FAIL-Zusammenfassung inkl. Deep-Dive Details.
### Files
- tools/parser_lab/run.php

## [0.5.47] – 2025-09-12 13:40
### Fixed
- Parser (DOM): Zutaten aus <li> itemisiert; kein zusammengefügter Container-Text mehr.
- Parser (Pre-Normalize): Unicode/ASCII-Brüche, Mixed Numbers, Ranges und führende Dezimalpunkte robust (Reihenfolge vor Qty-Parse eingehalten).
### Changed
- Parser (Post-Parse): konsumierten Mengen-Präfix zuverlässig aus Resttext entfernt (keine doppelte Menge im item).
### Files
- includes/parser.php
- includes/parser_helpers.php

## [0.5.46] – 2025-09-12 13:05
### Fixed
- Parser: Nach Qty/Range robuste Extraktion von Unit/Item/Note (keine Multi-Splits; nichts verschluckt).
- Parser: D-FIX-Flow beibehalten (Ranges „a–b/ bis“, ASCII-/Unicode-Brüche, „.50/0,50“).
### Files
- includes/parser.php
- includes/parser_helpers.php

## [0.5.45] – 2025-09-12 12:10
### Fixed
- Parser: Range-Handling („a–b“/„bis“) vor Segmentierung; keine Reduktion auf Einzelwerte.
- Parser: ASCII-/Unicode-Brüche und gemischte Zahlen („1/2“, „1 1/2“, „½“) robust; Dezimal-Komma („0,50“) und führende Dezimal („.50“) normalisiert.
- Parser: Multi-Qty-Splitting pro Zeile deaktiviert; eine Zutatenzeile ergibt genau einen Struct-Eintrag.
### Files
- includes/parser.php
- includes/parser_helpers.php

## [0.5.44] – 2025-09-12 10:30
### Fixed
- Parser: ASCII-Brüche (1/2, 3/4, 1 1/2) sicher als Mengen erkannt; keine Verwechslung mit Bereichen.
- Parser: Bereiche mit Gedankenstrich bzw. „bis“ als {low,high} erhalten; keine Reduktion.
- Parser: Dezimal-Komma im Zahlkontext und führende Null („.50“ → „0.50“) normalisiert; Slash bleibt für Brüche erhalten.
- Lab: Qty-Ranges formatiert als „low–high“; „display (einfach)“ ergänzt optional „, note“.
### Files
- includes/parser_helpers.php
- tools/parser_lab/run.php

## [0.5.43] – 2025-09-12 09:04
### Fixed
- Brüche & gemischte Zahlen (¼, ½, ¾, 1/2, 1 1/2) robust geparst (keine Fehldeutung „1/2“→„2“).
- Bereiche (2–3) als Range erhalten (Anzeige in Parser-Lab „low–high“).
- Dezimal-Komma & führende Null normalisiert („0,50“→0.5, „.50“→„0.50“).
### Files
- includes/parser_helpers.php
- tools/parser_lab/run.php

## [0.5.42] – 2025-09-11  09:04
### Fixed
- Parser: Zähl-Einheit „Stück“ korrekt erkannt (0,5 Stück Zitrone → qty 0.5, unit piece, item Zitrone, note Saft).
- Parser: Hyphen-Notiz bei Gewichten („Ingwer-Stück“) → item=Ingwer, note=Stück (unit bleibt g).
- Parser: Notizen aus Klammern/Komma beibehalten (z. B. „, gehackt“).
- Parser: „ca.“/Stopwörter vor/nach Mengentoken entfernt; Dezimal-Komma in numerischen Tokens berücksichtigt.
- Parser: Geister-Segmente mit reiner Zahl (z. B. „1“) verworfen.
### Files
- includes/parser.php
- includes/parser_helpers.php

## [0.5.41] – 2025-09-11 17:05 Europe/Berlin
### Fixed
- Helper-Kollision behoben: `sitc_unicode_fraction_map()` nur noch in `includes/parser_helpers.php`; Renderer bindet Parser-Helper per `require_once`.
- Globale Helper mit `function_exists`-Guards abgesichert; doppelte Definitionen entfernt.
### Files
- includes/parser_helpers.php
- includes/renderer/helpers.php

## [0.5.40] – 2025-09-11 16:40 Europe/Berlin
### Fixed
- Parser: PCRE2-kompatible Tokenizer/Splitter; Unicode-Brüche & gemischte Zahlen stabil; Dezimal-Komma korrekt (0,50→0.5).
### Changed
- Einheiten-Aliase im Parser erweitert (tl/el/zehe/bund/… → kanonisch).
### Files
- includes/parser.php
- includes/parser_helpers.php (neu)

## [0.5.39] – 2025-09-11 13:58
### Fixed
- Parser: Regex-Ausdrücke PCRE2-kompatibel abgesichert (preg_split @~835/980/1187) und mit Guards versehen.
- Guards: Regex-Fehler brechen den Parser nicht mehr ab; Diagnose im Parser-Lab via E_USER_WARNING sichtbar.
### Files
- includes/parser.php

## [0.5.38] – 2025-09-11 13:58
### Changed
- Parser-Lab: Vollversion wiederhergestellt (Fixtures-Index in run.php, Diagnostics, strukturierte Tabelle, Metriken).
### Added
- Parser-Lab: run_diag.php als Mini-Diagnose (Upload/OpCache/Hash/mtime).
### Files
- tools/parser_lab/run.php
- tools/parser_lab/run_diag.php

## [0.5.37] – 2025-09-11 11:06
### Fixed
- Parser-Lab: „Cannot redeclare status_badge()“ behoben; zentrale Implementierung in helpers/render_table.php, run.php nutzt sie nur noch.
### Files
- tools/parser_lab/run.php
- tools/parser_lab/helpers/render_table.php

## [0.5.36] – 2025-09-11 09:52
### Changed
- Parser-Lab: Fixture-Index/Liste in `run.php` integriert (autodiscovery von fixtures/).
### Removed
- Parser-Lab: `index.php` entfernt.
### Files
- tools/parser_lab/run.php
- tools/parser_lab/index.php

## [0.5.35] – 2025-09-11 09:52
### Removed
- Parser-Lab: index.php entfernt (Navigation ist in run.php integriert).
### Files
- tools/parser_lab/index.php

## [0.5.34] – 2025-09-11 09:36
### Fixed
- Parser-Lab: Vollständige Fehlerdiagnostik (Exceptions, PHP-Fehler, shutdown last error) + Fixture-Links direkt in run.php; kein „stiller“ Fallback mehr.
### Files
- tools/parser_lab/run.php
- tools/parser_lab/lib/harness.php

## [0.5.34] – 2025-09-11 09:36
### Changed
- CHANGELOG-Format vereinheitlicht (Datum mit Uhrzeit, Reihenfolge der Unterüberschriften, einheitliches Files-Subheading) und neuester Eintrag ergänzt.
### Files
- CHANGELOG.md

## [0.5.33] – 2025-09-11
### Changed
- Parser-Lab: Diagnose erweitert (HTML-Länge, JSON-LD-Count, Parse-Dauer, Flags/Confidence, JSON-LD-Kurzsicht); nie leere Tabelle.
### Added
- Parser-Lab: Fixture-Index & Schnellzugriff (Butter Chicken, Ente Chop Suey, Hähnchen Biryani).
### Files
- tools/parser_lab/run.php
- tools/parser_lab/lib/harness.php
- tools/parser_lab/index.php

## [0.5.32] - 2025-09-11
### Fixed
- Parser-Lab: vollständige Fehlerdiagnostik (Exceptions/last error) statt stummem Fallback.
### Changed
- Parser-Lab: .jsonld-Fixtures werden als HTML mit JSON-LD Script eingebettet; bei fehlenden strukturierten Feldern wird eine sinnvolle Fallback-Struktur erzeugt, damit die Tabelle nie leer ist.
### Files
- tools/parser_lab/lib/harness.php
- tools/parser_lab/run.php

## [0.5.31] – 2025-09-10
### Fixed
- Parser-Lab: Whitescreen endgültig entfernt (Error/Shutdown-Guards); Diagnosen und Tabellen-Ausgabe erscheinen immer.
### Added
- Parser-Lab wiederhergestellt: Fixtures (butter_chicken, ente_chop_suey, biryani), Harness & Tabellen-Ausgabe; Debug-Modus ohne Whitescreen.
### Files
- tools/parser_lab/run.php
- tools/parser_lab/lib/harness.php (neu)

## [0.5.30] - 2025-09-10
### Changed
- Parser: `recipeIngredient` liefert strukturierte Einträge (raw, qty[float|{low,high}], unit, item, note); Stopwörter/Unicode‑Brüche/Bereiche normalisiert; Mehrfach-Mengen pro Zeile gesplittet.
### Files
- includes/parser.php

## [0.5.29] - 2025-09-10
### Added
- Parser-Fixture-Lab (tools/parser_lab) zum lokalen Testen von HTML/JSON-LD gegen den vorhandenen Parser.
### Files
- tools/parser_lab/**

## [0.5.28] - 2025-09-10
### Added
- Test-Lab (tools/qty_lab) für Mengen-/Bruch-Parsing erstellt (Standalone, Diagnosezwecke)
### Files
- tools/qty_lab/lib_qty.php
- tools/qty_lab/test.php

## [0.5.27] - 2025-09-10
### Fixed
- Bruchmengen (½, 1½) inkl. gemischter Zahlen robust erkannt & skaliert.
- Basiseinheiten (ml/g/l) skalierbar; ca./Stopwörter entfernt.
- Dedupe & Großschreibung der Zutaten-Ausgabe (keine Doppelungen; erstes Wort groß).
- Dev-Diagnostik zeigt alle Zutatenzeilen.
### Files
- includes/renderer/helpers_extra.php (neu)
- includes/renderer.php
- includes/renderer/ingredients.php
- includes/renderer/dev_badge.php

## [0.5.26] - 2025-09-09
### Changed
- Renderer-Anbindung nach Modularisierung repariert; BC-Shims ergänzt; keine Funktionsänderung.
### Files
- includes/renderer.php

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

## [0.5.17] - 2025-09-04
### Fixed
- Bruchmengen (½, 1½, ¼) inkl. gemischter Zahlen robust geparst & skaliert; Basis-Einheiten (ml/g/l) skalierbar; DE-Format.
### Files
- includes/parser.php
- includes/renderer/ingredients.php
- includes/renderer/helpers.php

## [0.5.16] - 2025-09-04
### Fixed
- Zentrale Render-Pipeline erzwungen (sanitize → pre-normalize → parse → format (DE)) in allen Renderpfaden; keine Rohstrings mehr im Frontend.
- Dev-Diagnostik-Badge (nur Dev-Mode) zeigt Pipeline für die ersten 5 Zutatenzeilen (raw/sanitized/prenorm/parsed/display + source).
- Refresh-Flow: Dev-Option „Force refresh“ ignoriert Locks, geparste Felder werden überschrieben; Feldquellen gespeichert und genutzt.
- Mojibake-Quelle entschlüsselt: Sanitize-Reihenfolge auf Entities → NFC → Mojibake → Whitespace umgestellt; „Stück“ normalisiert vor dem Parsen.
- DE-Einheiten und ca./Stopwörter konsolidiert; Unicode-Brüche am Zeilenanfang sicher erkannt und skaliert.
### Files
- includes/renderer.php
- includes/parser.php (sanitize helpers)
- includes/refresh.php 
- skipintro-recipe-crawler.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.15] - 2025-09-04
### Fixed
- Globale Pipeline für Zutaten-Normalisierung (DE) durchgängig erzwungen: Sanitize → Pre-Normalize → Parse → Format (DE) → Render; keine Rohstrings mehr in der Anzeige.
- Unicode-Brüche am Zeilenanfang (½, ¼, ¾, ⅛, ⅓, ⅔ sowie 1/2, 1 1/2, 1 /2) robust erkannt und in skalierbare Dezimalzahlen überführt.
- Text-Sanitisierung mit HTML-Entities, UTF-8/NFC und Mojibake-Fixes.
- Stopwörter „ca.“ u. a. (ca, circa, etwa, ungefähr, about, approx., (ca.), (ca.

## [0.5.14] - 2025-09-04
### Fixed
- Renderer: Syntaxfehler um Zeile ~365 behoben (String-Verkettung / Artefakt entfernt).
- Vor-Normalisierung & Mengenparser (DE): Stopwörter (ca.), Brüche/Dezimal, Bereiche; Ausgabe immer Dezimal mit Komma.
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
- Mengen: Vor-Normalisierung (ca./circa/etwa/ungef./about/approx.), Unicode/ASCII-Brüche, Dezimal (Komma/Punkt), Bereich a-b skaliert beide Enden; Anzeige immer DE-Format (Komma), ganzzahlnahe Werte werden gerundet, ansonsten max. 2 Dezimalstellen.
### Files
- includes/renderer.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.12] - 2025-09-04
### Fixed
- Quarantäne Temp-Dateien; Encoding UTF-8 normalisiert
### Files
- includes/renderer.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.11] - 2025-09-04
### Fixed
- Stabilitätshotfix: Safe Mode + Fallback-Renderer. Parser/Renderer mit Guardrails (try/catch, Flags) töten das Frontend nicht mehr. Safe Mode deaktiviert Gruppierung und Skalierung; einfache Zutatenliste als vorläufige Darstellung.
### Files
- includes/settings.php
- includes/admin-page.php
- includes/renderer.php
- skipintro-recipe-crawler.php

## [0.5.10] - 2025-09-03
### Fixed
- Zutaten-Gruppen: Verhindert Fehlklassifikation von echten Zutatenzeilen als Zwischenüberschrift. Heuristik prüft jetzt zunächst auf Mengen/Einheiten und wertet solche Zeilen nicht als Header.
### Files
- includes/renderer.php

## [0.5.9] - 2025-09-02
### Fixed
- Vor-Normalisierung für Mengen: Entfernt generische Zusätze wie "ca.", "circa", "etwa", "ungef.", "about", "approx.", "approximately" am Zeilenanfang bzw. direkt vor der Zahl sowie unmittelbar nach der Menge (z. B. "60 (ca.) ml").
- Vereinheitlicht Whitespaces um "/" und Bereichsseparatoren (en/em dash, "-") vor dem Mengen-Parsing.
### Files
- includes/parser.php
- skipintro-recipe-crawler.php
- CHANGELOG.md
- PROJECT_PLAN.md

## [0.5.8] - 2025-09-02
### Fixed
- Generische De-Dupe-Logik für Zutaten nach dem Merge (JSON-LD > Microdata > DOM). Normalisiert Menge, Einheit (kanonisch) und Zutatentext, doppelte Einträge werden zusammengeführt, Notizen vereinigt.
### Files
- includes/parser.php
- skipintro-recipe-crawler.php

## [0.5.7] - 2025-09-02
### Fixed
- Mengen-Parser robust gegen Unicode- und ASCII-Brüche (½, ¼, ¾, ⅛, ⅓, ⅔ sowie 1/2, 1 1/2, 1 /2), Dezimalzahlen mit Komma/Punkt und Bereiche (2–3). Beide Bereichsenden werden korrekt skaliert und in DE-Format angezeigt.
- Ausgabe der Mengen konsistent in deutscher Locale (Komma), ganzzahlnahe Werte werden gerundet, ansonsten max. 2 Dezimalstellen.
- Freitext wie "Saft einer halben Zitrone" wird als 0,5 Stück Zitrone (Note "Saft") modelliert und korrekt skaliert.
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
### Files
- includes/parser.php
- skipintro-recipe-crawler.php

## [0.5.5] - 2025-08-29
### Changed
- Ausgabe der Einheiten auf Deutsch umgestellt (EL/TL, g, kg, ml, l, …) mit Alias-Mapping; Parser kann intern weiterhin kanonische Kurzformen verwenden.
- Zahlenformat für Mengen konsistent mit Dezimal-Komma; Brüche (½, ¼, 1 1/2) werden korrekt erkannt und skaliert.
### Files
- includes/renderer.php
- DEV_NOTES.md
- tools/UNIT_EXAMPLES.md
- skipintro-recipe-crawler.php

## [0.5.4] - 2025-08-29
### Fixed
- Refresh-Button jetzt klar sichtbar: zusätzlich im öffentlichen Block (`post_submitbox_misc_actions`) und weiterhin in eigener Meta-Box.
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
- Refresh-Logik mit Parser v2: Quelle bestimmen (schema.url > canonical/og:url > gespeicherte Quelle), Ergebnis validieren (Flags/Confidence) und nur parser-verwaltete Felder selektiv überschreiben.
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
