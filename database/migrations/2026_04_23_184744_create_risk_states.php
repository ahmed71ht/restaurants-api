<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('risk_states', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); 
            // key = ip OR fingerprint_hash
            $table->integer('risk_score')->default(0);
            $table->timestamp('last_seen')->nullable();
            $table->timestamp('last_decay_at')->nullable();
            $table->integer('failures')->default(0);
            $table->integer('successes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_states');
    }
};