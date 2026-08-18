<?php

namespace Tests\Concerns;

use Illuminate\Support\Arr;
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
     * Replace values that legitimately vary between runs (ids, timestamps) so the
     * golden file captures *shape and content*, not incidental database state.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected function normalizeForSnapshot(array $data): array
    {
        $volatile = ['id', 'created_at', 'updated_at', 'company_id', 'user_id', 'appointment_id',
            'contract_id', 'client_id', 'status_id', 'comment_id', 'actor_id', 'parent_id',
            'role_id', 'staff_role_id', 'worker_id'];

        // Only numeric ids are volatile. String ids (e.g. an action slug like
        // "new-client") are meaningful content and must stay in the golden file.
        array_walk_recursive($data, function (&$value, $key) use ($volatile) {
            if (in_array($key, $volatile, true) && is_int($value)) {
                $value = '<'.$key.'>';
            }
        });

        return $data;
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
