#!/usr/bin/env bash
# Shared helpers for GELIA style inventory (Fase 1).

set -eu
set -o pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
OUT_DIR="${STYLE_INVENTORY_OUT:-$ROOT/docs/style-system/inventory}"

csv_escape() {
    local s="${1//\"/\"\"}"
    printf '"%s"' "$s"
}

derive_module() {
    local path="$1"
    path="${path#$ROOT/}"
    path="${path#./}"
    case "$path" in
        resources/js/Pages/*)
            local rest="${path#resources/js/Pages/}"
            echo "${rest%%/*}"
            ;;
        resources/js/Components/*)
            local rest="${path#resources/js/Components/}"
            echo "Components/${rest%%/*}"
            ;;
        resources/js/Layouts/*) echo "Layouts" ;;
        resources/views/reportes/*) echo "Reportes/PDF" ;;
        resources/views/rh/*) echo "Rh/PDF" ;;
        resources/views/activos/*) echo "Activos/PDF" ;;
        resources/views/punto-venta/*) echo "PuntoVenta/PDF" ;;
        resources/views/emails/*) echo "Emails" ;;
        resources/views/vendor/mail/*) echo "Emails" ;;
        resources/views/*) echo "Views/Standalone" ;;
        resources/css/gelia/*) echo "CSS/Gelia" ;;
        *) echo "Other" ;;
    esac
}

# Rutas exentas del guard hex en JSX (L2 — ver docs/style-system/exceptions.md).
# Exit 0 = exento; 1 = sujeto a contrato / ratchet.
style_path_exempt() {
    local path="$1"
    path="${path#$ROOT/}"
    path="${path#./}"

    case "$path" in
        *MapaGoogle.jsx|*MapaEditorZonas.jsx|*MapaLogistico.jsx|*BuscadorPlacesGoogle.jsx) return 0 ;;
        *FirmaCanvas.jsx|*OverlayFirma*|*ModalFirmar*) return 0 ;;
        resources/js/Pages/Soporte/Manuales/content/*) return 0 ;;
        *cargarChartJs.js|*GraficaUtilidadPeriodo.jsx|*ModalAnalisis.jsx) return 0 ;;
        *Personalizacion/*|*GestorDePersonalizacion*|resources/js/Layouts/AppLayout.jsx|*TablaTemas.jsx) return 0 ;;
    esac
    return 1
}

# Auto-classification: S1 | S2 | L1 | L2 | L3 | L4 | (empty = manual)
auto_classify() {
    local path="$1"
    local pattern="$2"
    local line="$3"

    # Standalone public pages
    case "$path" in
        *privacidad-app.blade.php|*welcome.blade.php) echo "L3"; return ;;
    esac

    # Email
    case "$path" in
        resources/views/emails/*|resources/views/vendor/mail/*) echo "L3"; return ;;
    esac

    # Print / PDF blades
    case "$path" in
        resources/views/reportes/*|resources/views/rh/*|resources/views/activos/*|resources/views/punto-venta/*|resources/views/saldos_favor/*|resources/views/manuales/*|resources/views/control_pedidos/*) echo "L4"; return ;;
    esac

    if style_path_exempt "$path"; then
        echo "L2"
        return
    fi

    # Canonical token definitions
    case "$path" in
        resources/css/gelia/tokens.css) echo "S1"; return ;;
        resources/css/gelia/primitives.css|resources/css/gelia/*.css) echo "S1"; return ;;
    esac

    case "$pattern" in
        tailwind-literal) echo "S2"; return ;;
        css-var)
            if [[ "$line" =~ var\(--(color-|theme-|gelia-|bg-|font-) ]]; then
                echo "S1"
            else
                echo "S1"
            fi
            return
            ;;
        hex)
            case "$path" in
                *ControlPedidos/Tienda/Index.jsx|*TablaEstatusPedidos*|*pedidosBmaStyles*) echo "L1"; return ;;
                *BadgeListaDescuento.jsx|*animations.css) echo "L1"; return ;;
                resources/js/Pages/Rh/*/Index.jsx|*Rh/Dashboard/Index.jsx) echo "S2"; return ;;
                resources/css/gelia/print.css) echo "L4"; return ;;
                resources/css/gelia/*) echo "S1"; return ;;
            esac
            echo "S2"
            return
            ;;
        inline-style)
            if [[ "$line" =~ (#[0-9a-fA-F]{3,8}|color:|background|backgroundColor|borderColor|fill:|stroke:) ]]; then
                case "$path" in
                    *ControlPedidos/Tienda/Index.jsx|*Facturas/Index.jsx|*Cedis/Index.jsx|*Auditar/Index.jsx) echo "L1"; return ;;
                    *Admin/Clientes.jsx|*ModalFormCliente*|*Profile/*) echo "S2"; return ;;
                esac
                echo "S2"
            else
                echo "S1"
            fi
            return
            ;;
    esac

    echo ""
}

auto_notes() {
    local path="$1"
    local pattern="$2"
    local classification="$3"
    local line="$4"

    case "$classification" in
        L2)
            case "$path" in
                *Mapa*) echo "Google Maps / polígonos" ;;
                *Firma*|*OverlayFirma*) echo "Canvas firma fondo trazo" ;;
                *Manuales/content/*) echo "Diagrama didáctico" ;;
                *Grafica*|*ModalAnalisis*) echo "Chart.js dataset" ;;
                *Personalizacion*|*AppLayout*) echo "Origen acento / personalización" ;;
                *) echo "Excepción técnica" ;;
            esac
            return
            ;;
        L3) echo "Página o correo standalone" ;;
        L4) echo "PDF / impresión DejaVu" ;;
        L1) echo "Color de dominio; tokenizar en Fase 3" ;;
        S2) echo "Migrar a theme-* o var(--color-*)" ;;
        S1)
            if [[ "$pattern" == "inline-style" ]] && [[ ! "$line" =~ (color:|background|#) ]]; then
                echo "inline dimensional/layout OK"
            else
                echo ""
            fi
            ;;
        *) echo "Revisión manual pendiente" ;;
    esac
}

write_csv_header() {
    local file="$1"
    # Tab-separated: match lines often contain commas (valid in quoted CSV but awkward for awk).
    printf '%s\n' 'path	line	match	pattern	module	classification	notes' > "$file"
}

append_rg_matches() {
    local pattern_regex="$1"
    local pattern_id="$2"
    local outfile="$3"
    shift 3
    # Remaining args: rg paths and globs

    rg -n --no-heading -S "$pattern_regex" "$@" 2>/dev/null | while IFS= read -r row; do
        local path line rest rel
        path="${row%%:*}"
        rest="${row#*:}"
        line="${rest%%:*}"
        local match="${rest#*:}"
        rel="${path#$ROOT/}"
        local module classification notes
        module="$(derive_module "$path")"
        classification="$(auto_classify "$rel" "$pattern_id" "$match")"
        notes="$(auto_notes "$rel" "$pattern_id" "$classification" "$match")"
        # Strip tabs/newlines from match for TSV safety
        match="${match//$'\t'/ }"
        match="${match//$'\n'/ }"
        printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
            "$rel" "$line" "$match" "$pattern_id" "$module" "$classification" "$notes" >> "$outfile"
    done || true
}
