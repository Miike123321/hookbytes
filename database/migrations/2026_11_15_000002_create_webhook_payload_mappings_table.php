<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_payload_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('source_path');
            $table->string('target_path');
            $table->string('cast_type', 16)->default('raw');
            $table->text('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_payload_mappings');
    }
};
