# SkipIntro Recipe Crawler — Projektplan (Meta)

> Stand (Codebasis): 0.5.16 (Rollback-Baseline, 2025-09-09)  
> Doku-Einträge aus späteren Work-in-Progress-Versionen werden nach dem Refactor neu bewertet.


0.5 Renderer modularisiert (No-Functional-Change) — ✓ (umgesetzt in 0.5.25)  

## 1 — Frontend-Funktionsbuttons
1.1 Foto/Galerie (Upload/Kamera, löschen) — ☐  
1.2 Bildschirm anlassen (Wake Lock) — ☐  
1.3 Rezept löschen (Papierkorb + Confirm) — ☐  
1.4 Einkaufsliste (optional) — ☐  
1.5 Debug-Panel (nur Dev) — ☐  

## 2 — Parser-Verbesserungen
2.1 JSON-LD: HowToSection/Steps sauber übernehmen — ☐  
2.2 Zutaten-Gruppen: Abschnitts-Header erkennen (TOPPING, MARINADE, …) — ☐  
2.3 Mengen/Brüche: ½, 1/2, 1 1/2 + Dezimal (Komma/Punkt) korrekt skalieren — ☐  
2.4 Einheiten-Ausgabe: Locale „de“ (EL/TL, g, ml …), keine Auto-Übersetzung — ☐  
2.5 Renderer: Steps immer OL/LI, Sections als Überschrift + eigene OL — ☐  
2.6 Bild-Fallback: JSON-LD → og:image → Standardbild — ☐  
2.7 Merge/De-Dupe: JSON-LD + DOM zusammenführen, Duplikate vermeiden — ☐  
2.8 Bereichs- & freie Mengen: „2–3“, „Saft einer halben Zitrone“ skalierbar — ☐  
2.9 Nährwerte: vorhandene auslesen; sonst aus Zutaten pro Portion schätzen — ☐  

## 3 — Import & Admin
3.1 Quelle-URL speichern & anzeigen (canonical/og:url, sonst pageUrl) — ☐  
3.2 Kategorien manuell wählbar; Source-Kategorien nicht übernehmen — ☐  
3.3 „Rezept aktualisieren“ (Refresh aus Quelle, Felder selektiv überschreiben) — ☐  

## 4 — Extras
4.1 Sterne-Bewertung (optional, filterbar) — ☐  
4.2 Frontend-Import-Maske `[sitc_import_form]` (optional) — ☐  
4.3 Filterbare Übersicht (Kategorie, Bewertung …) — ☐  

---

### Notation
- ☐ = offen; ✓ = erledigt (mit Version angeben), z. B.: „✓ umgesetzt in 0.5.16“
- Änderungen am Plan: so knapp wie möglich.  
- Detaillierte Umsetzung steht nicht hier — kommt als Codex-Anweisung.

### Pflege-Regeln
- Jede erledigte Aufgabe bekommt sofort ein ✓ + Versionshinweis.  
- `CHANGELOG.md`: neue Einträge immer oben, Reihenfolge „neu → alt“.