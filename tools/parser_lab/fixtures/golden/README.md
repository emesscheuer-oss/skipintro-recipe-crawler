Golden Fixtures (Qty-focused)

Structure
- Directory per collection date: YYYYMMDD
- For each case, add two files side-by-side:
  - <slug>.txt            # one ingredient line per row (UTF-8)
  - <slug>.expected.json  # expected fields for each line

Expected JSON format
- Either a plain array of objects (aligned by line index), or { "cases": [ ... ] }
- Only include relevant fields: qty, min_qty, max_qty, unit, item, note
- Examples:

[
  { "qty": 0.5, "unit": "l", "item": "milch" },
  { "min_qty": 1.0, "max_qty": 2.0, "unit": "tbsp", "item": "butter" },
  { "qty": 2, "unit": "piece", "item": "eier", "note": null }
]

Conventions
- Use decimal point in expected numeric values (0.5 rather than 0,5)
- For ranges, provide both min_qty and max_qty
- Omit keys you don’t want to assert
- Leave note out (omit) if it’s irrelevant; set it explicitly to null to assert absence

How to run
- Open in browser: tools/parser_lab/auto_eval_web.php
- Section “Golden (Qty)” shows per-file pass/fail and metrics:
  - qty_parse_rate, range_detect_rate, decimal_comma_clean, unit_attach_clean, note_separation_ok

