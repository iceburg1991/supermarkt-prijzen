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
        Schema::create('scraper_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('supermarket')->unique();
            $table->text('refresh_token'); // Encrypted
            $table->timestamp('token_obtained_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraper_tokens');
    }
};
