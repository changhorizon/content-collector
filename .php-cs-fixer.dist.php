<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . DIRECTORY_SEPARATOR . 'config',
        __DIR__ . DIRECTORY_SEPARATOR . 'database',
        __DIR__ . DIRECTORY_SEPARATOR . 'src',
        __DIR__ . DIRECTORY_SEPARATOR . 'tests',
    ])
    ->name('*.php')
    ->append([
        __DIR__ . DIRECTORY_SEPARATOR . '.php-cs-fixer.dist.php',
    ]);

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        // ================= 基础规范（必需） =================
        '@PSR12' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,

        // ================= 现代 PHP（低风险） ==============
        'array_syntax' => ['syntax' => 'short'],
        'list_syntax' => ['syntax' => 'short'],

        // ================= 可读性 ===========================
        'single_quote' => true,
        'concat_space' => ['spacing' => 'one'],

        // ================= 多行结构 =========================
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],

        // ================= 安全 / 质量 ======================
        'no_multiple_statements_per_line' => true,
        'no_extra_blank_lines' => true,
    ])
    ->setFinder($finder);
