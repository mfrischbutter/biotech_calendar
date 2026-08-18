<?php

namespace Tests\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Golden-file ("snapshot") assertions for structured data.
 *
 * Snapshots live next to the test suite in tests/__snapshots__ and are committed,
 * so an unintended change to a controller's Inertia payload fails the build with a
 * readable diff instead of slipping through.
 *
 * Regenerate deliberately with:
 *   ./vendor/bin/sail exec -T laravel.test bash -c 'UPDATE_SNAPSHOTS=1 php artisan test'
 * (the env var has to be set inside the container, not on the sail command).
 */
trait MatchesJsonSnapshots
{
    protected function snapshotDirectory(): string
    {
        return base_path('tests/__snapshots__');
    }

    protected function snapshotPath(string $name): string
    {
        $class = Str::afterLast(static::class, '\\');

        return $this->snapshotDirectory()."/{$class}.{$name}.json";
    }

    /**
     * Assert that $actual matches the stored golden file for $name.
     *
     * @param  array<array-key, mixed>  $actual
     */
    protected function assertMatchesJsonSnapshot(array $actual, string $name): void
    {
        $path = $this->snapshotPath($name);
        $json = json_encode(
            $this->normalizeForSnapshot($actual),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        )."\n";

        $this->guardAgainstWallClockInSnapshot($json, $name);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        if (env('UPDATE_SNAPSHOTS') || ! file_exists($path)) {
            file_put_contents($path, $json);

            if (env('UPDATE_SNAPSHOTS')) {
                $this->addToAssertionCount(1);

                return;
            }

            $this->markTestIncomplete("Snapshot created: {$path}. Re-run to verify.");
        }

        $this->assertSame(
            file_get_contents($path),
            $json,
            "Snapshot mismatch for [{$name}].\nIf this change is intended, re-run with UPDATE_SNAPSHOTS=1."
        );
    }

    /**
     * Replace auto-increment ids, which MySQL does not reset on rollback, so the
     * golden file captures *shape and content*, not incidental database state.
     *
     * Timestamps are deliberately NOT normalised: what a payload says the time is
     * is content worth asserting. Any test whose payload carries one must freeze
     * the clock with Carbon::setTestNow() — guardAgainstWallClockInSnapshot()
     * enforces that rather than leaving it to a comment.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected function normalizeForSnapshot(array $data): array
    {
        $volatileIds = ['id', 'company_id', 'user_id', 'appointment_id',
            'contract_id', 'client_id', 'status_id', 'comment_id', 'actor_id', 'parent_id',
            'role_id', 'staff_role_id', 'worker_id', 'created_by'];

        // Only numeric ids are volatile. String ids (e.g. an action slug like
        // "new-client") are meaningful content and must stay in the golden file.
        array_walk_recursive($data, function (&$value, $key) use ($volatileIds) {
            if (in_array($key, $volatileIds, true) && is_int($value)) {
                $value = '<'.$key.'>';

                return;
            }

            // Routes embed the same auto-increment ids, so mask them there too —
            // the route *shape* is what the golden file is asserting.
            if ($key === 'url' && is_string($value)) {
                $value = preg_replace('/\d+/', '<id>', $value);
            }
        });

        return $data;
    }

    /**
     * A golden file that bakes a wall-clock timestamp passes on the day it is
     * written and fails on the next run. Refuse to write or compare one unless
     * the test has pinned "now", so the failure lands on the author.
     */
    private function guardAgainstWallClockInSnapshot(string $json, string $name): void
    {
        if (! preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $json)) {
            return;
        }

        $this->assertTrue(
            Carbon::hasTestNow(),
            "Snapshot [{$name}] contains a timestamp but the test has not frozen the clock. "
            .'Call Carbon::setTestNow() in setUp() (and Carbon::setTestNow(null) in tearDown) '
            .'so the golden file captures a fixed moment instead of the wall clock.'
        );
    }

    /**
     * Pull a subset of keys out of an Inertia response's page props.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    protected function inertiaProps(TestResponse $response, array $keys): array
    {
        $props = $response->viewData('page')['props'] ?? [];

        return Arr::only($props, $keys);
    }
}
