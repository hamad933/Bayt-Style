#!/usr/bin/env bash
set -euo pipefail

python3 --version
python3 - <<'PY'
import sys
if sys.version_info < (3, 12):
    raise SystemExit("Python 3.12+ is required for the RP01 automation control plane")
import tools.rp01_automation
print("RP01_AUTOMATION_PYTHON_READY=TRUE")
PY

if [[ "${1:-}" == "--product-check" ]]; then
  php --version | head -n 1
  node --version
  composer --version
  npm --version
  test -f composer.lock
  test -f package-lock.json
  echo "RP01_PRODUCT_TOOLCHAIN_PRESENT=TRUE"
fi
