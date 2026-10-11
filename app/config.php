<?php
// 環境ごとの値は app/config.local.php を置いて上書きする（Git管理外）。
// さくらでは db_driver を mysql にして下の mysql[] を書き換える。
declare(strict_types=1);

$config = [
    'db_driver' => 'sqlite',

    'sqlite' => [
        'path' => APP_ROOT . '/data/app.sqlite',
    ],

    // さくらのコントロールパネルに出ている値に差し替える
    'mysql' => [
        'host'     => 'mysqlXXX.db.sakura.ne.jp',
        'port'     => 3306,
        'database' => 'xxxxxx_report',
        'user'     => 'xxxxxx',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    'viewport' => [
        'user'  => ['w' => 768,  'h' => 1024],
        'admin' => ['w' => 1600, 'h' => 900],
    ],

    'app_name'       => '作業完了報告書SYSTEM',

    // 報告書の右上に入る自社情報
    'company_name'    => '株式会社アイソテック',
    'company_address' => '東京都中央区銀座1-1-1 銀座ビル4F',
    'company_tel'     => 'TEL 03-0000-0000 / FAX 03-0000-0001',
    'company_branch'  => '大阪営業所 大阪市北区梅田1-1-1　TEL 06-0000-0000',
    'login_max_fail' => 3,            // 3回で口座ロック。解除は事務局

    'demo' => false,                  // true でログイン画面にID/パスワードを入れた状態にする
    'auto_unlock_minutes' => 0,       // 0 なら自動解除しない（本番はこちら）
    'session_name'   => 'wcrsid',
    'debug'          => true,         // 本番では false
    'storage'        => [
        'signatures' => APP_ROOT . '/data/signatures',
        'pdf'        => APP_ROOT . '/data/pdf',
        'backups'    => APP_ROOT . '/data/backups',
        'tmp'        => APP_ROOT . '/data/tmp',
    ],
    'mail' => [
        'from_name'       => '作業完了報告書SYSTEM',
        'from_address'    => 'noreply@example.sakura.ne.jp',
        'default_subject' => '作業完了報告書',
        'dry_run'         => true,    // true の間は送らず .eml を残すだけ
    ],
];

if (getenv('VERCEL') !== false && is_file(APP_ROOT . '/app/config.vercel.php')) {
    $config = array_replace_recursive($config, require APP_ROOT . '/app/config.vercel.php');
}

if (is_file(APP_ROOT . '/app/config.local.php')) {
    $config = array_replace_recursive($config, require APP_ROOT . '/app/config.local.php');
}

return $config;
