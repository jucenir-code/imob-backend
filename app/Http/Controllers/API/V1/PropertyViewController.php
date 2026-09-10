<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyViewController extends Controller
{
    public function __invoke(Request $request, Property $property): JsonResponse
    {
        $this->authorize('view', $property);
        abort_unless($request->user()->status === 'active', 403);

        $count = DB::transaction(function () use ($request, $property) {
            // Serialize concurrent visits, including duplicate requests from the same browser.
            $current = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
            $ownerId = $current->owner_id ?? $current->group?->owner_id;

            if ($current->status !== 'draft' && (int) $ownerId !== (int) $request->user()->id) {
                $inserted = DB::table('property_views')->insertOrIgnore([
                    'property_id' => $current->id,
                    'user_id' => $request->user()->id,
                    'viewed_on' => now()->toDateString(),
                ]);

                if ($inserted) {
                    // Analytics must not change the property's last edit date or fire update events.
                    Property::query()->whereKey($current->id)->toBase()->increment('views_count');
                    $current->views_count++;
                }
            }

            return (int) $current->views_count;
        }, 3);

        return response()->json(['views_count' => $count]);
    }
}
