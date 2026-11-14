<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('failed_at')->nullable()->after('delivered_at');
            $table->char('causal_order_key', 64)->nullable()->after('failed_at');
            $table->index(['project_id', 'causal_order_key', 'id'], 'events_causal_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_causal_order_index');
            $table->dropColumn(['failed_at', 'causal_order_key']);
        });
    }
};
