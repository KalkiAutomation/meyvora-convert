#!/usr/bin/env bash
# Regenerate public/js/*.min.js and public/css/*.min.css from non-min sources.
# Requires Node.js + npm (uses local devDependencies from repo root package.json).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

if ! command -v npm >/dev/null 2>&1; then
  echo "ERROR: npm not found. Install Node.js LTS to build minified assets."
  exit 1
fi

if [ ! -f "$ROOT/package.json" ]; then
  echo "ERROR: package.json missing at repo root."
  exit 1
fi

# Install minifiers once (node_modules is gitignored).
if [ ! -x "$ROOT/node_modules/.bin/terser" ] || [ ! -x "$ROOT/node_modules/.bin/cleancss" ]; then
  echo "--- npm install (terser, clean-css-cli) ---"
  npm install --no-audit --no-fund
fi

TERSER="$ROOT/node_modules/.bin/terser"
CLEANCSS="$ROOT/node_modules/.bin/cleancss"

PUBLIC_JS=(
  meyvc-shipping-bar
  meyvc-controller
  meyvc-sticky-cart
  meyvc-signals
  meyvc-abandoned-cart-capture
  meyvc-offer-banner
  meyvc-animations
  meyvc-cart-exit-nudge
  meyvc-core
  meyvc-social-proof-toast
  meyvc-ux-detector
  meyvc-exit-intent
  meyvc-popup
  meyvc-public
  meyvc-spin-wheel
)

PUBLIC_CSS=(
  meyvc-popup
  meyvc-boosters
  meyvc-checkout
  meyvc-sticky-cart
  meyvc-classic-cart-checkout
  meyvc-animations
)

echo "--- Minify JS (public/js) ---"
for base in "${PUBLIC_JS[@]}"; do
  src="public/js/${base}.js"
  out="public/js/${base}.min.js"
  if [[ -f "$src" ]]; then
    "$TERSER" "$src" -c -m --comments false -o "$out"
    echo "  $out"
  else
    echo "  [SKIP] missing $src"
  fi
done

echo "--- Minify CSS (public/css) ---"
for base in "${PUBLIC_CSS[@]}"; do
  src="public/css/${base}.css"
  out="public/css/${base}.min.css"
  if [[ -f "$src" ]]; then
    "$CLEANCSS" -O2 --inline none -o "$out" "$src"
    echo "  $out"
  else
    echo "  [SKIP] missing $src"
  fi
done

echo "--- Minify public assets: done ---"
