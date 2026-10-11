<?php
declare(strict_types=1);

function report_done(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);

    view('user/report_done', [
        'report'    => $report,
        'mailCount' => (int) Database::value(
            'SELECT COUNT(*) FROM mail_logs WHERE report_id = ?', [$report['id']]
        ),
        'title'     => '完了',
    ], 'layout_user');
}

function report_sheet(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);

    $data = Report::sheetData($report);

    if (!$report['pdf_at']) {
        Report::touch((int) $report['id'], ['pdf_at' => now()]);
        $report['pdf_at'] = now();
        $data['report']   = $report;
    }

    view('sheet/report', $data + [
        'density'  => Report::sheetDensity($data),
        'forPrint' => query('print') === '1',
    ]);
}

// 一覧の●・プレビューの「PDFで開く」・メール添付が全部ここを通る。
// ?dl=1 で保存。作るたび data/pdf/report_{No}.pdf を最新に書き換える。
function report_pdf(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);
    $id     = (int) $report['id'];

    $bytes = Pdf::forReport($report);
    $file  = Pdf::store($bytes, 'report', (int) $report['report_no']);

    Report::touch($id, [
        'pdf_at'   => $report['pdf_at'] ?: now(),
        'pdf_file' => $file,
    ]);
    audit('report_pdf', 'reports:' . $id, $file);

    Pdf::send($bytes, Pdf::fileName('report', $report), query('dl') === '1', Pdf::fileName('report', $report, true));
}

function report_preview(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);

    view('user/report_preview', [
        'report' => $report,
        'mode'   => 'preview',
        'title'  => 'プレビュー',
    ]);
}

function report_print(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);

    view('user/report_preview', [
        'report' => $report,
        'mode'   => 'print',
        'title'  => '印刷',
    ]);
}

function report_mail(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);
    $id     = (int) $report['id'];

    $last = Database::one(
        'SELECT * FROM mail_logs WHERE report_id = ? ORDER BY id DESC', [$id]
    );

    $form = [
        'to'      => (string) ($last['to_addr'] ?? ''),
        'subject' => (string) ($last['subject'] ?? config('mail.default_subject')),
        'cc'      => (string) ($last['cc_addr'] ?? (string) $user['email']),
        'body'    => (string) ($last['body'] ?? report_mail_template($report, $user)),
    ];
    $errors = [];
    $sent   = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();

        $form = [
            'to'      => trim((string) post('to', '')),
            'subject' => trim((string) post('subject', '')),
            'cc'      => trim((string) post('cc', '')),
            'body'    => (string) post('body', ''),
        ];

        $errors = report_validate_mail($form);

        if (!$errors) {
            // 送った控えは日時付きで残す。後から「何を送ったか」を確かめられるように
            $bytes = Pdf::forReport($report);
            $file  = Pdf::store($bytes, 'report', (int) $report['report_no'], true);

            $failed = Mailer::send([
                'to'          => $form['to'],
                'cc'          => report_split_addresses($form['cc']),
                'reply_to'    => (string) $user['email'],
                'subject'     => $form['subject'],
                'body'        => $form['body'],
                'attachments' => [[
                    'name'  => Pdf::fileName('report', $report),
                    'bytes' => $bytes,
                    'type'  => 'application/pdf',
                ]],
            ]);

            if ($failed !== null) {
                $errors['send'] = $failed . ' 時間をおいてもう一度お試しください。';
            } else {
                Database::insert('mail_logs', [
                    'report_id'  => $id,
                    'kind'       => 'report',
                    'to_addr'    => mb_substr($form['to'], 0, 255),
                    'cc_addr'    => mb_substr($form['cc'], 0, 512),
                    'subject'    => mb_substr($form['subject'], 0, 255),
                    'body'       => mb_substr($form['body'], 0, 8000),
                    'attachment' => $file,
                    'is_dry_run' => config('mail.dry_run') ? 1 : 0,
                    'sent_at'    => now(),
                ]);

                Report::touch($id, [
                    'mail_count' => (int) $report['mail_count'] + 1,
                    'pdf_at'     => $report['pdf_at'] ?: now(),
                    'pdf_file'   => $file,
                ]);

                audit('report_mailed', 'reports:' . $id, $form['to'] . ' ' . $file);
                $sent   = true;
                $report = Report::findOwned($id, $user);
            }
        }
    }

    view('user/report_mail', [
        'report' => $report,
        'form'   => $form,
        'errors' => $errors,
        'sent'   => $sent,
        'title'  => 'メール送信',
    ]);
}

function report_validate_mail(array $f): array
{
    $e = [];

    if ($f['to'] === '') {
        $e['to'] = '送信先のメールアドレスを入れてください。';
    } elseif (!report_is_email($f['to'])) {
        $e['to'] = 'メールアドレスの形式が正しくありません（例：taro@example.co.jp）。';
    }

    if ($f['subject'] === '') {
        $e['subject'] = '件名を入れてください。';
    }

    foreach (report_split_addresses($f['cc']) as $cc) {
        if (!report_is_email($cc)) {
            $e['cc'] = '「' . $cc . '」はメールアドレスの形式になっていません。';
            break;
        }
    }

    return $e;
}

function report_is_email(string $address): bool
{
    return (bool) filter_var($address, FILTER_VALIDATE_EMAIL);
}

function report_split_addresses(string $text): array
{
    return array_values(array_filter(array_map(
        'trim',
        preg_split('/[,、;\s]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []
    ), fn($s) => $s !== ''));
}

function report_mail_template(array $report, array $user): string
{
    return implode("\n", [
        (string) $report['hospital_name'] . ' 御中',
        '',
        'いつもお世話になっております。',
        (string) $user['company_name'] . 'です。',
        '',
        sprintf(
            '%s に実施いたしました作業の完了報告書をお送りいたします。',
            ymd_ja((string) $report['work_date'], false)
        ),
        '',
        '　作業場所：' . (string) $report['work_place'],
        '　作業件名：' . (string) $report['work_title'],
        '',
        'ご確認のほど、よろしくお願いいたします。',
    ]);
}
