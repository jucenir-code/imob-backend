<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('properties')
            ->join('groups', 'groups.id', '=', 'properties.group_id')
            ->whereNull('properties.owner_id')
            ->select('properties.id as property_id', 'groups.owner_id as group_owner_id')
            ->get();

        foreach ($rows as $row) {
            if (! $row->group_owner_id) {
                continue;
            }

            DB::table('properties')
                ->where('id', $row->property_id)
                ->update(['owner_id' => $row->group_owner_id]);
        }
    }

    public function down(): void
    {
        // no-op
    }
};
