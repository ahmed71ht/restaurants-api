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
        Schema::create('browser_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint_hash')->unique();
            $table->json('fingerprint_data')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->integer('user_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('browser_fingerprints');
    }
};
