<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('view-support-tickets');

        $filter = in_array($request->query('status'), ['open', 'resolved', 'all'], true)
            ? $request->query('status')
            : 'open';

        $tickets = SupportTicket::with(['user', 'resolver'])
            ->when($filter !== 'all', fn ($q) => $q->where('status', $filter))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'open'     => SupportTicket::where('status', SupportTicket::STATUS_OPEN)->count(),
            'resolved' => SupportTicket::where('status', SupportTicket::STATUS_RESOLVED)->count(),
        ];

        return view('support-tickets.index', compact('tickets', 'counts', 'filter'));
    }

    public function toggleStatus(SupportTicket $ticket)
    {
        Gate::authorize('manage-support-tickets');

        if ($ticket->isResolved()) {
            $ticket->update([
                'status'      => SupportTicket::STATUS_OPEN,
                'resolved_at' => null,
                'resolved_by' => null,
            ]);
            $message = 'Ticket reopened.';
        } else {
            $ticket->update([
                'status'      => SupportTicket::STATUS_RESOLVED,
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
            ]);
            $message = 'Ticket marked as resolved.';
        }

        return back()->with('success', $message);
    }

    public function updateType(Request $request, SupportTicket $ticket)
    {
        Gate::authorize('manage-support-tickets');

        $type = $request->input('type');
        $ticket->update([
            'type' => array_key_exists($type, SupportTicket::TYPE_LABELS) ? $type : null,
        ]);

        return back()->with('success', 'Ticket tag updated.');
    }

    public function media(SupportTicket $ticket): StreamedResponse
    {
        Gate::authorize('view-support-tickets');

        abort_if(! $ticket->media_path || ! Storage::exists($ticket->media_path), 404);

        return Storage::download($ticket->media_path, $ticket->media_filename);
    }
}
