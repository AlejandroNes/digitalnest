<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('submission_key')->unique();
            $table->string('name', 150);
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->string('subject', 200)->nullable();
            $table->text('message');
            $table->enum('status', ['pending', 'read'])->default('pending');
            $table->timestamp('notification_attempted_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
