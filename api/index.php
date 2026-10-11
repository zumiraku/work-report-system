<?php
// Vercel の入口。さくらでは public/index.php が入口になるのでこのファイルは使わない。
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/_check') {
    header('Content-Type: text/plain; charset=utf-8');

    echo "PHP          : " . PHP_VERSION . "\n";
    echo "SAPI         : " . PHP_SAPI . "\n";
    echo "実行場所      : " . APP_ROOT . "\n\n";

    foreach (['pdo', 'pdo_sqlite', 'mbstring', 'json', 'session', 'gd', 'zlib', 'zip', 'dom', 'xmlreader'] as $ext) {
        printf("拡張 %-12s : %s\n", $ext, extension_loaded($ext) ? 'あり' : '★なし');
    }

    $tmp = sys_get_temp_dir();
    echo "\n一時フォルダ  : {$tmp}（" . (is_writable($tmp) ? '書き込める' : '★書き込めない') . "）\n";

    $seed = APP_ROOT . '/data/demo.sqlite';
    echo "デモ用DB      : " . (is_file($seed) ? number_format(filesize($seed)) . ' bytes' : '★ありません') . "\n";

    $live = $tmp . '/wcr/app.sqlite';
    echo "動作中のDB    : " . (is_file($live) ? number_format(filesize($live)) . ' bytes' : '（まだ作られていません）') . "\n";

    if (is_file($live)) {
        try {
            require_once APP_ROOT . '/app/lib/helpers.php';
            require_once APP_ROOT . '/app/lib/Database.php';
            Database::boot(config());

            echo "\nアカウントの状態\n";
            foreach (Database::all(
                'SELECT account_id, is_locked, failed_count, locked_at FROM accounts ORDER BY id'
            ) as $a) {
                printf(
                    "  %-12s ロック:%-4s 失敗:%d回  %s\n",
                    $a['account_id'],
                    $a['is_locked'] ? '★あり' : 'なし',
                    (int) $a['failed_count'],
                    $a['locked_at'] ? '（' . $a['locked_at'] . ' に）' : ''
                );
            }
            echo "\n  ※ロックは " . (int) config('auto_unlock_minutes') . "分で自動的に解除されます\n";
        } catch (Throwable $e) {
            echo "\nアカウントの状態は読めませんでした：" . $e->getMessage() . "\n";
        }
    }

    exit;
}

require APP_ROOT . '/app/bootstrap.php';

$router = new Router();
require APP_ROOT . '/app/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
