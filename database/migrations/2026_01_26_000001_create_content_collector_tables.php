<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 原始页面表（Raw Pages）
        |--------------------------------------------------------------------------
        |
        | 用于存储采集过程中获取到的原始页面数据，包括 URL、HTTP 响应信息、原始 HTML 内容等。
        |
        */
        Schema::create('content_collector_raw_pages', function (Blueprint $table) {
            $table->id();
            $table->string('task_id')->index()->comment('所属采集任务标识');
            $table->string('host')->comment('页面所属主机');
            $table->string('url')->index()->comment('标准化后的页面 URL');
            $table->smallInteger('http_code')->nullable()->comment('HTTP 返回状态码');
            $table->json('http_headers')->nullable()->comment('HTTP 原始响应头');
            $table->longText('raw_html')->nullable()->comment('页面原始 HTML 内容');
            $table->string('raw_html_hash')->nullable()->comment('原始 HTML 内容哈希');
            $table->timestamp('fetched_at')->nullable()->comment('页面成功抓取的时间');
            $table->timestamps();
            $table->unique(['task_id', 'host', 'url']);
            $table->comment('采集得到的原始页面数据');
        });

        /*
        |--------------------------------------------------------------------------
        | 媒体资源表（Media）
        |--------------------------------------------------------------------------
        |
        | 用于存储页面中引用到的外部媒体资源，例如图片、脚本、样式文件等，并记录其下载与存储信息。
        |
        */
        Schema::create('content_collector_media', function (Blueprint $table) {
            $table->id();
            $table->string('task_id')->index()->comment('所属采集任务标识');
            $table->string('host')->comment('媒体资源所属主机');
            $table->string('url')->comment('媒体资源 URL');
            $table->smallInteger('http_code')->nullable()->comment('媒体请求返回状态码');
            $table->string('http_content_type')->nullable()->comment('服务器返回的内容类型');
            $table->bigInteger('content_size')->nullable()->comment('媒体内容大小（字节）');
            $table->string('content_hash')->nullable()->comment('媒体内容哈希');
            $table->string('storage_path')->nullable()->comment('媒体存储路径');
            $table->timestamp('downloaded_at')->nullable()->comment('媒体下载完成时间');
            $table->timestamps();
            $table->unique(['task_id', 'host', 'url']);
            $table->comment('页面引用的外部媒体资源');
        });

        /*
        |--------------------------------------------------------------------------
        | 引用关系表（References）
        |--------------------------------------------------------------------------
        |
        | 用于描述页面与页面、页面与媒体资源之间的引用关系，包含引用来源、目标类型及引用语义。
        |
        */
        Schema::create('content_collector_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('raw_page_id')->index()->comment('来源原始页面 ID');
            $table->unsignedBigInteger('target_id')->comment('引用目标 ID');
            $table->enum('target_type', ['page', 'media'])->comment('引用目标类型');
            $table->string('source_tag')->nullable()->comment('产生引用的 HTML 标签');
            $table->string('source_attr')->nullable()->comment('产生引用的 HTML 属性');
            $table->enum(
                'relation',
                ['link', 'embed', 'import', 'preload', 'redirect', 'canonical'],
            )->nullable()->comment('引用关系语义');
            $table->timestamps();
            $table->unique(
                ['raw_page_id', 'target_id', 'target_type', 'source_tag', 'source_attr'],
                'cc_ref_raw_target_tag_unique',
            );
            $table->comment('页面与页面 / 媒体之间的引用关系');
        });

        /*
        |--------------------------------------------------------------------------
        | 解析页面表（Parsed Pages）
        |--------------------------------------------------------------------------
        |
        | 用于存储从原始页面解析得到的结构化内容，例如标题、正文、Meta 信息等。
        |
        */
        Schema::create('content_collector_parsed_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('raw_page_id')->comment('来源原始页面 ID');
            $table->string('host')->comment('页面所属主机');
            $table->string('url')->index()->comment('页面 URL');
            $table->string('html_title')->nullable()->comment('解析得到的页面标题');
            $table->longText('html_body')->nullable()->comment('解析得到的正文内容');
            $table->json('html_meta')->nullable()->comment('解析得到的 Meta 信息');
            $table->timestamp('parsed_at')->nullable()->comment('页面完成解析的时间');
            $table->timestamps();
            $table->unique(['host', 'url']);
            $table->comment('从原始页面解析得到的结构化内容');
        });

        /*
        |--------------------------------------------------------------------------
        | URL 处理流程表（URL Ledger）
        |--------------------------------------------------------------------------
        |
        | 用于记录单个 URL 在采集任务中的处理流程与最终结果，
        | 覆盖从发现、调度到抓取、解析直至终结态的完整生命周期。
        |
        | 该表用于流程审计、失败分析与任务执行追踪，
        | 不直接存储页面内容，仅反映处理状态与原因。
        |
        */
        Schema::create('content_collector_url_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('task_id')->index()->comment('所属采集任务标识');
            $table->string('host')->comment('URL 所属主机');
            $table->string('url')->comment('被处理的 URL');
            $table->timestamp('discovered_at')->nullable()->comment('URL 被发现的时间');
            $table->timestamp('scheduled_at')->nullable()->comment('URL 被调度进入处理队列的时间');
            $table->timestamp('fetched_at')->nullable()->comment('URL 对应页面成功抓取的时间');
            $table->timestamp('parsed_at')->nullable()->comment('URL 对应页面完成解析的时间');
            $table->enum(
                'final_result',
                ['success', 'failed', 'skipped', 'denied'],
            )->nullable()->comment('URL 处理的最终结果状态');
            $table->string('final_reason')->nullable()->comment('终结结果原因说明，如失败或被拒绝的具体原因');
            $table->timestamps();
            $table->unique(['task_id', 'host', 'url']);
            $table->comment('采集任务中 URL 处理流程与最终状态记录');
        });

        /*
        |--------------------------------------------------------------------------
        | 采集任务表（Tasks）
        |--------------------------------------------------------------------------
        |
        | 用于记录采集任务的执行状态、目标主机以及任务的开始与结束时间。
        |
        */
        Schema::create('content_collector_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_id')->unique()->comment('采集任务标识');
            $table->string('host')->comment('任务目标主机');
            $table->enum(
                'status',
                ['running', 'finished', 'failed', 'cancelled'],
            )->default('running')->comment('任务执行状态');
            $table->timestamp('started_at')->nullable()->comment('任务开始时间');
            $table->timestamp('finished_at')->nullable()->comment('任务结束时间');
            $table->timestamps();
            $table->comment('采集任务状态记录');
        });

        /*
        |--------------------------------------------------------------------------
        | 任务锁表（Task Locks）
        |--------------------------------------------------------------------------
        |
        | 用于控制采集任务的并发执行，防止重复或过量抓取。
        |
        */
        Schema::create('content_collector_task_locks', function (Blueprint $table) {
            $table->id();
            $table->string('task_id')->comment('采集任务标识');
            $table->string('host')->comment('任务目标主机');
            $table->unsignedInteger('count')->default(0)->comment('当前锁计数');
            $table->timestamps();
            $table->unique(['host', 'task_id']);
            $table->comment('采集任务并发控制锁');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_collector_task_locks');
        Schema::dropIfExists('content_collector_tasks');
        Schema::dropIfExists('content_collector_url_ledger');
        Schema::dropIfExists('content_collector_parsed_pages');
        Schema::dropIfExists('content_collector_references');
        Schema::dropIfExists('content_collector_media');
        Schema::dropIfExists('content_collector_raw_pages');
    }
};
