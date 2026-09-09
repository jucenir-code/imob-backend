<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deal\DealStoreRequest;
use App\Http\Requests\Deal\DealUpdateRequest;
use App\Http\Resources\DealResource;
use App\Models\Deal;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Deal::query()
            ->with(['property.group', 'sellerAgent', 'buyerAgent'])
            ->accessibleBy($user);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($propertyId = $request->integer('property_id')) {
            $query->where('property_id', $propertyId);
        }

        if ($agentId = $request->integer('agent_id')) {
            $query->where(function ($q) use ($agentId) {
                $q->where('seller_agent_id', $agentId)
                    ->orWhere('buyer_agent_id', $agentId);
            });
        }

        $deals = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return DealResource::collection($deals);
    }

    public function store(DealStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $property = Property::query()->with('group')->findOrFail($request->validated('property_id'));
        $sellerAgentId = $property->owner_id ?: $property->group?->owner_id;

        $this->authorize('create', [Deal::class, $property]);

        if ($sellerAgentId === $user->id) {
            return response()->json([
                'message' => __('Property owner cannot initiate a deal as buyer.'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $sellerAgentId) {
            return response()->json([
                'message' => 'Este imóvel não possui responsável vinculado para iniciar conversa.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $property->owner_id) {
            $property->owner_id = $sellerAgentId;
            $property->save();
        }

        $existingDeal = Deal::query()
            ->where('property_id', $property->id)
            ->where('seller_agent_id', $sellerAgentId)
            ->where('buyer_agent_id', $user->id)
            ->whereNotIn('status', ['closed', 'cancelled', 'lost'])
            ->latest('id')
            ->first();

        if ($existingDeal) {
            return (new DealResource($existingDeal->load(['property.group', 'sellerAgent', 'buyerAgent'])))
                ->response()
                ->setStatusCode(200);
        }

        $data = $request->validated();
        $commissionSplit = $data['commission_split'] ?? ['seller_agent' => 50, 'buyer_agent' => 50];
        $hasAcceptedConversation = Deal::query()
            ->where('property_id', $property->id)
            ->where('seller_agent_id', $sellerAgentId)
            ->whereNotIn('status', ['initiated', 'closed', 'cancelled', 'lost'])
            ->exists();
        $initialStatus = $hasAcceptedConversation ? 'initiated' : 'proposal';

        $deal = DB::transaction(function () use ($data, $property, $user, $commissionSplit, $sellerAgentId, $initialStatus) {
            /** @var Deal $deal */
            $deal = Deal::query()->create([
                'property_id' => $property->id,
                'seller_agent_id' => $sellerAgentId,
                'buyer_agent_id' => $user->id,
                'buyer_name' => $data['buyer_name'] ?? $user->name,
                'buyer_contact' => $data['buyer_contact'] ?? $user->phone_e164,
                'commission_percent' => $data['commission_percent'] ?? 6.00,
                'commission_split_json' => $commissionSplit,
                'status' => $initialStatus,
                'notes' => $data['notes'] ?? null,
                'started_at' => now(),
            ]);

            if ($initialStatus === 'proposal') {
                $deal->messages()->create([
                    'user_id' => $user->id,
                    'message' => 'Conversa iniciada automaticamente. O corretor já pode responder.',
                ]);
            } else {
                $deal->messages()->create([
                    'user_id' => $user->id,
                    'message' => 'Contato enviado. Aguarde o corretor aceitar para liberar a conversa.',
                ]);
            }

            return $deal->load(['property.group', 'sellerAgent', 'buyerAgent', 'messages.author', 'messages.attachments']);
        });

        return (new DealResource($deal))->response()->setStatusCode(201);
    }

    public function show(Deal $deal): DealResource
    {
        $this->authorize('view', $deal);

        return new DealResource($deal->load(['property.group', 'sellerAgent', 'buyerAgent', 'messages.author', 'messages.attachments']));
    }

    public function update(DealUpdateRequest $request, Deal $deal): DealResource
    {
        $this->authorize('update', $deal);

        $data = $request->validated();

        if (
            ($data['status'] ?? null) === 'proposal' &&
            $deal->status === 'initiated' &&
            $request->user()->id !== $deal->seller_agent_id &&
            ! $request->user()->groups()
                ->where('groups.id', $deal->property->group_id)
                ->wherePivotIn('role_in_group', ['owner', 'moderator'])
                ->exists()
        ) {
            throw new AuthorizationException('Somente o responsável pelo imóvel pode aceitar esta conversa.');
        }

        if (isset($data['commission_split'])) {
            $deal->commission_split_json = $data['commission_split'];
            unset($data['commission_split']);
        }

        $deal->fill($data);

        if (isset($data['status']) && $data['status'] === 'closed' && ! $deal->closed_at) {
            $deal->closed_at = now();
        }

        $deal->save();

        return new DealResource($deal->refresh()->load(['property.group', 'sellerAgent', 'buyerAgent']));
    }
}
