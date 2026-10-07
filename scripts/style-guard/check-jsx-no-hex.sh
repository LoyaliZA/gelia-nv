#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
source "$SCRIPT_DIR/lib.sh"

mode="check"
for arg in "$@"; do
    case "$arg" in
        --check) mode="check" ;;
        --update-baseline) mode="update-baseline" ;;
        --list) mode="list" ;;
        -h|--help)
            echo "Uso: $0 [--check|--update-baseline|--list]"
            exit 0
            ;;
        *)
            echo "Opción desconocida: $arg" >&2
            exit 2
            ;;
    esac
done

case "$mode" in
    list)
        style_guard_collect_jsx_hex | LC_ALL=C sort -u
        ;;
    update-baseline)
        style_guard_write_baseline "$BASELINE_JSX_HEX" style_guard_collect_jsx_hex
        echo "Baseline actualizado: $BASELINE_JSX_HEX"
        ;;
    check)
        style_guard_check_baseline "$BASELINE_JSX_HEX" style_guard_collect_jsx_hex "jsx"
        ;;
esac
