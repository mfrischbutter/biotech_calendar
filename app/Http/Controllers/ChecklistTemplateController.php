<?php

namespace App\Http\Controllers;

use App\Models\ChecklistTemplate;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChecklistTemplateController extends Controller
{
    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('settings.edit'), 403);

        $validated = $request->validate(self::rules());

        ChecklistTemplate::create([
            ...$validated,
            'sort_order' => (int) ChecklistTemplate::max('sort_order') + 1,
        ]);

        return back();
    }

    public function update(Request $request, ChecklistTemplate $template)
    {
        abort_unless($request->user()->hasPermission('settings.edit'), 403);

        $template->update($request->validate(self::rules()));

        return back();
    }

    public function destroy(Request $request, ChecklistTemplate $template)
    {
        abort_unless($request->user()->hasPermission('settings.edit'), 403);

        $template->delete();

        return back();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['nullable', Rule::in(Contract::KINDS)],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'string', 'max:500'],
        ];
    }
}
