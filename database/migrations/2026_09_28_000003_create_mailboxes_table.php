<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('local_part');
            $table->string('address')->unique();
            $table->string('name');
            $table->string('status', 32)->default('active');
            $table->boolean('is_shared')->default(false);
            $table->unsignedBigInteger('quota_mb')->default(5120);
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->unsignedInteger('message_count')->default(0);
            $table->json('forwarding_to')->nullable();
            $table->boolean('forwarding_keep_copy')->default(true);
            $table->boolean('auto_reply_enabled')->default(false);
            $table->string('auto_reply_subject')->nullable();
            $table->text('auto_reply_body')->nullable();
            $table->timestamp('auto_reply_starts_at')->nullable();
            $table->timestamp('auto_reply_ends_at')->nullable();
            $table->text('signature')->nullable();
            $table->timestamp('usage_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['domain_id', 'local_part']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailboxes');
    }
};
