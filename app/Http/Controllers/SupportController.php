<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupportRequest;
use App\Mail\SupportReport;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SupportController extends Controller
{
    public function index()
    {
        Gate::authorize('submit-support');

        return view('support.index');
    }

    public function store(StoreSupportRequest $request)
    {
        Gate::authorize('submit-support');

        $user = auth()->user();

        $mediaContent  = null;
        $mediaFilename = null;
        $mediaMime     = null;
        $mediaPath     = null;

        if ($request->hasFile('media')) {
            $file          = $request->file('media');
            $mediaContent  = file_get_contents($file->getRealPath());
            $mediaFilename = $file->getClientOriginalName();
            $mediaMime     = $file->getMimeType();
            // Persist a copy on the private disk so admins can review it later.
            $mediaPath     = $file->store('support-tickets');
        }

        // Persist the ticket so an admin can track and resolve it in-app.
        $ticket = SupportTicket::create([
            'user_id'        => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'role'           => $user->role,
            'description'    => $request->input('description'),
            'media_path'     => $mediaPath,
            'media_filename' => $mediaFilename,
            'media_mime'     => $mediaMime,
            'status'         => SupportTicket::STATUS_OPEN,
        ]);

        // Human-friendly unique reference based on the row id (e.g. FK-00042).
        $ticket->update([
            'ticket_number' => 'FK-' . str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT),
        ]);

        try {
            Mail::to(config('services.support.email'))->send(new SupportReport(
                senderName:    $user->name,
                senderEmail:   $user->email,
                senderRole:    str_replace('_', ' ', $user->role),
                description:   $request->input('description'),
                mediaContent:  $mediaContent,
                mediaFilename: $mediaFilename,
                mediaMime:     $mediaMime,
            ));
        } catch (\Throwable $e) {
            Log::error('Support report email failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return redirect()->route('support.index')->with(
                'error',
                'Sorry, your report could not be sent right now. Please try again, or reduce the attachment size.'
            );
        }

        return redirect()->route('support.index')->with('success', 'Your report has been sent to the developer. Thank you!');
    }
}
