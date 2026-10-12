<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('password')->index();
            $table->string('role', 32)->default('cashier')->after('is_super_admin')->index();
            $table->boolean('is_active')->default(true)->after('role')->index();
            $table->text('two_factor_secret')->nullable()->after('is_active');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_confirmed_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_super_admin']);
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'is_super_admin',
                'role',
                'is_active',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
