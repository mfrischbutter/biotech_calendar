#!/usr/bin/env bash
# Post-edit hook: runs type/lint checks on edited files
# Reads tool input from stdin JSON, extracts file_path

set -euo pipefail

INPUT=$(cat)
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

# Skip if no file path
if [ -z "$FILE_PATH" ]; then
  exit 0
fi

# Create marker file so quality-gate knows edits were made
touch /tmp/.claude_biotech_edits_made

# --- PHP files: run Pint (format check) ---
if [[ "$FILE_PATH" == *.php ]]; then
  if [ -f "./vendor/bin/pint" ]; then
    RESULT=$(./vendor/bin/pint --test "$FILE_PATH" 2>&1) || true
    if echo "$RESULT" | grep -q "FAIL"; then
      echo "pint $FILE_PATH:"
      echo "$RESULT" | tail -10
    fi
  fi
  exit 0
fi

# --- TypeScript / Vue files: run vue-tsc ---
if [[ "$FILE_PATH" == *.ts ]] || [[ "$FILE_PATH" == *.vue ]]; then
  RESULT=$(npx vue-tsc --noEmit 2>&1) || true
  # Only output if there are errors
  if [ $? -ne 0 ] || echo "$RESULT" | grep -q "error TS"; then
    echo "vue-tsc errors:"
    echo "$RESULT" | grep "error TS" | head -10
  fi
  exit 0
fi

exit 0
