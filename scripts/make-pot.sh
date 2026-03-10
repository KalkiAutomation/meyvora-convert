#!/bin/bash
# Regenerate the .pot translation file.
# Requires WP-CLI: https://wp-cli.org
# Run: chmod +x scripts/make-pot.sh && bash scripts/make-pot.sh
# Usage: bash scripts/make-pot.sh (run from plugin root)
wp i18n make-pot . languages/meyvora-convert.pot \
  --domain=meyvora-convert \
  --exclude=node_modules,vendor,tests,scripts \
  --headers='{"Report-Msgid-Bugs-To":"https://wordpress.org/support/plugin/meyvora-convert","Language-Team":"https://translate.wordpress.org/projects/wp-plugins/meyvora-convert/"}'
echo "Done. POT file regenerated at languages/meyvora-convert.pot"
