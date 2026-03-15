#!/usr/bin/env bash
# Production release: build blocks, create dist/meyvora-convert.zip (clean, installable), print ZIP contents summary.
#
# ZIP MUST NOT contain: __MACOSX, .DS_Store, ._*, nested .zip, dot-folders (.release etc.), node_modules anywhere.
# ZIP MUST include: blocks/cart-checkout-extension/build/index.js + index.asset.php, all plugin runtime PHP/CSS/JS, assets/screenshot-*.png.
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_NAME="$(basename "$PLUGIN_ROOT")"
ZIP_NAME="meyvora-convert.zip"
DIST_DIR="$PLUGIN_ROOT/dist"
ZIP_PATH="$DIST_DIR/$ZIP_NAME"
BLOCKS_DIR="$PLUGIN_ROOT/blocks/cart-checkout-extension"

echo "=== Meyvora Convert Release ==="
echo "Plugin root: $PLUGIN_ROOT"
echo ""

# 1) Build blocks bundle
echo "--- 1) Building blocks (cart-checkout-extension) ---"
if [ ! -d "$BLOCKS_DIR" ]; then
  echo "Error: $BLOCKS_DIR not found."
  exit 1
fi
cd "$BLOCKS_DIR"
if command -v npm >/dev/null 2>&1; then
  npm ci 2>/dev/null || npm install
  npm run build
else
  echo "Error: npm not found."
  exit 1
fi

# Verify required build outputs
if [ ! -f "$BLOCKS_DIR/build/index.js" ]; then
  echo "Error: blocks/cart-checkout-extension/build/index.js not found after build."
  exit 1
fi
if [ ! -f "$BLOCKS_DIR/build/index.asset.php" ]; then
  echo "Error: blocks/cart-checkout-extension/build/index.asset.php not found after build."
  exit 1
fi
echo "Blocks build OK: build/index.js, build/index.asset.php"
echo ""

# 2) Create dist/meyvora-convert.zip with explicit excludes (no __MACOSX, .DS_Store, ._*, .zip, dot-folders, node_modules)
echo "--- 2) Creating $ZIP_NAME ---"
mkdir -p "$DIST_DIR"
rm -f "$ZIP_PATH"
cd "$(dirname "$PLUGIN_ROOT")"

# Exclude: macOS junk, nested zips, dot-folders, node_modules anywhere, dist/scripts/tests/docs and dev-only files
# COPYFILE_DISABLE=1 prevents macOS zip from adding __MACOSX and resource-fork files
COPYFILE_DISABLE=1 zip -r "$ZIP_PATH" "$PLUGIN_NAME" \
  -x "*__MACOSX*" \
  -x "*.DS_Store" \
  -x "*._*" \
  -x "*.zip" \
  -x "*/.release/*" \
  -x "*/.release*" \
  -x "*/.git/*" \
  -x "*/.git*" \
  -x "*/.github/*" \
  -x "*/.vscode/*" \
  -x "*/.idea/*" \
  -x "*/.cursor/*" \
  -x "*/.phpunit*" \
  -x "*/.eslintrc*" \
  -x "*/.editorconfig" \
  -x "*/.gitignore" \
  -x "*node_modules*" \
  -x "$PLUGIN_NAME/dist/*" \
  -x "$PLUGIN_NAME/scripts/*" \
  -x "$PLUGIN_NAME/tests/*" \
  -x "$PLUGIN_NAME/docs/*" \
  -x "$PLUGIN_NAME/package-lock.json" \
  -x "$PLUGIN_NAME/phpunit.xml*" \
  -x "$PLUGIN_NAME/blocks/cart-checkout-extension/package-lock.json" \
  -x "$PLUGIN_NAME/blocks/cart-checkout-extension/webpack.config.js" \
  -x "$PLUGIN_NAME/blocks/cart-checkout-extension/*.config.js" \
  -x "$PLUGIN_NAME/assets/README.md"

echo "Created: $ZIP_PATH"
echo ""

# 3) Verify screenshots referenced in readme.txt
echo "--- 3) Screenshot check ---"
SCREENSHOTS_MISSING=0
for i in 1 2 3 4 5 6; do
  if unzip -l "$ZIP_PATH" | grep -q "assets/screenshot-${i}\.\(png\|jpg\|jpeg\)"; then
    echo "  [OK] screenshot-${i}"
  else
    echo "  [WARN] assets/screenshot-${i}.png missing from zip"
    SCREENSHOTS_MISSING=$((SCREENSHOTS_MISSING + 1))
  fi
done
if [ "$SCREENSHOTS_MISSING" -gt 0 ]; then
  echo "  $SCREENSHOTS_MISSING screenshot(s) missing — add them to assets/ before final release."
else
  echo "  All 6 screenshots present in zip."
fi
echo ""

# 4) ZIP CONTENTS SUMMARY
echo "--- 4) ZIP CONTENTS SUMMARY ---"
echo "Path: $ZIP_PATH"
echo ""

# Ensure required files are present
if ! unzip -l "$ZIP_PATH" | grep -q "blocks/cart-checkout-extension/build/index.js"; then
  echo "WARNING: blocks/cart-checkout-extension/build/index.js not found in zip."
fi
if ! unzip -l "$ZIP_PATH" | grep -q "blocks/cart-checkout-extension/build/index.asset.php"; then
  echo "WARNING: blocks/cart-checkout-extension/build/index.asset.php not found in zip."
fi

# Top-level tree (plugin name + first path segment)
echo "Top-level structure:"
unzip -l "$ZIP_PATH" | awk '
  /^----/ || /^Length/ || /^[[:space:]]*$/ { next }
  {
    path = $NF
    n = split(path, a, "/")
    if (n >= 1 && a[1] != "") {
      if (n == 1) { key = a[1] }
      else { key = a[1] "/" a[2] }
      seen[key] = 1
    }
  }
  END {
    for (k in seen) print "  " k (index(k, "/") ? "" : "/")
  }
' | sort -u

echo ""
echo "File count and total size:"
unzip -l "$ZIP_PATH" | tail -1

echo ""
echo "Sample of included runtime paths (PHP/CSS/JS):"
unzip -l "$ZIP_PATH" | awk -v p="$PLUGIN_NAME" '
  $NF ~ /\.(php|css|js)$/ && index($NF, p) == 1 {
    path = $NF
    sub(p "/", "", path)
    if (path != "" && ++n <= 20) print "  " path
  }
' || true

echo ""
echo "=== Release complete: $ZIP_PATH ==="
