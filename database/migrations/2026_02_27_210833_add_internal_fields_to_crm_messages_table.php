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
        Schema::table('crm_messages', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('remote_id');
            $table->foreignId('user_id')->nullable()->after('is_internal')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['is_internal', 'user_id']);
        });
    }
};