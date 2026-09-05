<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveApplicationRequest;
use App\Models\LeaveApplication;
use App\Models\LeaveAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class LeaveApplicationController extends Controller
{
    public function index()
    {
        Gate::authorize('view-leave');

        $user = auth()->user();

        $query = LeaveApplication::with(['user', 'decider', 'attachments'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at');

        // Staff see only their own applications; the Owner sees everyone's.
        if (! $user->isOwner()) {
            $query->where('user_id', $user->id);
        }

        $applications = $query->paginate(25);

        return view('leave.index', compact('applications'));
    }

    public function store(StoreLeaveApplicationRequest $request)
    {
        Gate::authorize('submit-leave');

        $application = LeaveApplication::create([
            'user_id'    => auth()->id(),
            'start_date' => $request->validated('start_date'),
            'end_date'   => $request->validated('end_date'),
            'reason'     => $request->validated('reason'),
            'status'     => LeaveApplication::STATUS_PENDING,
        ]);

        // Stream each upload straight to disk — never read contents into memory
        // (files can be up to 2 GB; memory_limit would be exceeded).
        foreach ($request->file('attachments', []) as $file) {
            $application->attachments()->create([
                'path'     => $file->store('leave-attachments'),
                'filename' => $file->getClientOriginalName(),
                'mime'     => $file->getClientMimeType(),
                'size'     => $file->getSize(),
            ]);
        }

        return redirect()->route('leave.index')->with('success', 'Leave application submitted.');
    }

    public function approve(LeaveApplication $leaveApplication)
    {
        Gate::authorize('decide-leave');

        return $this->decide($leaveApplication, LeaveApplication::STATUS_APPROVED, 'Leave approved.');
    }

    public function reject(LeaveApplication $leaveApplication)
    {
        Gate::authorize('decide-leave');

        return $this->decide($leaveApplication, LeaveApplication::STATUS_REJECTED, 'Leave rejected.');
    }

    public function attachment(LeaveAttachment $leaveAttachment)
    {
        Gate::authorize('view-leave');

        // Only the applicant and the Owner may download a supporting document.
        $user = $leaveAttachment->leaveApplication->user_id;
        if (! auth()->user()->isOwner() && auth()->id() !== $user) {
            abort(403);
        }

        abort_if(! Storage::exists($leaveAttachment->path), 404);

        return Storage::download($leaveAttachment->path, $leaveAttachment->filename);
    }

    private function decide(LeaveApplication $application, string $status, string $message)
    {
        if (! $application->isPending()) {
            return back()->with('error', 'This application has already been decided.');
        }

        $application->update([
            'status'     => $status,
            'decided_by' => auth()->id(),
            'decided_at' => now(),
        ]);

        return back()->with('success', $message);
    }
}
