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
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->string('company')->nullable()->after('name');
        });

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->string('source')->nullable()->after('responsible_user_id');
            $table->string('loss_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropColumn('company');
        });

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropColumn(['source', 'loss_reason']);
        });
    }
};