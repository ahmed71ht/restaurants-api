<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('comment_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comment_id'); // تعليق الأب
            $table->unsignedBigInteger('user_id'); // صاحب الرد
            $table->text('reply'); // نص الرد
            $table->timestamps();

            // علاقة المفتاح الأجنبي
            $table->foreign('comment_id')->references('id')->on('restaurant_comments')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_replies');
    }
};
