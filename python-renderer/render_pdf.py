#!/usr/bin/env python3
"""
AIMM PDF renderer (stub).

Usage:
  python render_pdf.py <report-dto.json> <output.pdf>
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

from reportlab.lib.pagesizes import letter
from reportlab.pdfgen import canvas


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: python render_pdf.py <report-dto.json> <output.pdf>", file=sys.stderr)
        return 1

    dto_path = Path(sys.argv[1])
    output_path = Path(sys.argv[2])

    if not dto_path.exists():
        print(f"Error: DTO file not found: {dto_path}", file=sys.stderr)
        return 1

    dto = json.loads(dto_path.read_text(encoding="utf-8"))
    output_path.parent.mkdir(parents=True, exist_ok=True)

    c = canvas.Canvas(str(output_path), pagesize=letter)
    text = c.beginText(72, 720)
    text.textLine("AIMM renderer stub")
    text.textLine(f"DTO: {dto_path.name}")
    text.textLine("")

    for line in json.dumps(dto, indent=2).splitlines():
        text.textLine(line)

    c.drawText(text)
    c.showPage()
    c.save()

    print(f"Wrote stub PDF to {output_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

