<?php
declare(strict_types=1);

function seed_all(): void
{
    Database::transaction(function () {
        seed_truncate();
        seed_admin();
        $accounts = seed_accounts();
        seed_workers($accounts);
        seed_models();
        seed_parts();
        seed_report_texts($accounts);
        seed_checklist();
        seed_reports($accounts);
    });

    $c = fn(string $t) => (int) Database::value("SELECT COUNT(*) FROM {$t}");
    printf(
        "seed    : accounts=%d workers=%d models=%d parts=%d texts=%d reports=%d internal=%d\n",
        $c('accounts'), $c('workers'), $c('machine_models'), $c('parts'),
        $c('report_texts'), $c('reports'), $c('internal_reports')
    );
}

function seed_truncate(): void
{
    foreach ([
        'mail_logs', 'internal_report_parts', 'internal_reports',
        'report_measurements', 'report_parts', 'report_models', 'report_workers', 'reports',
        'checklist_items', 'report_texts', 'parts', 'machine_models', 'workers',
        'audit_logs', 'login_attempts', 'remember_tokens', 'sync_ops', 'accounts', 'admins',
    ] as $t) {
        Database::pdo()->exec("DELETE FROM {$t}");
        if (Database::driver() === 'mysql') {
            Database::pdo()->exec("ALTER TABLE {$t} AUTO_INCREMENT = 1");
        }
    }
    if (Database::driver() === 'sqlite') {
        Database::pdo()->exec("DELETE FROM sqlite_sequence");
    }
}

