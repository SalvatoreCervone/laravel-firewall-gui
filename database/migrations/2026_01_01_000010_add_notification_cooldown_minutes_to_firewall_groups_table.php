<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firewall_groups', function (Blueprint $table): void {
            $table->unsignedInteger('notification_cooldown_minutes')->nullable()->after('notification_cooldown_hours');
        });

        DB::table('firewall_groups')
            ->whereNotNull('notification_cooldown_hours')
            ->update([
                'notification_cooldown_minutes' => DB::raw('notification_cooldown_hours * 60'),
            ]);
    }

    public function down(): void
    {
        Schema::table('firewall_groups', function (Blueprint $table): void {
            $table->dropColumn('notification_cooldown_minutes');
        });
    }
};
