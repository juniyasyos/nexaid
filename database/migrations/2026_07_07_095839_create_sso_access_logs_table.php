<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            
            // Allow null if user doesn't have an active access profile or role when accessing
            $table->unsignedBigInteger('access_profile_id')->nullable();
            $table->foreign('access_profile_id')->references('id')->on('access_profiles')->nullOnDelete();
            
            $table->unsignedBigInteger('role_id')->nullable();
            $table->foreign('role_id')->references('id')->on('iam_roles')->nullOnDelete();
            
            $table->string('ip_address')->nullable();
            
            // Optional: which token id or session id was used
            $table->string('session_id')->nullable();
            
            $table->timestamp('accessed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_access_logs');
    }
};
