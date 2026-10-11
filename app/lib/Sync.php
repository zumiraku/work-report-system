<?php
// 圏外で端末に溜めた操作が二重に届くのを防ぐ。
// op_id を台帳に入れるだけ。入らなかった＝既に処理済みなので duplicate を返す。
declare(strict_types=1);

final class Sync
{
    public static function guardReplay(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $opId = trim((string) ($_POST['op_id'] ?? ''));
        if ($opId === '' || !preg_match('/^[0-9A-Za-z_-]{8,64}$/', $opId)) {
            return;
        }

        try {
            Database::insert('sync_ops', [
                'op_id'      => $opId,
                'account_id' => $_SESSION['account_id'] ?? null,
                'path'       => mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
                'created_at' => now(),
            ]);
        } catch (PDOException $e) {
            // ux_sync_ops_op_id に当たった＝受付済み。黙って成功扱いにする
            json_out([
                'status' => 'duplicate',
                'op_id'  => $opId,
            ]);
        }
    }
}
