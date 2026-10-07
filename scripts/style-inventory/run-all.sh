#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
"$DIR/run-inventory.sh"
"$DIR/extract-canonical.sh"
"$DIR/generate-module-report.sh"
