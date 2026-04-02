<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('seller_agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('buyer_agent_id')->constrained('users')->cascadeOnDelete();
            $table->string('buyer_name', 120)->nullable();
            $table->string('buyer_contact', 50)->nullable();
            $table->decimal('commission_percent', 5, 2)->default(6.00);
            $table->json('commission_split_json');
            $table->enum('status', ['initiated', 'under_review', 'proposal', 'in_escrow', 'closed', 'lost', 'cancelled'])->default('initiated');
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['property_id', 'status'], 'deals_property_status_idx');
            $table->index(['seller_agent_id', 'buyer_agent_id', 'status'], 'deals_agents_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
