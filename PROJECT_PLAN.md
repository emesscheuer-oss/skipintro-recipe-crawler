# SkipIntro Recipe Crawler – Projektplan

## Ziel
- Private Rezept-Sammlung in WordPress.
- Rezepte per URL importieren (JSON-LD bevorzugt, Fallback HTML-Scraping).
- Speicherung in Standard-Blog-Posts, Ausgabe im Frontend mit Zutaten, Zubereitung, Skalierung usw.

## Status Quo (Version 0.5)
- Parser funktionsfähig, aber Probleme bei Einheiten, Brüchen, Zutatenblöcken.
- Checkboxen und Skalierung fehlerhaft.
- Funktionsbuttons sichtbar, aber ohne Funktion.

## Roadmap

### F0 – Basis stabilisieren (0.5.x)
- Parser/Renderer optimieren.
- Bugs fixen bei Skalierung, Checkboxen, Button-Ausrichtung.

### F1 – Frontend-Funktionsbuttons
- Foto hinzufügen (Upload/Kamera, Galerie unter Rezept, löschen im Frontend).
- Bildschirm-anlassen (Wake Lock API).
- Rezept löschen (Trash, doppelte Bestätigung, in Papierkorb).
- Einkaufsliste (optional).
- Debug-Panel.

### F2 – Parser-Verbesserungen
- Einheiten & Mengen verbessern.
- Brüche („½“) korrekt parsen.
- Zutatenblöcke („TOPPING:“) als Zwischenüberschrift.
- Zubereitungsschritte besser strukturieren.

### F3 – Renderer & UI
- Checkboxen sauber durchstreichen.
- Skalierung stabilisieren.
- Buttons einheitlich gestalten.
- Debug-Ausgabe optional ausblendbar.

### F4 – Import & Admin
- Quelle-URL speichern und anzeigen.
- Kategorien manuell auswählen, Quelle-Kategorien nicht übernehmen.
- Bild-Fallback definieren.

### F5 – Extras
- Sterne-Bewertung im Frontend.
- Optionale Frontend-Import-Maske `[sitc_import_form]`.
- Filterbare Rezeptübersicht.
