<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('crm_conversations', function (Blueprint $table) {
            $table->dropUnique(['whatsapp_instance_id', 'jid']);
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['whatsapp_instance_id']);
            }
        });

        Schema::table('crm_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('whatsapp_instance_id')->nullable()->change();
            if (config('database.default') !== 'sqlite') {
                $table->foreign('whatsapp_instance_id')
                    ->references('id')
                    ->on('whatsapp_instances')
                    ->nullOnDelete();
            }
            $table->unique(['team_id', 'jid']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_conversations', function (Blueprint $table) {
        // Revert omitted for brevity
        });
    }
};