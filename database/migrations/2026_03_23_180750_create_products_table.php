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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 100);
            $table->string('supermarket', 50);
            $table->string('name', 255);
            $table->string('quantity', 100)->nullable();
            $table->text('image_url')->nullable();
            $table->text('product_url')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'supermarket']);
            $table->index('supermarket');
            $table->index('name');

            $table->foreign('supermarket')
                ->references('identifier')
                ->on('supermarkets')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
