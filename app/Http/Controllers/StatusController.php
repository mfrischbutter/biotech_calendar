<?php

namespace App\Http\Controllers;

use App\Models\Status;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('settings.edit'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'in:'.implode(',', Status::COLORS)],
        ]);

        $maxSort = Status::max('sort_order') ?? -1;

        Status::create([
            ...$validated,
            'sort_order' => $maxSort + 1,
        ]);

        return back();
    }

    public function update(Request $request, Status $status)
    {
        abort_unless($request->user()->hasPermission('settings.edit'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'in:'.implode(',', Status::COLORS)],
        ]);

        $status->update($validated);

        return back();
    }

    public function destroy(Request $request, Status $status)
    {
        abort_unless($request->user()->hasPermission('settings.edit'), 403);

        $status->delete();

        return back();
    }

    public function reorder(Request $request)
    {
        abort_unless($request->user()->hasPermission('settings.edit'), 403);

        $validated = $request->validate([
            'statuses' => ['required', 'array'],
            'statuses.*.id' => ['required', 'exists:statuses,id'],
            'statuses.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['statuses'] as $item) {
            Status::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return back();
    }
}
