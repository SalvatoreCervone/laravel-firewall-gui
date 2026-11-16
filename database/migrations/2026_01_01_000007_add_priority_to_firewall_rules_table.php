<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firewall_rules', function (Blueprint $table): void {
            $table->integer('priority')->default(0)->after('notify_on_hit');
        });
    }

    public function down(): void
    {
        Schema::table('firewall_rules', function (Blueprint $table): void {
            $table->dropColumn('priority');
        });
    }
};
