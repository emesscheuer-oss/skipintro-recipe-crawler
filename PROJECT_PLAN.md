# SkipIntro Recipe Crawler – Projektplan (Meta)

## 0 – Basis (0.5.x)
0.1 Parser/Renderer stabilisieren – ~?  
0.2 Skalierung fixen – ~?  
0.3 Checkboxen fixen – ~?  
0.4 Button-Ausrichtung fixen – ~?  

## 1 – Frontend-Funktionsbuttons
1.1 Foto/Galerie (Upload/Kamera, löschen) – ~?  
1.2 Bildschirm-anlassen (Wake Lock) – ~?  
1.3 Rezept löschen (Papierkorb + Confirm) – ~?  
1.4 Einkaufsliste (optional) – ~?  
1.5 Debug-Panel (nur Dev) – ~?  

## 2 – Parser-Verbesserungen
2.1 JSON-LD: HowToSection/Steps sauber übernehmen – ~?  
2.2 Zutaten-Gruppen: Abschnitts-Header erkennen (TOPPING, MARINADE, …) – ~?  
2.3 Mengen/Brüche: ½, 1/2, 1 1/2 + Dezimal (Komma/Punkt) korrekt skalieren — ✅ (umgesetzt in 0.5.5)  
2.4 Einheiten-Ausgabe: Locale getreu (EL/TL, g, ml …), keine Auto-Übersetzung — ✅ (umgesetzt in 0.5.5)  
2.5 Renderer: Steps immer OL/LI, Sections als Überschrift + eigene OL – ~?  
2.6 Bild-Fallback: JSON-LD → og:image → Standardbild – ~?  
2.7 Merge/De-Dupe: JSON-LD + DOM zusammenführen, Duplikate vermeiden — ✅ (umgesetzt in 0.5.6)  
    - Merge-Priorität: JSON-LD > Microdata > DOM; DOM nur als Fallback für fehlende Felder.
    - De-Dupe: case-/diakritika-/whitespace-insensitiv; Zeilenreihenfolge stabil (erstes Vorkommen bleibt).
2.8 Bereichs- & freie Mengen: „2–3“, „Saft einer halben Zitrone“ skalierbar – ~?  
2.9 Nährwerte auslesen und mitschreiben, falls nicht vorhanden aus den Zutaten heruntergerechnet auf eine Portion/Person – ~?  

## 3 – Import & Admin
3.1 Quelle-URL speichern & anzeigen (canonical/og:url, sonst pageUrl) – ~?  
3.2 Kategorien manuell wählbar; Source-Kategorien nicht übernehmen – ~?  
3.3 Rezept im Backend erneut laden (Refresh – die Quelle ist bekannt) und überschreiben – somit könnten künftige Parser-Anpassungen auf alte Rezepte angewendet werden, ohne diese löschen und neu anlegen zu müssen — ✅ (umgesetzt in 0.5.3; korrigiert in 0.5.4)

## 4 – Extras
4.1 Sterne-Bewertung (optional, filterbar) – ~?  
4.2 Frontend-Import-Maske `[sitc_import_form]` (optional) – ~?  
4.3 Filterbare Übersicht (Kategorie, Bewertung …) – ~?  

---

### Notation
- ~? = offen — ✅ = erledigt (mit Version), z. B.: „✅ (umgesetzt in 0.5.2)“
- Änderungen am Plan: so knapp wie möglich.
- Detaillierte Umsetzung steht nicht hier, sondern kommt als Codex-Anweisung (siehe Vorlage unten).

### Pflege-Regeln
- Jede erledigte Aufgabe bekommt sofort ein o. + Versionshinweis.  
- CHANGELOG.md: neue Einträge immer oben, Reihenfolge neu → alt.
