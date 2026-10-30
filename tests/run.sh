#!/usr/bin/env bash
# Every local check; CI runs the same script.
set -euo pipefail
cd "$(dirname "$0")/.."
for file in $(find src config -name '*.php'); do php -l "$file" > /dev/null; done
echo 'PHP syntax checks passed.'
for test in tests/*.php; do php "$test"; done
python3 tests/package-layout.py