function seed_admin(): void
{
    Database::insert('admins', [
        'account_id'    => 'admin',
        'password_hash' => password_hash('admin1234', PASSWORD_DEFAULT),
        'notify_email'  => 'jimukyoku@example.co.jp',
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);
}

function seed_accounts(): array
{
    $rows = [
        ['ABCDE0001', '株式会社エムテック設備', 'mtec@example.co.jp'],
        ['ABCDE0002', '有限会社山田空調',       'yamada@example.co.jp'],
        ['ABCDE0003', '第一メンテナンス株式会社', 'daiichi@example.co.jp'],
    ];
    $ids = [];
    foreach ($rows as [$login, $company, $mail]) {
        $ids[$login] = Database::insert('accounts', [
            'account_id'    => $login,
            'password_hash' => password_hash('pass1234', PASSWORD_DEFAULT),
            'company_name'  => $company,
            'email'         => $mail,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
    return $ids;
}

function seed_workers(array $accounts): void
{
    $main = [
        ['鈴木太郎', 'スズキタロウ'],
        ['加藤次郎', 'カトウジロウ'],
        ['山本直人', 'ヤマモトナオト'],
        ['神保進之介', 'ジンボシンノスケ'],
        ['小田雄一', 'オダユウイチ'],
        ['浅香光男', 'アサカミツオ'],
        ['米窪花子', 'ヨネクボハナコ'],
        ['落合健一', 'オチアイケンイチ'],
        ['山田修', 'ヤマダオサム'],
        ['佐藤剛', 'サトウツヨシ'],
    ];
    foreach ($main as [$name, $kana]) {
        Database::insert('workers', [
            'account_id' => $accounts['ABCDE0001'],
            'name'       => $name,
            'kana'       => $kana,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    foreach ([['田中一郎', 'タナカイチロウ'], ['大谷翔', 'オオタニショウ']] as [$name, $kana]) {
        Database::insert('workers', [
            'account_id' => $accounts['ABCDE0002'],
            'name'       => $name,
            'kana'       => $kana,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function seed_models(): void
{
    $models = [
        ['無菌病室 MIU-201', 'ムキンビョウシツMIU-201'],
        ['無菌病室 MIU-401', 'ムキンビョウシツMIU-401'],
        ['MDF', 'MDF'],
        ['RX', 'RX'],
        ['LI-11', 'LI-11'],
        ['LI-12', 'LI-12'],
        ['LI-13', 'LI-13'],
        ['LI-32', 'LI-32'],
        ['LI-30', 'LI-30'],
        ['保冷庫', 'ホレイコ'],
        ['保温庫', 'ホオンコ'],
        ['アイソレーション盤', 'アイソレーションバン'],
    ];
    $i = 0;
    foreach ($models as [$name, $kana]) {
        Database::insert('machine_models', [
            'name'       => $name,
            'kana'       => $kana,
            'sort_order' => ++$i * 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function seed_parts(): void
{
    $featured = [
        ['プレフィルター', 'プレフィルター', '枚', 999999],
        ['水フィルター', 'ミズフィルター', '本', 999998],
        ['配管キット', 'ハイカンキット', '式', 999997],
        ['送風機', 'ソウフウキ', '台', 999996],
        ['HEPAフィルター', 'ヘパフィルター', '枚', 999995],
        ['スイッチ', 'スイッチ', '個', 999994],
        ['ランプ', 'ランプ', '個', 999993],
        ['リレー', 'リレー', '個', 999992],
        ['風速切替基板', 'フウソクキリカエキバン', '枚', 999991],
        ['制御基板', 'セイギョキバン', '枚', 999990],
        ['ファン', 'ファン', '個', 999989],
    ];

    $categories = [
        ['中性能フィルター', 'チュウセイノウフィルター', '枚'],
        ['ケミカルフィルター', 'ケミカルフィルター', '枚'],
        ['活性炭フィルター', 'カッセイタンフィルター', '枚'],
        ['パッキン', 'パッキン', '個'],
        ['Oリング', 'オーリング', '個'],
        ['ベアリング', 'ベアリング', '個'],
        ['Vベルト', 'ブイベルト', '本'],
        ['プーリー', 'プーリー', '個'],
        ['電磁弁', 'デンジベン', '個'],
        ['ボールバルブ', 'ボールバルブ', '個'],
        ['圧力計', 'アツリョクケイ', '個'],
        ['流量計', 'リュウリョウケイ', '個'],
        ['温度センサ', 'オンドセンサ', '個'],
        ['湿度センサ', 'シツドセンサ', '個'],
        ['差圧センサ', 'サアツセンサ', '個'],
        ['インバータ', 'インバータ', '台'],
        ['電源基板', 'デンゲンキバン', '枚'],
        ['表示基板', 'ヒョウジキバン', '枚'],
        ['操作パネル', 'ソウサパネル', '枚'],
        ['ヒューズ', 'ヒューズ', '個'],
        ['マグネットスイッチ', 'マグネットスイッチ', '個'],
        ['サーマルリレー', 'サーマルリレー', '個'],
        ['タイマー', 'タイマー', '個'],
        ['蛍光灯', 'ケイコウトウ', '本'],
        ['LEDランプ', 'エルイーディーランプ', '本'],
        ['安定器', 'アンテイキ', '個'],
        ['ダンパー', 'ダンパー', '個'],
        ['ドアクローザ', 'ドアクローザ', '個'],
        ['戸車', 'トグルマ', '個'],
        ['ガスケット', 'ガスケット', '個'],
        ['ホース', 'ホース', '本'],
        ['継手', 'ツギテ', '個'],
        ['ポンプ', 'ポンプ', '台'],
        ['モーター', 'モーター', '台'],
        ['コンプレッサ', 'コンプレッサ', '台'],
        ['UVランプ', 'ユーブイランプ', '本'],
        ['吸気口カバー', 'キュウキコウカバー', '枚'],
        ['排気口カバー', 'ハイキコウカバー', '枚'],
        ['シャワーノズル', 'シャワーノズル', '個'],
        ['シャワーパン', 'シャワーパン', '個'],
        ['制御ケーブル', 'セイギョケーブル', '本'],
        ['端子台', 'タンシダイ', '個'],
        ['冷却コイル', 'レイキャクコイル', '個'],
        ['加熱コイル', 'カネツコイル', '個'],
        ['加湿エレメント', 'カシツエレメント', '個'],
        ['ドレンパン', 'ドレンパン', '個'],
        ['防振ゴム', 'ボウシンゴム', '個'],
        ['グリスニップル', 'グリスニップル', '個'],
    ];
    $series = ['MIU', 'LI', 'MDF', 'RX', 'BCR', 'FDS', 'SUS', 'AHU'];

    $target = 10000;
    $st = Database::pdo()->prepare(
        'INSERT INTO parts (name, kana, unit, priority, created_at, updated_at)
         VALUES (:name, :kana, :unit, :priority, :created_at, :updated_at)'
    );

    $push = function (string $name, string $kana, string $unit, int $priority) use ($st) {
        $st->execute([
            'name' => $name, 'kana' => $kana, 'unit' => $unit, 'priority' => $priority,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    };

    foreach ($featured as [$name, $kana, $unit, $priority]) {
        $push($name, $kana, $unit, $priority);
    }

    $count = count($featured);
    $num   = 101;
    while ($count < $target) {
        foreach ($series as $s) {
            foreach ($categories as [$name, $kana, $unit]) {
                if ($count >= $target) {
                    break 2;
                }
                $code = sprintf('%s-%03d', $s, $num);
                $push($name . ' ' . $code, $kana . ' ' . $code, $unit, 0);
                $count++;
            }
        }
        $num++;
    }
}

function seed_report_texts(array $accounts): void
{
    $common = [
        '水フィルター等、消耗部品は交換いたしました。',
        'BCR3シャワーパンのゆるみ、BCR5、入口扉の動きは修正し動作良好です。その他、特に問題ありません。',
        '室内清浄度はFDS209Dにてクラス100、および150にてクラス5をクリアーし良好です。',
        'フィルター差圧を測定し、いずれも管理値内であることを確認しました。',
        '外観点検・動作確認を実施し、異常は認められませんでした。',
    ];
    $i = 0;
    foreach ($common as $body) {
        Database::insert('report_texts', [
            'account_id' => null,
            'body'       => $body,
            'sort_order' => ++$i * 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    foreach ([
        '次回点検時にHEPAフィルターの交換を予定しております。',
        '異音の発生していた送風機ベアリングを交換し、振動値は基準内に収まりました。',
    ] as $body) {
        Database::insert('report_texts', [
            'account_id' => $accounts['ABCDE0001'],
            'body'       => $body,
            'sort_order' => ++$i * 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function seed_checklist(): void
{
    $items = [
        '予定の作業はすべて終了しました',
        '作業前の状態に復旧したことを確認しました',
        '作業により発生した廃材・梱包材はすべて撤去しました',
        '設備の運転状態が正常であることを立会者と確認しました',
        '次回点検までの注意事項をご説明しました',
    ];
    $i = 0;
    foreach ($items as $label) {
        Database::insert('checklist_items', [
            'label'      => $label,
            'sort_order' => ++$i * 10,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function seed_signature_image(int $no, string $name): string
{
    $dir = config('storage.signatures');
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $w = 560;
    $h = 180;
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    $ink = imagecolorallocate($im, 22, 28, 38);
    imagesetthickness($im, 5);
    imageantialias($im, true);

    mt_srand(crc32($name . $no));

    $x = 60;
    $strokes = 3 + (crc32($name) % 3);
    for ($s = 0; $s < $strokes; $s++) {
        $baseY = 110;
        $px    = $x;
        $py    = $baseY;

        $steps = 5 + mt_rand(0, 3);
        for ($i = 0; $i < $steps; $i++) {
            $nx = $px + mt_rand(18, 42);
            $ny = $baseY - mt_rand(-28, 46);
            imageline($im, (int) $px, (int) $py, (int) $nx, (int) $ny, $ink);
            $px = $nx;
            $py = $ny;
        }
        imageline($im, (int) $px, (int) $py, (int) ($px + 26), (int) ($py + 22), $ink);
        $x = $px + mt_rand(20, 40);
        if ($x > $w - 120) {
            break;
        }
    }

    $file = sprintf('sign_seed_%d.png', $no);
    imagepng($im, $dir . '/' . $file);
    imagedestroy($im);

    return $file;
}

function seed_reports(array $accounts): void
{
    $accountId = $accounts['ABCDE0001'];
    $allChecks = implode(',', array_column(
        Database::all('SELECT id FROM checklist_items ORDER BY sort_order'), 'id'
    ));
    $texts = array_column(
        Database::all('SELECT body FROM report_texts WHERE account_id IS NULL ORDER BY sort_order'), 'body'
    );
    $models = Database::all('SELECT id, name FROM machine_models ORDER BY sort_order');
    $parts  = Database::all('SELECT id, name, unit FROM parts ORDER BY priority DESC, id LIMIT 11');

    $rows = [
        [1001, '2026-08-25', '2026-08-26', '横浜市立大学附属病院', '4階 中央無菌室',
            '無菌病室(MIU-201)×3台 保守点検', ['落合健一', '米窪花子'], 'completed', 1],
        [1000, '2026-08-18', '2026-08-19', '川崎市立川崎病院 中央手術棟', 'B1 機械室',
            '空調設備 定期点検', ['山田修'], 'draft', 0],
        [999,  '2026-08-10', '2026-08-10', '都立駒込病院', '5階 血液内科病棟',
            'MIU-401 フィルター交換', ['山本直人', '小田雄一'], 'completed', 2],
        [998,  '2026-08-03', '2026-08-05', '横浜市立みなと赤十字病院', '3階 ICU',
            'アイソレーション盤 点検', ['加藤次郎'], 'submitted', 0],
        [990,  '2026-07-28', '2026-07-29', '至誠会練馬総合病院', '2階 手術室前室',
            'LI-11／LI-12 保守点検', ['神保進之介'], 'completed', 2],
        [904,  '2026-07-14', '2026-07-16', '足立総合病院 東館', '地下1階 設備室',
            '保冷庫・保温庫 定期点検', ['米窪花子'], 'completed', 0],
        [891,  '2026-06-30', '2026-07-02', '埼玉大学病院 中央検査部', '1階 検査室',
            'RX 保守点検一式', ['鈴木太郎'], 'completed', 1],
    ];

    foreach ($rows as [$no, $workDate, $createdDate, $hospital, $place, $title, $workers, $status, $mailCount]) {
        $isDraft     = $status === 'draft';
        $isCompleted = $status === 'completed';

        $reportId = Database::insert('reports', [
            'report_no'      => $no,
            'account_id'     => $accountId,
            'client_uuid'    => sprintf('seed-%04d-%s', $no, bin2hex(random_bytes(4))),
            'status'         => $status,
            'created_date'   => $createdDate,
            'hospital_name'  => $hospital,
            'work_date'      => $workDate,
            'work_date_end'  => null,
            'work_place'     => $place,
            'work_title'     => $title,
            'workers_text'   => implode('、', $workers),
            'work_note'      => $isDraft ? null : '以上、保守点検作業一式',
            'parts_note'     => null,
            'report_body'    => $isDraft ? null : implode("\n", array_slice($texts, 0, 3)),
            'checked_ids'    => $isDraft ? null : $allChecks,
            'submitter_name' => $isDraft ? null : $workers[0],
            'signature_file' => $isDraft ? null : seed_signature_image($no, $workers[0]),
            'signature_at'   => $isDraft ? null : $workDate . ' 16:40:00',
            'pdf_at'         => $isDraft ? null : $createdDate . ' 09:12:00',
            'mail_count'     => $mailCount,
            'submitted_at'   => $isDraft ? null : $createdDate . ' 09:20:00',
            'completed_at'   => $isCompleted ? $createdDate . ' 18:05:00' : null,
            'device_saved_at' => $workDate . ' 16:38:00',
            'synced_at'      => $isDraft ? null : $workDate . ' 17:02:00',
            'created_at'     => $createdDate . ' 08:30:00',
            'updated_at'     => now(),
        ]);

        $i = 0;
        foreach ($workers as $name) {
            $w = Database::one(
                'SELECT id FROM workers WHERE account_id = ? AND name = ?',
                [$accountId, $name]
            );
            Database::insert('report_workers', [
                'report_id'  => $reportId,
                'worker_id'  => $w['id'] ?? null,
                'name'       => $name,
                'sort_order' => ++$i * 10,
            ]);
        }

        if ($isDraft) {
            continue;
        }

        foreach (array_slice($models, 0, 3) as $k => $m) {
            Database::insert('report_models', [
                'report_id'  => $reportId,
                'model_id'   => $m['id'],
                'model_name' => $m['name'],
                'qty'        => [3, 2, 1][$k],
                'sort_order' => ($k + 1) * 10,
            ]);
        }

        foreach (array_slice($parts, 0, 5) as $k => $p) {
            Database::insert('report_parts', [
                'report_id'  => $reportId,
                'part_id'    => $p['id'],
                'part_name'  => $p['name'],
                'unit'       => $p['unit'],
                'qty'        => [2, 5, 1, 9, 4][$k],
                'sort_order' => ($k + 1) * 10,
            ]);
        }

        $rooms = ['BCR1', 'BCR2', 'BCR3', 'BCR4', 'BCR5'];
        foreach ($rooms as $k => $room) {
            Database::insert('report_measurements', [
                'report_id'        => $reportId,
                'room_name'        => $room,
                'model_name'       => $models[$k % count($models)]['name'],
                'cumulative_hours' => 12000 + $k * 3175,
                'serial_no'        => sprintf('%06d', 204100 + $no % 100 * 7 + $k),
                'manufactured_ym'  => sprintf('20%02d-%02d', 18 + $k, 3 + $k),
                'sort_order'       => ($k + 1) * 10,
            ]);
        }

        if ($isCompleted) {
            $internalId = Database::insert('internal_reports', [
                'report_id'        => $reportId,
                'created_date'     => $createdDate,
                'hospital_name'    => $hospital,
                'work_date'        => $workDate,
                'work_place'       => $place,
                'workers_text'    => implode('、', $workers),
                'work_title'       => $title,
                'remaining_work'   => "HEPAフィルターは次回定期時に交換予定。\n入口扉クローザの調整は部材待ち。",
                'travel_out_from'  => '07:30',
                'travel_out_to'    => '09:00',
                'work_from'        => '09:00',
                'work_to'          => '16:30',
                'travel_back_from' => '16:30',
                'travel_back_to'   => '18:00',
                'sales_approach'   => '中央棟の空調更新について、来期予算での検討状況をヒアリング。次回訪問時に概算をご提示予定。',
                'remarks'          => 'client立会は設備課 主任。次回は事前に入室許可申請が必要。',
                'pdf_at'           => $createdDate . ' 17:40:00',
                'completed_at'     => $createdDate . ' 18:05:00',
                'created_at'       => $createdDate . ' 17:00:00',
                'updated_at'       => now(),
            ]);

            foreach (array_slice($parts, 0, 3) as $k => $p) {
                Database::insert('internal_report_parts', [
                    'internal_report_id' => $internalId,
                    'part_id'            => $p['id'],
                    'part_name'          => $p['name'],
                    'unit'               => $p['unit'],
                    'qty'                => [1, 2, 1][$k],
                    'sort_order'         => ($k + 1) * 10,
                ]);
            }
        }

        for ($m = 0; $m < $mailCount; $m++) {
            Database::insert('mail_logs', [
                'report_id'  => $reportId,
                'kind'       => 'report',
                'to_addr'    => 'setsubi@example-hospital.jp',
                'cc_addr'    => 'jimukyoku@example.co.jp',
                'subject'    => '作業完了報告書',
                'body'       => "お世話になっております。\n作業完了報告書をお送りいたします。",
                'is_dry_run' => 1,
                'sent_at'    => $createdDate . ' 1' . (2 + $m) . ':00:00',
            ]);
        }
    }
}
