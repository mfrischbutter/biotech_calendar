<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('settings.edit'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'in:'.implode(',', Tag::COLORS)],
        ]);

        $maxSort = Tag::max('sort_order') ?? -1;

        Tag::create([
            ...$validated,
            'sort_order' => $maxSort + 1,
        ]);

        return back();
    }

    public function update(Request $request, Tag $tag)
    {
        abort_unless($request->user()->hasPermission('settings.edit'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'in:'.implode(',', Tag::COLORS)],
        ]);

        $tag->update($validated);

        return back();
    }

    public function destroy(Request $request, Tag $tag)
    {
        abort_unless($request->user()->hasPermission('settings.edit'), 403);

        $tag->delete();

        return back();
    }

    public function reorder(Request $request)
    {
        abort_unless($request->user()->hasPermission('settings.edit'), 403);

        $validated = $request->validate([
            'tags' => ['required', 'array'],
            'tags.*.id' => ['required', 'exists:tags,id'],
            'tags.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['tags'] as $item) {
            Tag::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return back();
    }
}
