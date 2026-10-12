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
        // 1. Settings Table (multi-scope: business, branch, user)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->default('business')->index();
            $table->unsignedBigInteger('scope_id')->nullable()->index();
            $table->string('key')->index();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'scope_id', 'key'], 'scope_key_unique');
        });

        // 2. Enhance Branches Table with extended contact & receipt metadata
        Schema::table('branches', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('state');
            $table->string('country')->nullable()->default('US')->after('postal_code');
            $table->string('manager_name')->nullable()->after('country');
            $table->text('receipt_header')->nullable()->after('manager_name');
            $table->text('receipt_footer')->nullable()->after('receipt_header');
            $table->boolean('is_default')->default(false)->after('is_active');
        });

        // 3. User Theme Preference
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme_preference')->default('light')->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('theme_preference');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'city',
                'state',
                'postal_code',
                'country',
                'manager_name',
                'receipt_header',
                'receipt_footer',
                'is_default',
            ]);
        });

        Schema::dropIfExists('settings');
    }
};
