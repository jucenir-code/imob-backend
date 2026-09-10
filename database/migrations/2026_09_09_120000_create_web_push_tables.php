<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_push_keys', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->text('keypair');
        });
        Schema::create('web_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->string('public_key', 100);
            $table->string('auth_token', 40);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_push_subscriptions');
        Schema::dropIfExists('web_push_keys');
    }
};
