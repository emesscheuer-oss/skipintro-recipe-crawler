# Projektplan – Skipintro Recipe Crawler (v0.6.01)

## Status (Kurz)
- ✅ Parser v2 + Normalizer im Refresh aktiv; Schema & Struct werden beim Refresh neu erzeugt (mit Backups).
- ✅ Legacy-Debug-Endpunkte per `SITC_DEBUG_TOOLS` gated (default off).
- ⏭ Frontend-UI: „Personenanzahl“-Label durch Icon ersetzen.
- ⏭ Fallback-Bild: dynamische URL prüfen/sichern (kein absoluter Pfad).
- ⏭ Shortcode `[sitc_import_form]` Funktionscheck (registriert in `includes/frontend-import.php`).

## Nächste Schritte (0.6.02)
1) Frontend: Personenanzahl-Icon statt Text.
2) Fallback-Image-Handling absichern (dynamischer Pfad, Fehlerfall).
3) Shortcode Health-Check + kleine UX-Verbesserungen.
4) (Optional) Admin-Hinweiszeile: „Geparst mit Parser v2 – letzter Refresh: Datum/Uhrzeit“.

## Notation
- ◻ offen; ✓ erledigt (Version in Klammern)
