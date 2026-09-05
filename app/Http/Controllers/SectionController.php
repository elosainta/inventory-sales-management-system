<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSectionRequest;
use App\Http\Requests\StoreSectionTaskRequest;
use App\Http\Requests\UpdateSectionRequest;
use App\Models\Section;
use App\Models\SectionTask;
use Illuminate\Support\Facades\Gate;

class SectionController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-sections');

        $sections = Section::with('tasks')->orderBy('name')->get();

        return view('sections.index', compact('sections'));
    }

    public function store(StoreSectionRequest $request)
    {
        Gate::authorize('manage-sections');

        $data = $request->validated();
        // An unchecked day just never appears in the request, so store the
        // absence explicitly rather than leaving the key missing.
        $data['active_days'] = $data['active_days'] ?? [];

        Section::create($data);

        return back()->with('success', 'Section created.');
    }

    public function update(UpdateSectionRequest $request, Section $section)
    {
        Gate::authorize('manage-sections');

        $data = $request->validated();
        $data['active_days'] = $data['active_days'] ?? [];

        $section->update($data);

        return back()->with('success', 'Section updated.');
    }

    public function destroy(Section $section)
    {
        Gate::authorize('manage-sections');

        $section->delete();

        return back()->with('success', 'Section deleted.');
    }

    public function storeTask(StoreSectionTaskRequest $request, Section $section)
    {
        Gate::authorize('manage-sections');

        $data = $request->validated();

        $maxOrder = $section->tasks()->max('sort_order') ?? -1;

        $section->tasks()->create([
            'title'          => $data['title'],
            'description'    => $data['description'] ?? null,
            'sort_order'     => $maxOrder + 1,
            // Both forms pair the checkbox with a hidden "0", so the key is
            // always present and an unticked box genuinely reads false. The
            // fallback only covers a post that omits the field entirely.
            'requires_photo' => $data['requires_photo'] ?? true,
        ]);

        return back()->with('success', 'Task added.');
    }

    public function updateTask(StoreSectionTaskRequest $request, Section $section, SectionTask $task)
    {
        Gate::authorize('manage-sections');

        abort_if($task->section_id !== $section->id, 404);

        $data = $request->validated();

        $task->update([
            'title'          => $data['title'],
            'description'    => $data['description'] ?? null,
            'requires_photo' => $data['requires_photo'] ?? true,
        ]);

        return back()->with('success', 'Task updated.');
    }

    public function destroyTask(Section $section, SectionTask $task)
    {
        Gate::authorize('manage-sections');

        abort_if($task->section_id !== $section->id, 404);

        $task->delete();

        return back()->with('success', 'Task deleted.');
    }
}
