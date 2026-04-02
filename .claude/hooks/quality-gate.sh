#!/usr/bin/env bash
# Stop hook: runs full quality gate when Claude finishes after making edits
# Only runs if edits were made (marker file exists)

set -euo pipefail

# Check if edits were made this session
if [ ! -f /tmp/.claude_biotech_edits_made ]; then
  exit 0
fi

# Clean up marker
rm -f /tmp/.claude_biotech_edits_made

echo "Running quality gate..."
echo ""

# 1. TypeScript type checking
echo "=== TypeScript Check ==="
TSC_RESULT=$(npx vue-tsc --noEmit 2>&1) || true
if echo "$TSC_RESULT" | grep -q "error TS"; then
  echo "$TSC_RESULT" | grep "error TS" | head -15
  TSC_COUNT=$(echo "$TSC_RESULT" | grep -c "error TS" || true)
  echo "  ($TSC_COUNT type errors)"
else
  echo "  No type errors"
fi
echo ""

# 2. PHP formatting check
echo "=== PHP Formatting (Pint) ==="
if [ -f "./vendor/bin/pint" ]; then
  PINT_RESULT=$(./vendor/bin/pint --test 2>&1) || true
  if echo "$PINT_RESULT" | grep -q "FAIL"; then
    echo "$PINT_RESULT" | grep "FAIL" | head -10
    echo "  Run ./vendor/bin/pint to fix"
  else
    echo "  All PHP files formatted correctly"
  fi
else
  echo "  Pint not available (run composer install)"
fi
echo ""

# 3. Check for large files (>300 lines) in app/ and resources/js/
echo "=== File Size Check ==="
LARGE_FILES=""

# PHP files
for f in $(find app/ -name '*.php' 2>/dev/null); do
  LINES=$(wc -l < "$f")
  if [ "$LINES" -gt 300 ]; then
    LARGE_FILES="$LARGE_FILES\n  $f: $LINES lines"
  fi
done

# Vue/TS files
for f in $(find resources/js/Pages resources/js/Layouts resources/js/lib -name '*.vue' -o -name '*.ts' 2>/dev/null); do
  LINES=$(wc -l < "$f")
  if [ "$LINES" -gt 300 ]; then
    LARGE_FILES="$LARGE_FILES\n  $f: $LINES lines"
  fi
done

if [ -n "$LARGE_FILES" ]; then
  echo "  Files exceeding 300 lines (consider splitting):"
  echo -e "$LARGE_FILES"
else
  echo "  All files within size limits"
fi
echo ""

# 4. Check for 'any' type usage in TypeScript
echo "=== TypeScript 'any' Check ==="
ANY_USAGE=$(grep -rn ': any' resources/js/Pages/ resources/js/lib/ resources/js/Layouts/ --include='*.ts' --include='*.vue' 2>/dev/null | grep -v 'node_modules' | grep -v '// eslint-disable' || true)
if [ -n "$ANY_USAGE" ]; then
  echo "  'any' types found (consider using proper types):"
  echo "$ANY_USAGE" | head -10
else
  echo "  No 'any' types found"
fi
echo ""

# 5. Check for missing translations (hardcoded strings in templates)
echo "=== Translation Check ==="
# Look for common patterns of hardcoded German strings in templates (rough heuristic)
HARDCODED=$(grep -rn '>\s*[A-ZÄÖÜ][a-zäöüß]\{3,\}' resources/js/Pages/ --include='*.vue' 2>/dev/null | grep -v 'v-for\|v-if\|:key\|class=\|import\|//\|<!--\|props\.\|form\.\|\.value\|headerTitle\|day\.\|appt\.\|config\.\|viewLabels\|recurrenceLabels\|t(' | head -5 || true)
if [ -n "$HARDCODED" ]; then
  echo "  Possible hardcoded strings (check if t() is needed):"
  echo "$HARDCODED"
else
  echo "  Looks good (spot-check recommended)"
fi

exit 0
