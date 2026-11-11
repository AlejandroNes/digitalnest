<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Services\ContactMail;
use App\Services\InternalUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class ContactMessageController extends Controller
{
    public function create(): View
    {
        return view('public.contact');
    }

    public function store(StoreContactMessageRequest $request, ContactMail $mail): JsonResponse|RedirectResponse
    {
        try {
            $message = ContactMessage::query()->firstOrCreate(['submission_key' => $request->submissionKey()], $request->safe()->only(['name', 'email', 'phone', 'subject', 'message']));
        } catch (QueryException $exception) {
            Log::error('contact.storage_failed', ['exception' => $exception::class]);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No pudimos guardar tu consulta. Inténtalo más tarde.'], 503);
            }

            return redirect(InternalUrl::absolute($request->validated('return_to') ?: '/contacto'))->with('contact_error', 'No pudimos guardar tu consulta. Inténtalo más tarde.');
        }
        if ($message->wasRecentlyCreated) {
            $mail->notify($message);
        }
        $confirmation = 'Tu consulta se ha guardado correctamente.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $confirmation], 202);
        }

        return redirect(InternalUrl::absolute($request->validated('return_to') ?: '/contacto'))->with('contact_success', $confirmation);
    }
}
