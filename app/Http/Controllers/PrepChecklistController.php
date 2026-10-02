<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskCheckRequest;
use App\Models\Section;
use App\Models\SectionCheck;
use App\Models\SectionTask;
use Illuminate\Http\Request;
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
            ->where('checked_date', SectionCheck::kitchenToday())
            ->get()
            ->keyBy('section_task_id');

        return view('prep.index', compact('sections', 'taskChecks'));
    }

    // Managers — the same day, read-only, with progress per section.
    public function overview()
    {
        Gate::authorize('overview-checklist');

        $sections = Section::with(['tasks.checks' => function ($q) {
            $q->where('checked_date', SectionCheck::kitchenToday())->with('user');
        }])->get();

        // Mark unread database notifications as read
        auth()->user()->unreadNotifications
            ->where('type', \App\Notifications\ChecklistReminder::class)
            ->each->markAsRead();

        return view('prep.overview', compact('sections'));
    }

    /**
     * Who did the prep, on any past day — and open to everyone.
     *
     * The overview above is the manager's read of *today*: what is still
     * outstanding and whose name is against it right now. This is the record
     * instead — one day at a time, every section, who ticked what and when.
     * It sits on `view-checklist` rather than `overview-checklist` because
     * the point of it is that the kitchen can see its own work: a chef asking
     * "did anyone close the fryer on Sunday" should not have to ask a manager.
     */
    public function history(Request $request)
    {
        Gate::authorize('view-checklist');

        // The date arrives on the query string, so it has to survive anything
        // typed there: an unparseable one reads as today rather than a 500,
        // and a future one is clamped, since there is nothing after today.
        $date = rescue(fn () => $request->date('date') ?? SectionCheck::kitchenToday(), SectionCheck::kitchenToday(), false);
        $date = $date->startOfDay()->min(SectionCheck::kitchenToday());

        $sections = Section::with(['tasks.checks' => fn ($q) => $q->where('checked_date', $date)->with('user')])
            ->orderBy('name')
            ->get();

        // The days that actually hold work, newest first, so the page can be
        // browsed without guessing dates into the box.
        $recentDays = SectionCheck::query()
            ->select('checked_date')
            ->distinct()
            ->orderByDesc('checked_date')
            ->limit(14)
            ->pluck('checked_date');

        return view('prep.history', compact('sections', 'date', 'recentDays'));
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
            ->where('checked_date', SectionCheck::kitchenToday())
            ->first();

        if ($existing) {
            // Re-doing someone else's tick moves the name to you: the row
            // should say who last stood in front of it.
            $attributes = ['user_id' => auth()->id()];

            // Guarded: a task that needs no photo has a null path, and
            // Storage::delete(null) is a TypeError.
            if ($path && $existing->photo_path) {
                Storage::delete($existing->photo_path);
            }
            if ($path) {
                $attributes['photo_path'] = $path;
            }

            $existing->update($attributes);
        } else {
            SectionCheck::create([
                'section_task_id' => $task->id,
                'user_id'         => auth()->id(),
                'checked_date'    => SectionCheck::kitchenToday(),
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
