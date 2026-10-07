#!/usr/bin/env bash
# Shared helpers for GELIA style guards (Fase 3).

set -eu
set -o pipefail

GUARD_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../style-inventory/lib.sh
source "$GUARD_DIR/../style-inventory/lib.sh"

HEX_JSX_REGEX='#[0-9a-fA-F]{3,8}'
BASELINE_JSX_HEX="${ROOT}/docs/style-system/baseline/jsx-hex-allowlist.tsv"
BASELINE_CSS_FEATURES_HEX="${ROOT}/docs/style-system/baseline/css-features-hex-allowlist.tsv"

style_line_has_inline_exception() {
    local file="$1"
    local line_no="$2"
    local prev=0
    if [[ "$line_no" =~ ^[0-9]+$ ]] && (( line_no > 1 )); then
        prev=$((line_no - 1))
    else
        return 1
    fi
    local prev_line
    prev_line="$(sed -n "${prev}p" "$file" 2>/dev/null || true)"
    [[ "$prev_line" == *"ponytail: style-exception"* ]]
}

# Emite líneas path<TAB>line (ordenadas) en stdout.
style_guard_collect_jsx_hex() {
    local row path rest line_no rel abs
    while IFS= read -r row; do
        [[ -z "$row" ]] && continue
        path="${row%%:*}"
        rest="${row#*:}"
        line_no="${rest%%:*}"
        abs="$path"
        if [[ "$abs" != /* ]]; then
            abs="$ROOT/$abs"
        fi
        rel="${path#$ROOT/}"
        rel="${rel#./}"

        if style_path_exempt "$rel"; then
            continue
        fi
        if style_line_has_inline_exception "$abs" "$line_no"; then
            continue
        fi
        printf '%s\t%s\n' "$rel" "$line_no"
    done < <(
        rg -n --no-heading -S "$HEX_JSX_REGEX" "$ROOT/resources/js" -g '*.jsx' 2>/dev/null || true
    )
}

style_guard_collect_css_features_hex() {
    local row path rest line_no rel abs
    while IFS= read -r row; do
        [[ -z "$row" ]] && continue
        path="${row%%:*}"
        rest="${row#*:}"
        line_no="${rest%%:*}"
        abs="$path"
        if [[ "$abs" != /* ]]; then
            abs="$ROOT/$abs"
        fi
        rel="${path#$ROOT/}"
        rel="${rel#./}"

        case "$rel" in
            resources/css/gelia/features/*) ;;
            *) continue ;;
        esac

        if style_line_has_inline_exception "$abs" "$line_no"; then
            continue
        fi
        printf '%s\t%s\n' "$rel" "$line_no"
    done < <(
        rg -n --no-heading -S "$HEX_JSX_REGEX" "$ROOT/resources/css/gelia/features" -g '*.css' 2>/dev/null || true
    )
}

style_guard_write_baseline() {
    local outfile="$1"
    local collector="$2"
    mkdir -p "$(dirname "$outfile")"
    {
        printf '# path\tline — generado por style-guard; usar --update-baseline con criterio.\n'
        "$collector" | LC_ALL=C sort -u
    } > "$outfile"
}

style_guard_check_baseline() {
    local outfile="$1"
    local collector="$2"
    local label="$3"

    if [[ ! -f "$outfile" ]]; then
        echo "style-guard: falta baseline $outfile — ejecutar --update-baseline primero." >&2
        return 1
    fi

    local current baseline new_entries
    current="$(mktemp)"
    baseline="$(mktemp)"
    trap 'rm -f "$current" "$baseline"' RETURN

    "$collector" | LC_ALL=C sort -u > "$current"
    grep -v '^#' "$outfile" | LC_ALL=C sort -u > "$baseline"

    new_entries="$(comm -23 "$current" "$baseline" || true)"
    if [[ -n "$new_entries" ]]; then
        echo "style-guard ($label): nuevos literales hex no permitidos (usa tokens, var(--*) o ponytail: style-exception):" >&2
        echo "$new_entries" >&2
        echo "Si el legado es intencional (evitar): npm run check:style-jsx:baseline o check:style-css:baseline" >&2
        return 1
    fi
    return 0
}
