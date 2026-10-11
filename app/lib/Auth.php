<?php
// ログイン。「ID/パスワードを保持」はパスワードを持たせず、
// 使い捨ての自動ログイン用トークン（selector + validator のハッシュ）を Cookie に置く方式。
// 使うたび作り替えるので、古いトークンを拾われても通らない。
declare(strict_types=1);

final class Auth
{
    private const REMEMBER_COOKIE = ['user' => 'wcr_keep', 'admin' => 'wcr_keep_admin'];
    private const REMEMBER_DAYS   = 90;

    private static function issueRemember(string $kind, int $actorId): void
    {
        $selector  = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(24));
        $expires   = time() + self::REMEMBER_DAYS * 86400;

        Database::insert('remember_tokens', [
            'actor_kind'     => $kind,
            'actor_id'       => $actorId,
            'selector'       => $selector,
            'validator_hash' => hash('sha256', $validator),
            'user_agent'     => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'expires_at'     => date('Y-m-d H:i:s', $expires),
            'last_used_at'   => null,
            'created_at'     => now(),
        ]);

        self::setRememberCookie($kind, $selector . ':' . $validator, $expires);
    }

    private static function setRememberCookie(string $kind, string $value, int $expires): void
    {
        setcookie(self::REMEMBER_COOKIE[$kind], $value, [
            'expires'  => $expires,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
        ]);
    }

    public static function tryRemember(string $kind): bool
    {
        $raw = $_COOKIE[self::REMEMBER_COOKIE[$kind]] ?? '';
        if ($raw === '' || !str_contains($raw, ':')) {
            return false;
        }
        [$selector, $validator] = explode(':', $raw, 2);

        $token = Database::one(
            'SELECT * FROM remember_tokens WHERE selector = ? AND actor_kind = ?',
            [$selector, $kind]
        );
        if (!$token || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            self::forgetRemember($kind);
            return false;
        }
        if (strtotime($token['expires_at']) < time()) {
            Database::run('DELETE FROM remember_tokens WHERE id = ?', [$token['id']]);
            self::forgetRemember($kind);
            return false;
        }

        $table = $kind === 'admin' ? 'admins' : 'accounts';
        $actor = Database::one("SELECT * FROM {$table} WHERE id = ?", [$token['actor_id']]);
        if (!$actor || ($kind === 'user' && (int) $actor['is_locked'] === 1)) {
            Database::run('DELETE FROM remember_tokens WHERE id = ?', [$token['id']]);
            self::forgetRemember($kind);
            return false;
        }

        $next    = bin2hex(random_bytes(24));
        $expires = time() + self::REMEMBER_DAYS * 86400;
        Database::update('remember_tokens', [
            'validator_hash' => hash('sha256', $next),
            'expires_at'     => date('Y-m-d H:i:s', $expires),
            'last_used_at'   => now(),
        ], 'id = :id', ['id' => $token['id']]);
        self::setRememberCookie($kind, $selector . ':' . $next, $expires);

        session_regenerate_id(true);
        if ($kind === 'admin') {
            $_SESSION['admin_id']    = (int) $actor['id'];
            $_SESSION['admin_login'] = $actor['account_id'];
        } else {
            $_SESSION['account_id']    = (int) $actor['id'];
            $_SESSION['account_login'] = $actor['account_id'];
        }
        $_SESSION['auto_login'] = true;
        audit('auto_login', $table . ':' . $actor['account_id']);

        return true;
    }

    private static function forgetRemember(string $kind): void
    {
        $raw = $_COOKIE[self::REMEMBER_COOKIE[$kind]] ?? '';
        if ($raw !== '' && str_contains($raw, ':')) {
            Database::run(
                'DELETE FROM remember_tokens WHERE selector = ? AND actor_kind = ?',
                [explode(':', $raw, 2)[0], $kind]
            );
        }
        self::setRememberCookie($kind, '', time() - 3600);
        unset($_COOKIE[self::REMEMBER_COOKIE[$kind]]);
    }

    private static function revokeAllRemember(string $kind, int $actorId): void
    {
        Database::run(
            'DELETE FROM remember_tokens WHERE actor_kind = ? AND actor_id = ?',
            [$kind, $actorId]
        );
    }

    public static function user(): ?array
    {
        $id = $_SESSION['account_id'] ?? null;
        if (!$id) {
            return null;
        }
        return Database::one('SELECT * FROM accounts WHERE id = ?', [$id]);
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) {
            redirect('/login');
        }
        return $user;
    }

    public static function attemptUser(string $loginId, string $password, bool $remember = false): array
    {
        $result = self::evaluateUser($loginId, $password, $remember);

        Database::insert('login_attempts', [
            'login_kind' => 'user',
            'account_id' => $loginId,
            'ip'         => client_ip(),
            'succeeded'  => $result['ok'] ? 1 : 0,
            'created_at' => now(),
        ]);

        return $result;
    }

    private static function evaluateUser(string $loginId, string $password, bool $remember): array
    {
        $account = Database::one('SELECT * FROM accounts WHERE account_id = ?', [$loginId]);

        if ($account) {
            $account = self::autoUnlock($account);
        }

        $ok = $account && password_verify($password, $account['password_hash']);

        if (!$account) {
            return ['ok' => false, 'error' => 'ユーザーIDまたはパスワードが違います。'];
        }

        if ((int) $account['is_locked'] === 1) {
            return [
                'ok'    => false,
                'error' => 'このユーザーIDはロックされています。解除は事務局にご連絡ください。',
            ];
        }

        if (!$ok) {
            $failed = (int) $account['failed_count'] + 1;
            $max    = (int) config('login_max_fail', 3);
            $lock   = $failed >= $max;

            Database::update('accounts', [
                'failed_count' => $failed,
                'is_locked'    => $lock ? 1 : 0,
                'locked_at'    => $lock ? now() : null,
                'updated_at'   => now(),
            ], 'id = :id', ['id' => $account['id']]);

            if ($lock) {
                self::revokeAllRemember('user', (int) $account['id']);
                audit('login_locked', 'accounts:' . $account['account_id']);
                return [
                    'ok'    => false,
                    'error' => "パスワードを{$max}回間違えたため、ロックしました。解除は事務局にご連絡ください。",
                ];
            }

            $rest = $max - $failed;
            return [
                'ok'    => false,
                'error' => "ユーザーIDまたはパスワードが違います。（あと{$rest}回間違えるとロックされます）",
            ];
        }

        Database::update('accounts', [
            'failed_count'  => 0,
            'last_login_at' => now(),
            'updated_at'    => now(),
        ], 'id = :id', ['id' => $account['id']]);

        session_regenerate_id(true);
        $_SESSION['account_id']    = (int) $account['id'];
        $_SESSION['account_login'] = $account['account_id'];
        audit('login', 'accounts:' . $account['account_id']);

        if ($remember) {
            self::issueRemember('user', (int) $account['id']);
        } else {
            self::forgetRemember('user');
        }

        return ['ok' => true];
    }

    private static function autoUnlock(array $account): array
    {
        $minutes = (int) config('auto_unlock_minutes', 0);

        if ($minutes <= 0 || (int) $account['is_locked'] !== 1 || !$account['locked_at']) {
            return $account;
        }
        if (strtotime((string) $account['locked_at']) > time() - $minutes * 60) {
            return $account;
        }

        Database::update('accounts', [
            'is_locked'    => 0,
            'failed_count' => 0,
            'locked_at'    => null,
            'updated_at'   => now(),
        ], 'id = :id', ['id' => $account['id']]);

        audit('login_auto_unlocked', 'accounts:' . $account['account_id'], "{$minutes}分経過");

        $account['is_locked']    = 0;
        $account['failed_count'] = 0;
        $account['locked_at']    = null;

        return $account;
    }

    public static function admin(): ?array
    {
        $id = $_SESSION['admin_id'] ?? null;
        if (!$id) {
            return null;
        }
        return Database::one('SELECT * FROM admins WHERE id = ?', [$id]);
    }

    public static function requireAdmin(): array
    {
        $admin = self::admin();
        if (!$admin) {
            redirect('/admin/login');
        }
        return $admin;
    }

    public static function attemptAdmin(string $loginId, string $password, bool $remember = false): array
    {
        $admin = Database::one('SELECT * FROM admins WHERE account_id = ?', [$loginId]);
        $ok    = $admin && password_verify($password, $admin['password_hash']);

        Database::insert('login_attempts', [
            'login_kind' => 'admin',
            'account_id' => $loginId,
            'ip'         => client_ip(),
            'succeeded'  => $ok ? 1 : 0,
            'created_at' => now(),
        ]);

        if (!$ok) {
            return ['ok' => false, 'error' => 'ユーザーIDまたはパスワードが違います。'];
        }

        session_regenerate_id(true);
        $_SESSION['admin_id']    = (int) $admin['id'];
        $_SESSION['admin_login'] = $admin['account_id'];
        audit('admin_login', 'admins:' . $admin['account_id']);

        if ($remember) {
            self::issueRemember('admin', (int) $admin['id']);
        } else {
            self::forgetRemember('admin');
        }

        return ['ok' => true];
    }

    public static function logout(string $kind = 'user'): void
    {
        audit($kind === 'admin' ? 'admin_logout' : 'logout');
        self::forgetRemember($kind);
        $_SESSION = [];
        session_destroy();
    }
}
