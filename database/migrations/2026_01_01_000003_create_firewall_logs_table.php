<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firewall_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('ip_address', 45)->index();
            $table->string('method', 10)->default('GET');
            $table->text('path');
            $table->text('user_agent')->nullable();
            $table->string('action', 30)->index(); // blocked | dry_run_blocked | allowed
            $table->unsignedBigInteger('matched_rule_id')->nullable()->index();
            $table->unsignedBigInteger('matched_group_id')->nullable()->index();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firewall_logs');
    }
};
