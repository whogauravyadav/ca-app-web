<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('student')->after('password'); // student|admin|editor
            $table->string('subscription_status')->default('free')->after('role'); // free|active|expired
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_status');
            $table->string('google_id')->nullable()->unique()->after('subscription_expires_at');
            $table->unsignedInteger('streak_count')->default(0)->after('google_id');
            $table->date('last_active_date')->nullable()->after('streak_count');
            $table->string('avatar_url')->nullable()->after('last_active_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'subscription_status',
                'subscription_expires_at',
                'google_id',
                'streak_count',
                'last_active_date',
                'avatar_url',
            ]);
        });
    }
};
