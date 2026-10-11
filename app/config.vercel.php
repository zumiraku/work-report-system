<?php
// Vercel で動かすときの上書き。app/config.php が環境変数 VERCEL を見て読む。
// Vercel は置いたファイルを書き換えられないので、唯一書ける /tmp にデモDBを複製して使う。
// そのため入力したデータはしばらくすると消える。クライアントに見せる用で、本番はさくら。
declare(strict_types=1);

$runtime = sys_get_temp_dir() . '/wcr';

foreach (['', '/signatures', '/pdf', '/backups', '/tmp'] as $sub) {
    if (!is_dir($runtime . $sub)) {
        @mkdir($runtime . $sub, 0777, true);
    }
}

// セッションの置き場所も /tmp。session_start より前に決める必要がある
if (is_writable(sys_get_temp_dir()) && !headers_sent()) {
    ini_set('session.save_path', sys_get_temp_dir());
}

$live = $runtime . '/app.sqlite';
$seed = APP_ROOT . '/data/demo.sqlite';

if (!is_file($live) && is_file($seed)) {
    @copy($seed, $live);

    foreach (glob(APP_ROOT . '/data/demo_signatures/*.png') ?: [] as $png) {
        @copy($png, $runtime . '/signatures/' . basename($png));
    }
}

return [
    'db_driver' => 'sqlite',
    'sqlite'    => ['path' => $live],

    'debug' => false,

    'demo' => true,
    'auto_unlock_minutes' => 5,

    'storage' => [
        'signatures' => $runtime . '/signatures',
        'pdf'        => $runtime . '/pdf',
        'backups'    => $runtime . '/backups',
        'tmp'        => $runtime . '/tmp',
    ],

    'mail' => ['dry_run' => true],
];
