<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_resources', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->morphs('resource');
            $table->string('external_id');
            $table->json('meta')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'resource_type', 'resource_id'], 'provider_resources_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_resources');
    }
};
