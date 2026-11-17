<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firewall_notification_logs', function (Blueprint $table): void {
            $table->string('status', 20)->default('queued')->after('subject')->index();
            $table->text('error_message')->nullable()->after('status');
            $table->timestamp('sent_at')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('firewall_notification_logs', function (Blueprint $table): void {
            $table->dropColumn(['status', 'error_message', 'sent_at']);
        });
    }
};
