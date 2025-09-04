# SkipIntro Recipe Crawler - Projektplan (Meta)

## 0 - Basis (0.5.x)
0.1 Parser/Renderer stabilisieren - o. (umgesetzt in 0.5.11)
    - C LF-Zeilenenden fix umgesetzt in 0.5.15
    - B Ã¢â‚¬â€œ Quarantaene & UTF-8 erledigt in 0.5.12
    - C Mojibake-Cleanup Renderer umgesetzt in 0.5.14
0.2 Skalierung fixen - ~?
0.3 Checkboxen fixen - ~?
0.4 Button-Ausrichtung fixen - ~?

## 1 - Frontend-Funktionsbuttons
1.1 Foto/Galerie (Upload/Kamera, loeschen) - ~?
1.2 Bildschirm-anlassen (Wake Lock) - ~?
1.3 Rezept loeschen (Papierkorb + Confirm) - ~?
1.4 Einkaufsliste (optional) - ~?
1.5 Debug-Panel (nur Dev) - ~?

## 2 - Parser-Verbesserungen
2.10 Vor-Normalisierung/Stopwoerter: "ca./circa/ungef./about/approx." vor/nach Mengen entfernen - o. (umgesetzt in 0.5.9)
2.1 JSON-LD: HowToSection/Steps sauber uebernehmen - ~?
2.2 Zutaten-Gruppen: Abschnitts-Header erkennen (TOPPING, MARINADE, ...) - ~?
2.3 Mengen/Brueche: 1/2, 1 1/2 + Dezimal (Komma/Punkt) korrekt skalieren - ? (umgesetzt in 0.5.5)
    - D umgesetzt in 0.5.13
2.4 Einheiten-Ausgabe: Locale getreu (EL/TL, g, ml ...), keine Auto-Uebersetzung - ? (umgesetzt in 0.5.5)
2.5 Renderer: Steps immer OL/LI, Sections als Ueberschrift + eigene OL - ~?
2.6 Bild-Fallback: JSON-LD -> og:image -> Standardbild - ~?
2.7 Merge/De-Dupe: JSON-LD + DOM zusammenfuehren, Duplikate vermeiden - ? (umgesetzt in 0.5.8)
    - Merge-Prioritaet: JSON-LD > Microdata > DOM; DOM nur als Fallback fuer fehlende Felder.
    - De-Dupe: case-/diakritika-/whitespace-insensitiv; Zeilenreihenfolge stabil (erstes Vorkommen bleibt).
2.8 Bereichs- & freie Mengen: "2-3", "Saft einer halben Zitrone" skalierbar - ~?
    - D umgesetzt in 0.5.13
2.9 Naehrwerte auslesen und mitschreiben, falls nicht vorhanden aus den Zutaten heruntergerechnet auf eine Portion/Person - ~?

## 3 - Import & Admin
3.1 Quelle-URL speichern & anzeigen (canonical/og:url, sonst pageUrl) - ~?
3.2 Kategorien manuell waehlbar; Source-Kategorien nicht uebernehmen - ~?
3.3 Rezept im Backend erneut laden (Refresh - die Quelle ist bekannt) und ueberschreiben - somit koennten kuenftige Parser-Anpassungen auf alte Rezepte angewendet werden, ohne diese loeschen und neu anlegen zu muessen - ? (umgesetzt in 0.5.3; korrigiert in 0.5.4)

## 4 - Extras
4.1 Sterne-Bewertung (optional, filterbar) - ~?
4.2 Frontend-Import-Maske `[sitc_import_form]` (optional) - ~?
4.3 Filterbare Uebersicht (Kategorie, Bewertung ...) - ~?

---

### Notation
- ~? = offen - ? = erledigt (mit Version), z. B.: "? (umgesetzt in 0.5.2)"
- Aenderungen am Plan: so knapp wie moeglich.
- Detaillierte Umsetzung steht nicht hier, sondern kommt als Codex-Anweisung (siehe Vorlage unten).

### Pflege-Regeln
- Jede erledigte Aufgabe bekommt sofort ein o. + Versionshinweis.
- CHANGELOG.md: neue Eintraege immer oben, Reihenfolge neu -> alt.