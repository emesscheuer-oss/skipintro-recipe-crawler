# Rezept-Seeds (lokale Smoke-Tests)

Pflegen Sie hier 3–5 repräsentative Post-IDs, die lokal für den Frontend-Smoke (Vergleich `?engine=mod` vs. `?engine=legacy`) genutzt werden.

- Post-ID: TBD — Titel: TBD — Quelle: TBD
- Post-ID: TBD — Titel: TBD — Quelle: TBD
- Post-ID: TBD — Titel: TBD — Quelle: TBD

Hinweise
- Seeds sollten typische Quellen, Formate und Problemfälle abdecken (Brüche, Ranges, Klammern/Notizen, verschiedene Units).
- Nach Parser-Änderungen: Lokal aktualisieren (Rezept-Tools → „Rezept aktualisieren“) und dann Frontend vergleichen.

Golden Fixtures (Qty)
- Für jede Iteration (Datum) die Live-Zeilen, die fehlinterpretiert wurden, als Golden-Files ablegen:
  - tools/parser_lab/fixtures/golden/YYYYMMDD/<slug>.txt
  - tools/parser_lab/fixtures/golden/YYYYMMDD/<slug>.expected.json
- Liste der herangezogenen Live-Posts hier ergänzen (Transparenz):
  - YYYY-MM-DD: Post-ID X, Titel „…“, Quelle „…“, Slug „…“
  - YYYY-MM-DD: …
