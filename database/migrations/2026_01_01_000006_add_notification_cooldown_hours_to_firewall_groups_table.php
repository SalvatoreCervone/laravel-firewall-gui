<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firewall_groups', function (Blueprint $table): void {
            $table->unsignedInteger('notification_cooldown_hours')->nullable()->after('notify_on_hit');
        });
    }

    public function down(): void
    {
        Schema::table('firewall_groups', function (Blueprint $table): void {
            $table->dropColumn('notification_cooldown_hours');
        });
    }
};
