---
name: analyze
description: Run TypeScript type checking and PHP linting, then fix any issues found
user_invocable: true
argument: optional path to analyze (defaults to full project)
---

# /analyze — Run Analysis and Fix

Run code analysis across both PHP and TypeScript/Vue layers.

## Steps

1. **TypeScript check**: Run `npx vue-tsc --noEmit` and capture output
2. **PHP lint**: Run `./vendor/bin/pint --test` to check PHP formatting
3. Parse the results for errors and warnings
4. For each issue:
   - Read the affected file
   - Fix the issue
   - Re-run the relevant check to confirm the fix
5. Run full analysis again to confirm everything is clean
6. Report summary of what was found and fixed
