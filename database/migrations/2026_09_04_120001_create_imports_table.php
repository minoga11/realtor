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
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('external_import_id');
            $table->timestamp('sent_at')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('total_offers')->default(0);
            $table->integer('processed_offers')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'external_import_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
