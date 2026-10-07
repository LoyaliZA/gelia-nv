#!/usr/bin/env bash
# Agrega inventarios CSV en informe por módulo (Fase 2 planning).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
# shellcheck source=lib.sh
source "$SCRIPT_DIR/lib.sh"

INV_DIR="${STYLE_INVENTORY_OUT:-$ROOT/docs/style-system/inventory}"
REPORT="$ROOT/docs/style-system/module-report.md"

if [[ ! -f "$INV_DIR/inventory-hex.csv" ]]; then
    echo "Run run-inventory.sh first." >&2
    exit 1
fi

aggregate_pattern() {
    local file="$1"
    local pattern_filter="${2:-}"
    awk -F'\t' -v pf="$pattern_filter" '
        NR == 1 { next }
        {
            mod = $5
            if (pf != "" && $4 != pf) next
            counts[mod]++
            if ($6 == "") unclassified[mod]++
        }
        END {
            for (m in counts) print counts[m], m
        }
    ' "$file" | sort -rn
}

top_files() {
    local file="$1"
    local pattern_id="${2:-}"
    awk -F'\t' -v pid="$pattern_id" '
        NR == 1 { next }
        {
            if (pid != "" && $4 != pid) next
            paths[$1]++
        }
        END {
            for (p in paths) print paths[p], p
        }
    ' "$file" | sort -rn | head -15 || true
}

classification_summary() {
    local file="$1"
    awk -F'\t' '
        NR == 1 { next }
        {
            c = $6
            if (c == "") c = "(vacío)"
            total[c]++
        }
        END {
            for (k in total) print total[k], k
        }
    ' "$file" | sort -rn 2>/dev/null || true
}

{
    echo "# Informe por módulo — inventario de estilos"
    echo ""
    echo "Generado por \`scripts/style-inventory/generate-module-report.sh\`."
    echo ""
    echo "## Resumen de clasificación (todos los CSV)"
    echo ""
    for f in inventory-hex inventory-tailwind-literal inventory-inline-style inventory-css-vars-jsx; do
        echo "### \`$f.csv\`"
        echo ""
        echo "| Cantidad | Clasificación |"
        echo "|----------|-----------------|"
        classification_summary "$INV_DIR/$f.csv" | while read -r count label; do
            echo "| $count | $label |"
        done
        echo ""
    done

    echo "## Hallazgos por módulo (hex + tailwind literal + inline con color)"
    echo ""
    echo "Conteo de filas por \`module\` en inventarios de legado (hex, tailwind-literal, inline-style)."
    echo ""
    echo "| Módulo | hex | tailwind | inline-style |"
    echo "|--------|-----|----------|--------------|"

    tmp="$(mktemp)"
    for mod in $(awk -F'\t' 'NR>1{print $5}' "$INV_DIR"/*.csv | sort -u); do
        h=$(awk -F'\t' -v m="$mod" 'NR>1{if($5==m)c++} END{print c+0}' "$INV_DIR/inventory-hex.csv")
        t=$(awk -F'\t' -v m="$mod" 'NR>1{if($5==m)c++} END{print c+0}' "$INV_DIR/inventory-tailwind-literal.csv")
        i=$(awk -F'\t' -v m="$mod" 'NR>1{if($5==m)c++} END{print c+0}' "$INV_DIR/inventory-inline-style.csv")
        if [[ "${h:-0}" -gt 0 || "${t:-0}" -gt 0 || "${i:-0}" -gt 0 ]]; then
            echo "| $mod | $h | $t | $i |"
        fi
    done

    echo ""
    echo "## Top archivos (migración Fase 2)"
    echo ""
    report_top() {
        local label="$1" file="$2" pid="$3"
        echo "### $label"
        echo ""
        top_files "$INV_DIR/$file" "$pid" | while read -r count path; do
            echo "- \`$count\` — \`$path\`"
        done
        echo ""
    }
    report_top "Hex" inventory-hex.csv hex
    report_top "Tailwind literal" inventory-tailwind-literal.csv tailwind-literal
    report_top "Inline style" inventory-inline-style.csv inline-style

    echo "## Hotspots revisados (clasificación explícita)"
    echo ""
    echo "Ver [hotspot-classifications.md](./hotspot-classifications.md)."
} > "$REPORT"

echo "Wrote $REPORT"
