<?php
declare(strict_types=1);

function login_page(): void
{
    if (Auth::user()) {
        redirect('/dashboard');
    }

    if (Auth::tryRemember('user')) {
        redirect('/dashboard');
    }

    $error   = null;
    $loginId = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();

        $loginId  = trim((string) post('login_id', ''));
        $password = (string) post('password', '');
        $remember = post('remember') === '1';

        if ($loginId === '' || $password === '') {
            $error = 'ユーザーIDとパスワードを入力してください。';
        } elseif (login_ip_blocked()) {
            $error = 'ログインの失敗が続いています。しばらく時間をおいてからお試しください。';
        } else {
            $result = Auth::attemptUser($loginId, $password, $remember);
            if ($result['ok']) {
                redirect('/dashboard');
            }
            $error = $result['error'];
        }
    }

    view('user/login', [
        'error'   => $error,
        'loginId' => $loginId,
        'title'   => 'ユーザーログイン',
    ]);
}

function logout_action(): void
{
    csrf_check();
    Auth::logout('user');
    redirect('/login');
}

function login_ip_blocked(int $limit = 10, int $minutes = 10): bool
{
    $since = date('Y-m-d H:i:s', time() - $minutes * 60);
    $n = (int) Database::value(
        'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND succeeded = 0 AND created_at > ?',
        [client_ip(), $since]
    );
    return $n >= $limit;
}
