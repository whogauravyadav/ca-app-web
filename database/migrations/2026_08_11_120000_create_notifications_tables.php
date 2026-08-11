<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        DB::statement('ALTER TABLE device_tokens MODIFY user_id BIGINT UNSIGNED NULL');

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable()->after('platform');
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('type')->default('custom');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('data')->nullable();
            $table->unsignedInteger('fcm_success')->default(0);
            $table->unsignedInteger('fcm_failure')->default(0);
            $table->boolean('sent_via_fcm')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'created_at']);
        });

        Schema::create('notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('app_notification_id')->constrained('app_notifications')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'app_notification_id']);
        });

        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('notification_settings')->insert([
            ['key' => 'notify_on_article_publish', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'notify_on_quiz_publish', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'fcm_topic', 'value' => 'all_users', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('notification_settings');

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropColumn('last_used_at');
        });
    }
};
