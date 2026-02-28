<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('crm_conversations')->cascadeOnDelete();
            $table->boolean('from_me')->default(false);
            $table->enum('type', ['text', 'image', 'audio', 'video', 'document', 'sticker'])->default('text');
            $table->text('content')->nullable();
            $table->string('media_url')->nullable();
            $table->timestamp('message_timestamp')->nullable();
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->string('remote_id')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_messages');
    }
};