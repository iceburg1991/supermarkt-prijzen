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
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 100);
            $table->string('supermarket', 50);
            $table->integer('price_cents');
            $table->integer('promo_price_cents')->default(0);
            $table->boolean('available')->default(true);
            $table->string('badge', 255)->nullable();
            $table->string('unit_price', 100)->nullable();
            $table->timestamp('scraped_at');
            $table->timestamps();

            $table->index(['product_id', 'supermarket', 'scraped_at']);
            $table->index('scraped_at');
            $table->index('promo_price_cents');

            // Composite foreign key constraint
            $table->foreign(['product_id', 'supermarket'])
                ->references(['product_id', 'supermarket'])
                ->on('products')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
