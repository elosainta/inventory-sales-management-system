<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportFeedbackPdfRequest;
use App\Http\Requests\StoreFeedbackRequest;
use App\Models\FeedbackAttachment;
use App\Models\FeedbackEntry;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class FeedbackController extends Controller
{
    public function index()
    {
        Gate::authorize('view-feedback');

        $user = auth()->user();

        if ($user->isOwner()) {
            return $this->ownerIndex();
        }

        // Staff view: feedback received, always anonymous. Sender names are
        // never passed to this view.
        $received = FeedbackEntry::with('attachments')
            ->where('to_user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $monthAverage = FeedbackEntry::where('to_user_id', $user->id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->get()
            ->avg(fn ($entry) => $entry->averageRating());

        // Staff rate each other: junior + head chefs, never self or demo accounts.
        $teammates = User::whereIn('role', [User::ROLE_JUNIOR_CHEF, User::ROLE_HEAD_CHEF])
            ->where('id', '!=', $user->id)
            ->where('is_demo', false)
            ->orderBy('name')
            ->get();

        return view('feedback.index', [
            'received'     => $received,
            'monthAverage' => $monthAverage ? round($monthAverage, 1) : null,
            'teammates'    => $teammates,
            'ownerData'    => null,
        ]);
    }

    public function store(StoreFeedbackRequest $request)
    {
        Gate::authorize('submit-feedback');

        $entry = FeedbackEntry::create([
            'from_user_id'         => auth()->id(),
            'to_user_id'           => $request->validated('to_user_id'),
            'rating_cleanliness'   => $request->validated('rating_cleanliness'),
            'rating_safety'        => $request->validated('rating_safety'),
            'rating_organisation'  => $request->validated('rating_organisation'),
            'rating_teamwork'      => $request->validated('rating_teamwork'),
            'rating_communication' => $request->validated('rating_communication'),
            'comment'              => $request->validated('comment'),
        ]);

        // Stream uploads straight to disk — files can be large, never read
        // their contents into memory.
        foreach ($request->file('attachments', []) as $file) {
            $entry->attachments()->create([
                'path'     => $file->store('feedback-attachments'),
                'filename' => $file->getClientOriginalName(),
                'mime'     => $file->getClientMimeType(),
                'size'     => $file->getSize(),
            ]);
        }

        return redirect()->route('feedback.index')->with('success', 'Feedback sent anonymously.');
    }

    public function attachment(FeedbackAttachment $feedbackAttachment)
    {
        Gate::authorize('view-feedback');

        // Sender, recipient, and the Owner may download.
        $entry = $feedbackAttachment->feedbackEntry;
        $user  = auth()->user();
        if (! $user->isOwner() && ! in_array($user->id, [$entry->from_user_id, $entry->to_user_id], true)) {
            abort(403);
        }

        abort_if(! Storage::exists($feedbackAttachment->path), 404);

        return Storage::download($feedbackAttachment->path, $feedbackAttachment->filename);
    }

    public function exportPdf(ExportFeedbackPdfRequest $request)
    {
        Gate::authorize('export-feedback-pdf');

        $month = $request->validated('month') ?? now()->format('Y-m');
        [$entries, $monthLabel, $questionAverages, $perPerson] = FeedbackEntry::monthReport($month);

        $pdf = Pdf::loadView('pdfs.feedback', compact('entries', 'monthLabel', 'questionAverages', 'perPerson'));

        return $pdf->download('feedback-' . $month . '.pdf');
    }

    private function ownerIndex()
    {
        $month = request()->query('month');
        if (! is_string($month) || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        [$entries, $monthLabel, $questionAverages, $perPerson] = FeedbackEntry::monthReport($month);

        // Weekly averages within the month (calendar week of the month, 1-5).
        $weekly = $entries->groupBy(fn ($entry) => (int) ceil($entry->created_at->day / 7))
            ->map(fn ($group) => round($group->avg(fn ($entry) => $entry->averageRating()), 1))
            ->sortKeys();

        return view('feedback.index', [
            'received'     => null,
            'monthAverage' => null,
            'teammates'    => collect(),
            'ownerData'    => [
                'month'            => $month,
                'monthLabel'       => $monthLabel,
                'entries'          => $entries,
                'questionAverages' => $questionAverages,
                'perPerson'        => $perPerson,
                'weekly'           => $weekly,
                'kitchenAverage'   => $entries->isEmpty() ? null : round($entries->avg(fn ($entry) => $entry->averageRating()), 1),
            ],
        ]);
    }

}
