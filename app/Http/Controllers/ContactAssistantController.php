<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactAssistantRequest;
use App\Models\ContactAssistantSetting;
use App\Services\ContactAssistant;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContactAssistantController extends Controller
{
    public function show(Request $request, ContactAssistant $assistant): JsonResponse
    {
        $configuration = $assistant->configuration();
        if ($request->boolean('fields') && $configuration['enabled']) {
            $configuration['fields_html'] = view('components.contact-assistant-fields', compact('configuration'))->render();
        }

        return response()->json($configuration);
    }

    public function store(StoreContactAssistantRequest $request, ContactAssistant $assistant): JsonResponse
    {
        try {
            $message = $assistant->store($request->validated());
        } catch (QueryException $exception) {
            Log::error('contact_assistant.storage_failed', ['exception' => $exception::class]);

            return response()->json(['message' => 'No pudimos guardar tu consulta. Inténtalo más tarde.'], 503);
        }

        return response()->json([
            'message' => ContactAssistantSetting::current()->final_message,
            'whatsapp_status' => $message->whatsapp_status,
            'whatsapp_url' => $assistant->whatsappUrl($message),
        ], 202);
    }
}
