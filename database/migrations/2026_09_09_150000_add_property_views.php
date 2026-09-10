<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedBigInteger('views_count')->default(0);
        });

        Schema::create('property_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('viewed_on');
            $table->unique(['property_id', 'user_id', 'viewed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_views');
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('views_count');
        });
    }
};
