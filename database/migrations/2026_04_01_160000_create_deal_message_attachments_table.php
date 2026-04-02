<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_message_id')->constrained('deal_messages')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('url');
            $table->string('mime_type', 120)->nullable();
            $table->string('original_name', 190)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['deal_message_id', 'position'], 'deal_msg_attachments_msg_pos_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_message_attachments');
    }
};
