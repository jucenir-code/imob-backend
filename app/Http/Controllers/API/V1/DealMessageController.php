<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deal\DealMessageStoreRequest;
use App\Http\Resources\DealMessageResource;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DealMessageController extends Controller
{
    public function index(Deal $deal)
    {
        $this->authorize('view', $deal);

        $messages = $deal->messages()
            ->with(['author', 'attachments'])
            ->orderBy('created_at')
            ->paginate(request()->integer('per_page', 50));

        return DealMessageResource::collection($messages);
    }

    public function store(DealMessageStoreRequest $request, Deal $deal): JsonResponse
    {
        $this->authorize('message', $deal);

        $message = DB::transaction(function () use ($request, $deal) {
            $message = $deal->messages()->create([
                'user_id' => $request->user()->id,
                'message' => trim((string) $request->validated('message', '')),
            ]);

            foreach ($request->file('attachments', []) as $index => $attachment) {
                $attachmentMimeType = $attachment->getMimeType();
                $attachmentType = Str::startsWith((string) $attachmentMimeType, 'image/') ? 'image' : 'audio';
                $storedPath = $attachment->store('deal-messages', 'public');

                $message->attachments()->create([
                    'type' => $attachmentType,
                    'url' => Storage::disk('public')->url($storedPath),
                    'mime_type' => $attachmentMimeType,
                    'original_name' => $attachment->getClientOriginalName(),
                    'duration_ms' => $attachmentType === 'audio'
                        ? $request->validated('attachment_duration_ms')
                        : null,
                    'position' => $index,
                ]);
            }

            return $message;
        });

        $message->load(['author', 'attachments']);

        return (new DealMessageResource($message))->response()->setStatusCode(201);
    }
}
