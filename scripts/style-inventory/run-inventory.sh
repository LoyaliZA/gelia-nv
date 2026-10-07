#!/usr/bin/env bash
# Genera inventarios CSV de estilos GELIA (Fase 1). Requiere: rg (ripgrep).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
source "$SCRIPT_DIR/lib.sh"

mkdir -p "$OUT_DIR"

echo "Writing inventories to $OUT_DIR"

write_csv_header "$OUT_DIR/inventory-hex.csv"
append_rg_matches '#[0-9a-fA-F]{3,8}' hex "$OUT_DIR/inventory-hex.csv" \
    "$ROOT/resources/js" -g '*.jsx' \
    "$ROOT/resources/views" -g '*.blade.php' \
    "$ROOT/resources/css/gelia" -g '*.css'

write_csv_header "$OUT_DIR/inventory-tailwind-literal.csv"
append_rg_matches 'bg-zinc-|text-gray-|bg-slate-|text-slate-|bg-neutral-|text-neutral-' tailwind-literal "$OUT_DIR/inventory-tailwind-literal.csv" \
    "$ROOT/resources/js" -g '*.jsx'

write_csv_header "$OUT_DIR/inventory-inline-style.csv"
append_rg_matches 'style=\{\{' inline-style "$OUT_DIR/inventory-inline-style.csv" \
    "$ROOT/resources/js" -g '*.jsx'

write_csv_header "$OUT_DIR/inventory-css-vars-jsx.csv"
append_rg_matches 'var\(--' css-var "$OUT_DIR/inventory-css-vars-jsx.csv" \
    "$ROOT/resources/js" -g '*.jsx'

echo "Done."
