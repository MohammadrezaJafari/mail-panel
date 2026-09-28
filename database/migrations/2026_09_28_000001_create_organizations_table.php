<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 32)->default('active');
            $table->unsignedInteger('max_domains')->nullable();
            $table->unsignedInteger('max_mailboxes')->nullable();
            $table->unsignedBigInteger('storage_limit_mb')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role', 32)->default('user')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
            $table->text('mail_password')->nullable()->after('password');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['role', 'is_active', 'mail_password', 'last_login_at', 'last_login_ip']);
        });
        Schema::dropIfExists('organizations');
    }
};
