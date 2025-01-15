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
        Schema::create('receipt_items', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->foreignId('receipt_id')->constrained('receipts')->onDelete('cascade');
            $table->string('item_name');
            $table->integer('quantity'); // Quantity of the item
            $table->decimal('price', 10, 2); // Up to 10 digits, 2 decimal places
            $table->timestamps(); // created_at and updated_at columns
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_items');
    }
};
