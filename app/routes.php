<?php
declare(strict_types=1);

$controller = function (string $file, string $fn) {
    return function (array $params = []) use ($file, $fn) {
        require_once APP_ROOT . '/app/controllers/' . $file . '.php';
        $fn($params);
    };
};

if (config('debug')) {
    $router->get('/_dev', $controller('dev', 'dev_index'));
}

$router->get('/', function () {
    redirect(Auth::user() ? '/dashboard' : '/login');
});

$router->any('/login',      $controller('auth', 'login_page'));
$router->post('/logout',    $controller('auth', 'logout_action'));
$router->get('/dashboard',  $controller('dashboard', 'dashboard_page'));

$router->any('/report/new',                $controller('report', 'report_new'));
$router->any('/report/{id}/basic',         $controller('report', 'report_basic'));
$router->any('/report/{id}/work',          $controller('report', 'report_work'));
$router->any('/report/{id}/parts',         $controller('report', 'report_parts'));
$router->any('/report/{id}/measure',       $controller('report', 'report_measure'));
$router->any('/report/{id}/confirm',       $controller('report', 'report_confirm'));
$router->any('/report/{id}/sign',          $controller('report', 'report_sign'));
$router->get('/report/{id}/signature.png', $controller('report', 'report_signature_image'));
$router->post('/report/{id}/signature/delete', $controller('report', 'report_delete_signature'));

$router->get('/report/{id}/done',    $controller('report_output', 'report_done'));
$router->get('/report/{id}/sheet',   $controller('report_output', 'report_sheet'));
$router->get('/report/{id}/pdf',     $controller('report_output', 'report_pdf'));
$router->get('/report/{id}/preview', $controller('report_output', 'report_preview'));
$router->get('/report/{id}/print',   $controller('report_output', 'report_print'));
$router->any('/report/{id}/mail',    $controller('report_output', 'report_mail'));

$router->get('/reports',       $controller('report_list', 'report_list'));
$router->post('/reports/copy', $controller('report_list', 'report_copy'));

$router->any('/mypage',         $controller('mypage', 'mypage_user'));
$router->any('/mypage/workers', $controller('mypage', 'mypage_workers'));
$router->any('/mypage/texts',   $controller('mypage', 'mypage_texts'));

$router->get('/report/{id}/internal',          $controller('internal', 'internal_entry'));
$router->any('/report/{id}/internal/basic',    $controller('internal', 'internal_basic'));
$router->any('/report/{id}/internal/remain',   $controller('internal', 'internal_remain'));
$router->any('/report/{id}/internal/parts',    $controller('internal', 'internal_parts'));
$router->any('/report/{id}/internal/hours',    $controller('internal', 'internal_hours'));
$router->any('/report/{id}/internal/sales',    $controller('internal', 'internal_sales'));
$router->get('/report/{id}/internal/confirm',  $controller('internal', 'internal_confirm'));
$router->post('/report/{id}/internal/complete', $controller('internal', 'internal_complete'));
$router->get('/report/{id}/internal/sheet',    $controller('internal', 'internal_sheet'));
$router->get('/report/{id}/internal/pdf',      $controller('internal', 'internal_pdf'));
$router->get('/report/{id}/internal/preview',  $controller('internal', 'internal_preview'));
$router->get('/report/{id}/internal/print',    $controller('internal', 'internal_print'));

$router->get('/admin', function () {
    redirect(Auth::admin() ? '/admin/dashboard' : '/admin/login');
});

$router->any('/admin/login',      $controller('admin_auth', 'admin_login_page'));
$router->post('/admin/logout',    $controller('admin_auth', 'admin_logout_action'));
$router->get('/admin/dashboard', $controller('admin_dashboard', 'admin_dashboard'));
$router->get('/admin/report/{id}/sheet',          $controller('admin_dashboard', 'admin_report_sheet'));
$router->get('/admin/report/{id}/internal-sheet', $controller('admin_dashboard', 'admin_internal_sheet'));
$router->get('/admin/report/{id}/pdf',            $controller('admin_dashboard', 'admin_report_pdf'));
$router->get('/admin/report/{id}/internal-pdf',   $controller('admin_dashboard', 'admin_internal_pdf'));
$router->get('/admin/report/{id}/signature.png',  $controller('admin_dashboard', 'admin_signature_image'));

$router->get('/admin/users',         $controller('admin_users', 'admin_users'));
$router->post('/admin/users/save',   $controller('admin_users', 'admin_users_save'));
$router->post('/admin/users/unlock', $controller('admin_users', 'admin_users_unlock'));

$router->get('/admin/parts',                $controller('admin_parts', 'admin_parts'));
$router->post('/admin/parts/save',          $controller('admin_parts', 'admin_parts_save'));
$router->post('/admin/parts/delete',        $controller('admin_parts', 'admin_parts_delete'));
$router->get('/admin/parts/download',       $controller('admin_parts', 'admin_parts_download'));
$router->post('/admin/parts/import',        $controller('admin_parts', 'admin_parts_import'));
$router->post('/admin/parts/import/apply',  $controller('admin_parts', 'admin_parts_import_apply'));
$router->post('/admin/parts/import/cancel', $controller('admin_parts', 'admin_parts_import_cancel'));

$router->get('/admin/models',         $controller('admin_masters', 'admin_models'));
$router->post('/admin/models/save',   $controller('admin_masters', 'admin_models_save'));
$router->post('/admin/models/delete', $controller('admin_masters', 'admin_models_delete'));

$router->get('/admin/texts',         $controller('admin_masters', 'admin_texts'));
$router->post('/admin/texts/save',   $controller('admin_masters', 'admin_texts_save'));
$router->post('/admin/texts/delete', $controller('admin_masters', 'admin_texts_delete'));

$router->get('/admin/checks',         $controller('admin_checks', 'admin_checks'));
$router->post('/admin/checks/save',   $controller('admin_checks', 'admin_checks_save'));
$router->post('/admin/checks/delete', $controller('admin_checks', 'admin_checks_delete'));

$router->any('/admin/profile', $controller('admin_masters', 'admin_profile'));
