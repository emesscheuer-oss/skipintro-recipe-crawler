# Parser Fixtures (tools/parser_lab/fixtures)

Diese Dateien sind künstliche Testfälle für den Parser. Sie enthalten bewusst problematische Muster:
- Brüche (½, 1½, 1/2, 1 1/2)
- “ca. … ml/g” (soll skalierbar werden)
- Doppelungen/Case-Unterschiede
- Bereichsangaben (2–3)

Dateien:
- butter_chicken.jsonld — JSON-LD-Variante, inkl. Doppelung und “ca.”-Fällen
- haehnchen_biryani.html — DOM-Rezept mit ½, 2–3, 1/2, 1 1/2
- ente_chop_suey.html — DOM-Rezept mit “½ TK Ente …”

**Hinweise**
- Lizenzsicher: keine Inhalte aus Originalseiten.
- Für lokale Tests mit `tools/parser_lab/run.php` gedacht.
- Bitte NICHT löschen; bei Parser-Änderungen Testfälle erweitern.
