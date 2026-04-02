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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['apartment', 'house', 'land', 'commercial']);
            $table->string('title', 150);
            $table->string('slug', 180);
            $table->text('description');
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->unsignedTinyInteger('parking')->nullable();
            $table->decimal('area_m2', 8, 2)->nullable();
            $table->string('neighborhood', 120);
            $table->string('city', 80);
            $table->char('state', 2);
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->enum('price_visibility', ['show', 'hide'])->default('show');
            $table->enum('status', ['draft', 'active', 'reserved', 'sold'])->default('draft');
            $table->string('whatsapp_owner', 20)->nullable();
            $table->string('cover_image_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['group_id', 'slug']);
            $table->index(['group_id', 'status'], 'properties_group_status_idx');
            $table->index(['type', 'bedrooms', 'neighborhood', 'price'], 'properties_filter_idx');
            $table->index('owner_id', 'properties_owner_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
