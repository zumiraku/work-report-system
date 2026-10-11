<?php
// メール送信。さくらは mail() がそのまま sendmail に流れるので、PHPMailer等は入れずに
// MIME だけ自前で組んでいる。dry_run の間は送らず .eml を data/tmp/mail に残す。
declare(strict_types=1);

final class Mailer
{
    public static function send(array $msg): ?string
    {
        $mail = self::build($msg);

        if (config('mail.dry_run')) {
            self::keepCopy($mail);
            return null;
        }

        $from = (string) config('mail.from_address');
        $ok   = @mail(
            $mail['to'],
            $mail['subject'],
            $mail['body'],
            $mail['headers'],
            preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+$/', $from) ? '-f ' . $from : ''
        );

        if (!$ok) {
            $err = error_get_last();
            return 'メールを送信できませんでした。'
                . ($err && config('debug') ? '（' . $err['message'] . '）' : '');
        }

        return null;
    }

    public static function build(array $msg): array
    {
        $boundary = '=_wcr_' . bin2hex(random_bytes(12));
        $eol      = "\n";

        $headers = [
            'From'         => self::address((string) config('mail.from_address'), (string) config('mail.from_name')),
            'MIME-Version' => '1.0',
            'Content-Type' => 'multipart/mixed; boundary="' . $boundary . '"',
            'X-Mailer'     => self::encodeWord((string) config('app_name')),
        ];

        $cc = array_values(array_filter((array) ($msg['cc'] ?? []), fn($a) => (string) $a !== ''));
        if ($cc) {
            $headers['Cc'] = implode(', ', array_map(fn($a) => self::address((string) $a), $cc));
        }
        if (!empty($msg['reply_to'])) {
            $headers['Reply-To'] = self::address((string) $msg['reply_to']);
        }

        // 本文は base64。日本語が配信経路のどこかで化けるのを避ける
        $text = str_replace(["\r\n", "\r"], "\n", (string) ($msg['body'] ?? ''));
        $body = '--' . $boundary . $eol
            . 'Content-Type: text/plain; charset=UTF-8' . $eol
            . 'Content-Transfer-Encoding: base64' . $eol . $eol
            . chunk_split(base64_encode($text), 76, $eol);

        foreach ((array) ($msg['attachments'] ?? []) as $file) {
            $name = (string) ($file['name'] ?? 'attachment.bin');
            $type = (string) ($file['type'] ?? 'application/octet-stream');
            $body .= '--' . $boundary . $eol
                . 'Content-Type: ' . $type . '; name="' . self::encodeWord($name, false) . '"' . $eol
                . 'Content-Transfer-Encoding: base64' . $eol
                . 'Content-Disposition: attachment; filename="' . self::encodeWord($name, false) . '";'
                . $eol . " filename*=UTF-8''" . rawurlencode($name) . $eol . $eol
                . chunk_split(base64_encode((string) ($file['bytes'] ?? '')), 76, $eol);
        }
        $body .= '--' . $boundary . '--' . $eol;

        $to      = self::address((string) $msg['to']);
        $subject = self::encodeWord((string) ($msg['subject'] ?? ''));

        $raw = 'To: ' . $to . $eol . 'Subject: ' . $subject . $eol
            . 'Date: ' . date('r') . $eol;
        foreach ($headers as $k => $v) {
            $raw .= $k . ': ' . $v . $eol;
        }
        $raw .= $eol . $body;

        return [
            'to'      => $to,
            'subject' => $subject,
            'body'    => $body,
            'headers' => $headers,
            'raw'     => $raw,
        ];
    }

    private static function keepCopy(array $mail): void
    {
        $dir = (string) config('storage.tmp') . '/mail';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $raw = (string) preg_replace('/\r?\n/', "\r\n", $mail['raw']);
        @file_put_contents($dir . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.eml', $raw);
    }

    public static function address(string $address, string $name = ''): string
    {
        $address = trim($address);
        $name    = trim($name);
        if ($name === '') {
            return $address;
        }

        return self::encodeWord($name) . ' <' . $address . '>';
    }

    // ヘッダ用の =?UTF-8?B?...?=。折り返しは CRLF+空白でないと mail() が通さない。
    // $fold = false は引用符の中（添付ファイル名）用で、折り返さない。
    public static function encodeWord(string $s, bool $fold = true): string
    {
        if ($s === '' || !preg_match('/[^\x20-\x7E]/', $s)) {
            return $s;
        }
        if (!$fold) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }

        $words = [];
        $chunk = '';
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if (strlen($chunk . $ch) > 42) {
                $words[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
                $chunk   = '';
            }
            $chunk .= $ch;
        }
        if ($chunk !== '') {
            $words[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
        }

        return implode("\r\n ", $words);
    }
}
