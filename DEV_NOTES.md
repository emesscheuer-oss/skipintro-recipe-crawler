# Entwickler‑Notizen: Einheiten‑Policy (DE)

Diese Notiz beschreibt, wie Einheiten intern vs. in der Anzeige gehandhabt werden.

- Intern (Parser/Kanonik): Zutaten dürfen in kanonischen Kurzformen gespeichert sein (z. B. `tsp`, `tbsp`, `cup`, `piece`, `pinch`, `can`, `bunch`, `g`, `kg`, `ml`, `l`). Parser oder Import müssen nicht auf Deutsch umschreiben.
- Anzeige (Renderer/UI): Vor der Ausgabe werden Einheiten auf deutsche Standards gemappt.
  - TL ↔ tsp (Ausgabe: „TL“)
  - EL ↔ tbsp (Ausgabe: „EL“)
  - Tasse ↔ cup (Ausgabe: „Tasse“)
  - Stück ↔ piece (Ausgabe: „Stück“)
  - Prise ↔ pinch (Ausgabe: „Prise“)
  - Dose ↔ can (Ausgabe: „Dose“)
  - Bund ↔ bunch (Ausgabe: „Bund“)
  - g, kg, ml, l bleiben unverändert, imperiale `oz`/`lb` werden unverändert angezeigt (üblich im DE‑Kontext)

Mengen/Format (Zusammenspiel mit 2.3)
- Renderer formatiert Zahlen mit Dezimal‑Komma (z. B. `1,5`).
- Unicode‑Brüche (`½`, `¼`, …), Bruchschreibweisen (`1/2`, `1 1/2`) sowie Dezimalzahlen (`1,5`/`1.5`) werden für die Skalierung in float konvertiert.
- Nach Skalierung wird wieder mit Komma formatiert (z. B. „1,5 EL“). Einfache Fälle wie „½ TL“ skaliert ×2 → „1 TL“.

Implementierung
- Mapping & Zahlenfunktionen in `includes/renderer.php`:
  - `sitc_unit_to_de($unit)` – mappt kanonisch → DE (nur Anzeige)
  - `sitc_coerce_qty_float($qty)` – parst Brüche/Dezimal in float
  - `sitc_format_qty_de($val)` – formatiert float mit Komma
- Kommentare im Code verweisen auf diese Notiz.

Hinweis
- Bestehende Daten werden nicht hart migriert; das Mapping passiert ausschließlich im Renderer.
