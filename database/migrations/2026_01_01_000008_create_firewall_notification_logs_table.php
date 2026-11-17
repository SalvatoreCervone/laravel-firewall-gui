<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firewall_notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('ip_address', 45)->index();
            $table->string('decision_action', 30)->index();
            $table->text('recipients');
            $table->string('subject')->nullable();
            $table->unsignedBigInteger('matched_rule_id')->nullable()->index();
            $table->unsignedBigInteger('matched_group_id')->nullable()->index();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firewall_notification_logs');
    }
};
