<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskCheckRequest;
use App\Models\Section;
use App\Models\SectionCheck;
use App\Models\SectionTask;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * The prep checklist is shared work. A task belongs to its section, not to a
 * person: anyone in the kitchen may tick any task, and the account that did it
 * is stamped on the row so the Owner can see who. Sections used to be assigned
 * one chef each, which meant an unassigned section could be worked by nobody
 * and a chef with no section signed in to an empty page.
 *
 * One consequence worth holding onto: a check is looked up by (task, date)
 * rather than (task, user, date). Ticking a task someone already ticked today
 * updates that one row and moves the name to you — it does not add a second.
 *
 * There was a second, parallel mechanism here until 1.10.55 — a per-appliance
 * photo check with its own model, routes, table and a hardcoded list of
 * appliances per section. It recorded nothing in its entire life: for four
 * months its section names matched none that exist, and once that was fixed it
 * turned out to duplicate prep tasks the kitchen already had, asking a chef to
 * photograph the same chiller twice. Deleted rather than left dormant. Tasks
 * are the only thing a chef ticks.
 */
class PrepChecklistController extends Controller
{
    // Every section, for anyone in the kitchen.
    public function index()
    {
        Gate::authorize('view-checklist');

        $sections = Section::with('tasks')->orderBy('name')->get();

        // Keyed by what was done, not by who did it — one row per task per day
        // whoever ticked it. `user` is eager-loaded because every done row
        // renders that name.
        $taskChecks = SectionCheck::with('user')
            ->whereIn('section_task_id', $sections->flatMap->tasks->pluck('id'))
            ->where('checked_date', today())
            ->get()
            ->keyBy('section_task_id');

        return view('prep.index', compact('sections', 'taskChecks'));
    }

    // Managers — the same day, read-only, with progress per section.
    public function overview()
    {
        Gate::authorize('overview-checklist');

        $sections = Section::with(['tasks.checks' => function ($q) {
            $q->where('checked_date', today())->with('user');
        }])->get();

        // Mark unread database notifications as read
        auth()->user()->unreadNotifications
            ->where('type', \App\Notifications\ChecklistReminder::class)
            ->each->markAsRead();

        return view('prep.overview', compact('sections'));
    }

    // Tick a prep task, with a photo where the task demands one.
    public function storeTaskCheck(StoreTaskCheckRequest $request)
    {
        Gate::authorize('view-checklist');

        // Any task in any section — the task id is all the authority needed now
        // that no section belongs to a person. findOrFail is the 404.
        $task = SectionTask::findOrFail($request->input('task_id'));

        $path = $request->hasFile('photo')
            ? $request->file('photo')->store('task-checks')
            : null;

        $existing = SectionCheck::where('section_task_id', $task->id)
            ->where('checked_date', today())
            ->first();

        if ($existing) {
            // Re-doing someone else's tick moves the name to you: the row
            // should say who last stood in front of it.
            $attributes = ['user_id' => auth()->id()];

            if ($path) {
                // Guarded: a task that needs no photo has a null path, and
                // Storage::delete(null) is a TypeError.
                if ($existing->photo_path) {
                    Storage::delete($existing->photo_path);
                }
                $attributes['photo_path'] = $path;
            }

            $existing->update($attributes);
        } else {
            SectionCheck::create([
                'section_task_id' => $task->id,
                'user_id'         => auth()->id(),
                'checked_date'    => today(),
                'photo_path'      => $path,
            ]);
        }

        return back()->with('success', __('Task photo uploaded.'));
    }

    public function taskCheckPhoto(SectionCheck $sectionCheck)
    {
        // Checks are shared records, so anyone who can open the checklist can
        // see its proof photos. `view-checklist` is a superset of
        // `overview-checklist` (managers are not admins), so this one gate
        // covers both the chefs doing the work and the managers reviewing it.
        Gate::authorize('view-checklist');

        abort_if(! $sectionCheck->photo_path || ! Storage::exists($sectionCheck->photo_path), 404);

        return Storage::response($sectionCheck->photo_path);
    }
}
