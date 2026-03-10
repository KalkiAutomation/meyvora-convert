#!/usr/bin/env bash
# Build a client-ready distribution zip: staging dir with only runtime files.
# Output: dist/meyvora-convert.zip with root entry meyvora-convert/
#
# INCLUDE (copied into staging):
#   meyvora-convert.php, readme.txt, uninstall.php
#   includes/, admin/, public/, templates/, languages/, assets/
#   blocks/cart-checkout-extension/build/*   (build assets only)
#   blocks/campaign/*
#
# EXCLUDE (dev-only, never copied):
#   build-zip.sh, scripts/, dist/, .release/, .git*
#   node_modules (anywhere)
#   blocks/cart-checkout-extension/src/
#   blocks/cart-checkout-extension/package.json, package-lock.json
#   blocks/cart-checkout-extension/webpack.config.js, *.config.js
#   assets/README.md
#   __MACOSX, .DS_Store, ._*
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$SCRIPT_DIR"
STAGING_DIR="${PLUGIN_ROOT}/.release/meyvora-convert"
ZIP_NAME="meyvora-convert.zip"
DIST_DIR="${PLUGIN_ROOT}/dist"

echo "=== Meyvora Convert build-zip ==="
echo "Plugin root: $PLUGIN_ROOT"
echo "Staging:     $STAGING_DIR"
echo ""

# 1) Clean staging and create structure
rm -rf "${PLUGIN_ROOT}/.release"
mkdir -p "$STAGING_DIR"
cd "$PLUGIN_ROOT"

# 2) Copy ONLY runtime plugin files (see INCLUDE/EXCLUDE at top of script)
[ -f "$PLUGIN_ROOT/meyvora-convert.php" ] && cp "$PLUGIN_ROOT/meyvora-convert.php" "$STAGING_DIR/"
[ -f "$PLUGIN_ROOT/readme.txt" ]      && cp "$PLUGIN_ROOT/readme.txt" "$STAGING_DIR/"
[ -f "$PLUGIN_ROOT/uninstall.php" ]   && cp "$PLUGIN_ROOT/uninstall.php" "$STAGING_DIR/"

for dir in includes admin public templates languages assets; do
  if [ -d "$PLUGIN_ROOT/$dir" ]; then
    cp -R "$PLUGIN_ROOT/$dir" "$STAGING_DIR/"
  fi
done

# blocks/cart-checkout-extension: ONLY build/ (exclude src/, package.json, package-lock.json, webpack.config.js)
if [ -d "$PLUGIN_ROOT/blocks/cart-checkout-extension/build" ]; then
  mkdir -p "$STAGING_DIR/blocks/cart-checkout-extension/build"
  cp "$PLUGIN_ROOT/blocks/cart-checkout-extension/build/"* "$STAGING_DIR/blocks/cart-checkout-extension/build/" 2>/dev/null || true
fi

# blocks/campaign/* (block.json, index.js)
if [ -d "$PLUGIN_ROOT/blocks/campaign" ]; then
  mkdir -p "$STAGING_DIR/blocks/campaign"
  cp "$PLUGIN_ROOT/blocks/campaign/"* "$STAGING_DIR/blocks/campaign/" 2>/dev/null || true
fi

# 3) Verify screenshots referenced in readme.txt are present
echo "--- Screenshot check ---"
SCREENSHOTS_MISSING=0
for i in 1 2 3 4 5 6; do
  FOUND=""
  for ext in png jpg jpeg; do
    if [ -f "$STAGING_DIR/assets/screenshot-${i}.${ext}" ]; then
      FOUND="screenshot-${i}.${ext}"
      break
    fi
  done
  if [ -n "$FOUND" ]; then
    echo "  [OK] assets/$FOUND"
  else
    echo "  [WARN] assets/screenshot-${i}.png missing"
    SCREENSHOTS_MISSING=$((SCREENSHOTS_MISSING + 1))
  fi
done
if [ "$SCREENSHOTS_MISSING" -gt 0 ]; then
  echo "  $SCREENSHOTS_MISSING screenshot(s) missing — add them to assets/ before final release."
else
  echo "  All 6 screenshots present."
fi
echo ""

# 4) Remove dev-only files from staging (assets/README.md is for contributors, not distribution)
rm -f "$STAGING_DIR/assets/README.md"

# 5) Delete Mac junk inside staging
echo "--- Removing Mac junk from staging ---"
find "$STAGING_DIR" -type d -name '__MACOSX' -exec rm -rf {} + 2>/dev/null || true
find "$STAGING_DIR" -type f -name '._*' -delete 2>/dev/null || true
# Remove any __MACOSX that might have been left (find -exec rm can leave empty parents)
find "$STAGING_DIR" -type d -name '__MACOSX' 2>/dev/null | while read -r d; do rm -rf "$d"; done
echo "Done."
echo ""

# 6) Create zip from .release/ so zip root is meyvora-convert/
mkdir -p "$DIST_DIR"
rm -f "$DIST_DIR/$ZIP_NAME"
cd "${PLUGIN_ROOT}/.release"
zip -r "${DIST_DIR}/${ZIP_NAME}" meyvora-convert
cd "$PLUGIN_ROOT"

echo "Built: $DIST_DIR/$ZIP_NAME"
echo ""

# 7) ZIP CONTENTS SUMMARY
echo "--- ZIP CONTENTS SUMMARY ---"
echo "Top-level list (first 50 entries):"
unzip -l "$DIST_DIR/$ZIP_NAME" | head -n 55
echo ""
# Check only zip entry names (last column), not the archive path; skip header (3 lines) and total line (NF<4)
ZIP_LIST="$(unzip -l "$DIST_DIR/$ZIP_NAME" | awk 'NR>3 && NF>=4 {print $NF}')"
echo "Checks (on zip entry names only):"
if echo "$ZIP_LIST" | grep -q '__MACOSX'; then
  echo "  [FAIL] __MACOSX is present in zip"
else
  echo "  [OK] __MACOSX not present"
fi
if echo "$ZIP_LIST" | grep -q 'dist/'; then
  echo "  [FAIL] dist/ is present in zip"
else
  echo "  [OK] dist/ not present"
fi
if echo "$ZIP_LIST" | grep -q '\.zip$'; then
  echo "  [FAIL] .zip file nested inside zip"
else
  echo "  [OK] no nested .zip"
fi
# Dev-only files must not appear in production zip
if echo "$ZIP_LIST" | grep -qE 'build-zip\.sh|webpack\.config\.js|cart-checkout-extension/src/|package\.json|package-lock\.json'; then
  echo "  [FAIL] dev-only file(s) present in zip"
else
  echo "  [OK] no dev-only files (build-zip.sh, webpack.config.js, src/, package*.json)"
fi
echo ""
echo "Total files and size:"
unzip -l "$DIST_DIR/$ZIP_NAME" | tail -1
echo ""
echo "=== Done: $DIST_DIR/$ZIP_NAME ==="
