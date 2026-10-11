<?php
declare(strict_types=1);

function dashboard_page(): void
{
    $user = Auth::requireUser();

    $draft = Database::one(
        "SELECT id, report_no, hospital_name, work_date, updated_at
           FROM reports
          WHERE account_id = ? AND status = 'draft' AND deleted_at IS NULL
          ORDER BY updated_at DESC",
        [$user['id']]
    );

    $reportCount = (int) Database::value(
        'SELECT COUNT(*) FROM reports WHERE account_id = ? AND deleted_at IS NULL',
        [$user['id']]
    );

    view('user/dashboard', [
        'user'        => $user,
        'draft'       => $draft,
        'reportCount' => $reportCount,
        'autoLogin'   => !empty($_SESSION['auto_login']),
        'title'       => 'ダッシュボード',
    ], 'layout_user');
}
