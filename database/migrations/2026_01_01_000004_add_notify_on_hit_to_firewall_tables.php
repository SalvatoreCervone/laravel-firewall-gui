<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firewall_groups', function (Blueprint $table): void {
            $table->boolean('notify_on_hit')->default(false)->after('is_active');
        });

        Schema::table('firewall_rules', function (Blueprint $table): void {
            $table->boolean('notify_on_hit')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('firewall_groups', function (Blueprint $table): void {
            $table->dropColumn('notify_on_hit');
        });

        Schema::table('firewall_rules', function (Blueprint $table): void {
            $table->dropColumn('notify_on_hit');
        });
    }
};
