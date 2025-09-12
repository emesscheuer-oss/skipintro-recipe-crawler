0.1 Parser/Renderer stabilisieren
- Parser-Lab reaktiviert (0.5.31)

# SkipIntro Recipe Crawler Ã¢â‚¬â€ Projektplan (Meta)

> Stand (Codebasis): 0.5.16 (Rollback-Baseline, 2025-09-09)  
> Doku-EintrÃƒÂ¤ge aus spÃƒÂ¤teren Work-in-Progress-Versionen werden nach dem Refactor neu bewertet.


0.5 Renderer modularisiert (No-Functional-Change) Ã¢â‚¬â€ Ã¢Å“â€œ (umgesetzt in 0.5.25)  

## 1 Ã¢â‚¬â€ Frontend-Funktionsbuttons
1.1 Foto/Galerie (Upload/Kamera, lÃƒÂ¶schen) Ã¢â‚¬â€ Ã¢ËœÂ  
1.2 Bildschirm anlassen (Wake Lock) Ã¢â‚¬â€ Ã¢ËœÂ  
1.3 Rezept lÃƒÂ¶schen (Papierkorb + Confirm) Ã¢â‚¬â€ Ã¢ËœÂ  
1.4 Einkaufsliste (optional) Ã¢â‚¬â€ Ã¢ËœÂ  
1.5 Debug-Panel (nur Dev) Ã¢â‚¬â€ Ã¢ËœÂ  

## 2 Ã¢â‚¬â€ Parser-Verbesserungen
2.1 JSON-LD: HowToSection/Steps sauber ÃƒÂ¼bernehmen Ã¢â‚¬â€ Ã¢ËœÂ  
2.2 Zutaten-Gruppen: Abschnitts-Header erkennen (TOPPING, MARINADE, Ã¢â‚¬Â¦) Ã¢â‚¬â€ Ã¢ËœÂ  
2.3 Mengen/BrÃƒÂ¼che: Ã‚Â½, 1/2, 1 1/2 + Dezimal (Komma/Punkt) korrekt skalieren Ã¢â‚¬â€ Ã¢ËœÂ  
2.3 (umgesetzt in 0.5.17) âœ…
2.3 Diagnose-Lab vorhanden (tools/qty_lab)
2.4 Einheiten-Ausgabe: Locale Ã¢â‚¬Å¾deÃ¢â‚¬Å“ (EL/TL, g, ml Ã¢â‚¬Â¦), keine Auto-ÃƒÅ“bersetzung Ã¢â‚¬â€ Ã¢ËœÂ  
2.5 Renderer: Steps immer OL/LI, Sections als ÃƒÅ“berschrift + eigene OL Ã¢â‚¬â€ Ã¢ËœÂ  
2.6 Bild-Fallback: JSON-LD Ã¢â€ â€™ og:image Ã¢â€ â€™ Standardbild Ã¢â‚¬â€ Ã¢ËœÂ  
2.7 Merge/De-Dupe: JSON-LD + DOM zusammenfÃƒÂ¼hren, Duplikate vermeiden Ã¢â‚¬â€ Ã¢ËœÂ  
2.8 Bereichs- & freie Mengen: Ã¢â‚¬Å¾2Ã¢â‚¬â€œ3Ã¢â‚¬Å“, Ã¢â‚¬Å¾Saft einer halben ZitroneÃ¢â‚¬Å“ skalierbar Ã¢â‚¬â€ Ã¢ËœÂ  
2.9 NÃƒÂ¤hrwerte: vorhandene auslesen; sonst aus Zutaten pro Portion schÃƒÂ¤tzen Ã¢â‚¬â€ Ã¢ËœÂ  

## 3 Ã¢â‚¬â€ Import & Admin
3.1 Quelle-URL speichern & anzeigen (canonical/og:url, sonst pageUrl) Ã¢â‚¬â€ Ã¢ËœÂ  
3.2 Kategorien manuell wÃƒÂ¤hlbar; Source-Kategorien nicht ÃƒÂ¼bernehmen Ã¢â‚¬â€ Ã¢ËœÂ  
3.3 Ã¢â‚¬Å¾Rezept aktualisierenÃ¢â‚¬Å“ (Refresh aus Quelle, Felder selektiv ÃƒÂ¼berschreiben) Ã¢â‚¬â€ Ã¢ËœÂ  

## 4 Ã¢â‚¬â€ Extras
4.1 Sterne-Bewertung (optional, filterbar) Ã¢â‚¬â€ Ã¢ËœÂ  
4.2 Frontend-Import-Maske `[sitc_import_form]` (optional) Ã¢â‚¬â€ Ã¢ËœÂ  
4.3 Filterbare ÃƒÅ“bersicht (Kategorie, Bewertung Ã¢â‚¬Â¦) Ã¢â‚¬â€ Ã¢ËœÂ  

---

### Notation
- Ã¢ËœÂ = offen; Ã¢Å“â€œ = erledigt (mit Version angeben), z. B.: Ã¢â‚¬Å¾Ã¢Å“â€œ umgesetzt in 0.5.16Ã¢â‚¬Å“
- Ãƒâ€žnderungen am Plan: so knapp wie mÃƒÂ¶glich.  
- Detaillierte Umsetzung steht nicht hier Ã¢â‚¬â€ kommt als Codex-Anweisung.

### Pflege-Regeln
- Jede erledigte Aufgabe bekommt sofort ein Ã¢Å“â€œ + Versionshinweis.  
- `CHANGELOG.md`: neue EintrÃƒÂ¤ge immer oben, Reihenfolge Ã¢â‚¬Å¾neu Ã¢â€ â€™ altÃ¢â‚¬Å“.