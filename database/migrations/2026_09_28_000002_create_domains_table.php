<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('max_mailboxes')->default(50);
            $table->unsignedInteger('max_aliases')->default(200);
            $table->unsignedBigInteger('default_quota_mb')->default(5120);
            $table->unsignedBigInteger('max_quota_mb')->default(10240);
            $table->unsignedBigInteger('domain_quota_mb')->default(102400);
            $table->json('dns_status')->nullable();
            $table->timestamp('dns_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
