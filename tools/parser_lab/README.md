# [ARCHIVE] parser_lab (Legacy/Fallback)

Status: LEGACY/Fallback. Keine Feature-Entwicklung mehr; nur minimale Bugfixes bei Bedarf.

- Zweck: Manuelle Notfalldiagnose und Vergleich. Nutzt ausschließlich die Parser-Engine über Toggle (`legacy|mod|auto`).
- Verboten: Eigene Regeln/Mapper im Lab. Parser-Weiterentwicklungen erfolgen ausschließlich im Codex-Prozess.
- Auto-Eval: Nach jeder Parser-Änderung müssen die Fixtures grün sein.
- Backend: Kein eigener Menüpunkt; Direkt-URL bleibt funktionsfähig (`tools/parser_lab/run.php`).
- Dateien: `run.php`, `auto_eval_web.php`, `lib/`, `fixtures/`.

Hinweis: Für reguläre Validierung im Admin nutze die Seite „Beiträge → Validierung“ (Parser-Tests, HTML/JSON-Report).

