<?php
declare(strict_types=1);

const ADMIN_PER_PAGE = 100;

function admin_sort(array $sorts, string $default, string $defaultDir = 'DESC'): array
{
    $sort = (string) query('sort', $default);
    if (!isset($sorts[$sort])) {
        $sort = $default;
    }
    $dir = strtoupper((string) query('dir', $defaultDir));
    if ($dir !== 'ASC' && $dir !== 'DESC') {
        $dir = $defaultDir;
    }
    return [$sort, $dir];
}

function admin_pager(int $total, int $perPage = ADMIN_PER_PAGE): array
{
    $pages  = max(1, (int) ceil($total / $perPage));
    $page   = min(max(1, (int) query('page', 1)), $pages);
    $offset = ($page - 1) * $perPage;

    return [
        'page'   => $page,
        'pages'  => $pages,
        'offset' => $offset,
        'from'   => $total === 0 ? 0 : $offset + 1,
        'to'     => min($offset + $perPage, $total),
        'total'  => $total,
    ];
}

function admin_sort_link(string $base, array $keep, string $key, string $label, string $sort, string $dir): string
{
    $next = ($sort === $key && $dir === 'ASC') ? 'desc' : 'asc';
    $mark = $sort !== $key ? '▼' : ($dir === 'ASC' ? '▲' : '▼');

    return sprintf(
        '<a href="%s?%s"%s>%s%s</a>',
        h($base),
        h(http_build_query($keep + ['sort' => $key, 'dir' => $next])),
        $sort === $key ? ' class="is-sorted"' : '',
        h($label),
        $mark
    );
}

function admin_page_url(string $base, array $keep, int $page): string
{
    return $base . '?' . http_build_query($keep + ['page' => $page]);
}

function admin_find_report(int $id): array
{
    $report = Database::one(
        'SELECT r.*, a.account_id AS login_id, a.company_name
           FROM reports r
           JOIN accounts a ON a.id = r.account_id
          WHERE r.id = ? AND r.deleted_at IS NULL',
        [$id]
    );
    if (!$report) {
        render_error(404, '報告書が見つかりません。');
        exit;
    }
    return $report;
}
