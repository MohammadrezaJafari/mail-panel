<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->json('attachments')->nullable();
            $table->timestamp('send_at')->index();
            $table->string('status', 20)->default('pending')->index();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('snoozes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('origin_folder');
            $table->string('snooze_folder');
            $table->unsignedBigInteger('uid');
            $table->string('message_id')->nullable();
            $table->string('subject')->nullable();
            $table->timestamp('wake_at')->index();
            $table->string('status', 20)->default('pending')->index();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snoozes');
        Schema::dropIfExists('scheduled_messages');
    }
};
