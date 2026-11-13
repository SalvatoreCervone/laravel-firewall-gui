<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firewall_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')
                ->nullable()
                ->constrained('firewall_groups')
                ->cascadeOnDelete();
            $table->string('type', 20)->index(); // whitelist | blacklist
            $table->string('rule_type', 20)->index(); // ip | cidr | range
            $table->string('value', 120)->index();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firewall_rules');
    }
};
