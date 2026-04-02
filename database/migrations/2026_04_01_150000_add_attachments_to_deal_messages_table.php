<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_messages', function (Blueprint $table) {
            $table->string('attachment_type', 20)->nullable()->after('message');
            $table->string('attachment_url')->nullable()->after('attachment_type');
            $table->string('attachment_mime_type', 120)->nullable()->after('attachment_url');
            $table->string('attachment_original_name', 190)->nullable()->after('attachment_mime_type');
            $table->unsignedInteger('attachment_duration_ms')->nullable()->after('attachment_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('deal_messages', function (Blueprint $table) {
            $table->dropColumn([
                'attachment_type',
                'attachment_url',
                'attachment_mime_type',
                'attachment_original_name',
                'attachment_duration_ms',
            ]);
        });
    }
};
