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
        Schema::create('supermarkets', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 50)->unique();
            $table->string('name', 100);
            $table->string('base_url', 255)->nullable();
            $table->boolean('requires_auth')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index('identifier');
            $table->index('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supermarkets');
    }
};
