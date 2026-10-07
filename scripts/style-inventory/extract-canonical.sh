#!/usr/bin/env bash
# Genera docs/style-system/canonical-tokens.md desde fuentes del sistema.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
DOC="$ROOT/docs/style-system/canonical-tokens.md"

mkdir -p "$(dirname "$DOC")"

{
    echo "# Tokens y consumo canónicos GELIA"
    echo ""
    echo "Generado por \`scripts/style-inventory/extract-canonical.sh\`. No editar manualmente; regenerar tras cambios en tokens."
    echo ""
    echo "## Variables CSS (\`resources/css/gelia/tokens.css\`)"
    echo ""
    echo '```'
    rg --only-matching --no-heading -e '--[a-zA-Z0-9_-]+' "$ROOT/resources/css/gelia/tokens.css" | sort -u
    echo '```'
    echo ""
    echo "## Aliases Tailwind (\`resources/css/app.css\` @theme)"
    echo ""
    echo '```'
    sed -n '/@theme {/,/^}/p' "$ROOT/resources/css/app.css" | rg --only-matching -e '--[a-zA-Z0-9_-]+' | sort -u
    echo '```'
    echo ""
    echo "## Clases primitivas (\`resources/css/gelia/primitives.css\`, prefijo \`theme-\` / \`gelia-\`)"
    echo ""
    echo '```'
    rg --only-matching --no-heading -e '\.(theme-[a-zA-Z0-9_-]+|gelia-[a-zA-Z0-9_-]+)' "$ROOT/resources/css/gelia/primitives.css" \
        | sed 's/^\.//' | sort -u | head -80
    echo "# ... (ver primitives.css para lista completa)"
    echo '```'
    echo ""
    echo "## Exports \`geliaTheme.js\`"
    echo ""
    echo '```'
    rg '^export (const|function)' "$ROOT/resources/js/utils/geliaTheme.js"
    echo '```'
    echo ""
    echo "## Mapa \`GELIA_ESTADO_VIVO_TONO\`"
    echo ""
    echo '```'
    sed -n '/GELIA_ESTADO_VIVO_TONO/,/};/p' "$ROOT/resources/js/utils/geliaTheme.js"
    echo '```'
} > "$DOC"

echo "Wrote $DOC"
