<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('content_collector_raw_pages', function (Blueprint $table) {
            $table->id();
            $table->string('host');
            $table->string('url')->index();
            $table->string('status')->default('pending');
            $table->integer('depth')->default(0);
            $table->string('discovered_from')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->smallInteger('status_code')->nullable();
            $table->json('headers')->nullable();
            $table->longText('raw_content')->nullable();
            $table->string('raw_content_hash')->nullable();
            $table->string('task_id')->index();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'fetched_at']);
            $table->unique(['host', 'url']);
        });

        Schema::create('content_collector_parsed_pages', function (Blueprint $table) {
            $table->id();
            $table->string('host');
            $table->string('url')->index();
            $table->string('title')->nullable();
            $table->longText('body_html')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamps();

            $table->unique(['host', 'url']);
        });

        Schema::create('content_collector_media', function (Blueprint $table) {
            $table->id();
            $table->string('host');
            $table->string('url');
            $table->unsignedBigInteger('parsed_page_id')->nullable()->index();
            $table->string('mime_type')->nullable();
            $table->string('local_path')->nullable();
            $table->bigInteger('size')->nullable();
            $table->string('hash')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            $table->unique(['host', 'url']);
        });

        // ================= Task Lifecycle =================

        Schema::create('content_collector_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_id')->unique();
            $table->string('host');
            $table->string('status')->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // ================= Concurrency / Locks =================

        Schema::create('content_collector_task_locks', function (Blueprint $table) {
            $table->id();
            $table->string('host');
            $table->string('task_id');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['host', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_collector_task_locks');
        Schema::dropIfExists('content_collector_tasks');
        Schema::dropIfExists('content_collector_media');
        Schema::dropIfExists('content_collector_parsed_pages');
        Schema::dropIfExists('content_collector_raw_pages');
    }
};
