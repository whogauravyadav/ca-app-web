<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('source', 40)->nullable()->after('is_premium_early');
            $table->string('source_url', 500)->nullable()->after('source');
            $table->unique('source_url');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->string('source', 40)->nullable()->after('published_at');
            $table->string('source_url', 500)->nullable()->after('source');
            $table->string('quiz_kind', 40)->nullable()->after('source_url');
            $table->unique('source_url');
        });

        Schema::create('fetch_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40)->default('outsource_1');
            $table->string('mode', 40);
            $table->boolean('dry_run')->default(false);
            $table->boolean('publish')->default(false);
            $table->date('since')->nullable();
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('errors')->nullable();
            $table->string('status', 20)->default('running');
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetch_logs');
        Schema::table('articles', function (Blueprint $table) {
            $table->dropUnique(['source_url']);
            $table->dropColumn(['source', 'source_url']);
        });
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropUnique(['source_url']);
            $table->dropColumn(['source', 'source_url', 'quiz_kind']);
        });
    }
};
