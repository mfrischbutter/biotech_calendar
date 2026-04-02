---
name: test
description: Run PHPUnit tests with optional filter
user_invocable: true
argument: optional test path or filter (e.g., "Feature/ClientTest" or "--filter=test_can_create")
---

# /test — Run Tests

Run PHPUnit tests and report results.

## Steps

1. Run `php artisan test $ARGUMENTS`
   - If no arguments, run all tests: `php artisan test`
   - If a path is given: `php artisan test --filter=$ARGUMENTS` or `php artisan test tests/$ARGUMENTS`
2. Parse results for failures
3. For failures: read the test file and the source file, diagnose the issue
4. Report summary: passed, failed, skipped counts
5. If tests fail due to code issues (not test issues), fix the code and re-run
