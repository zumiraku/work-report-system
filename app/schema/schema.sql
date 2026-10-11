-- {{PK}} / {{TAIL}} は tools/migrate.php がドライバに応じて置き換える。
-- VARCHAR / DATETIME / TINYINT は SQLite でもそのまま通るので統一して使っている。

-- ============================================================
-- 認証・組織
-- ============================================================

CREATE TABLE admins (
  id            {{PK}},
  account_id    VARCHAR(64)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  notify_email  VARCHAR(255) NULL,
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL
){{TAIL}};
CREATE UNIQUE INDEX ux_admins_account_id ON admins (account_id);

-- 利用者アカウント。概要書どおり「各作業人ではなく会社毎」に発行する
CREATE TABLE accounts (
  id            {{PK}},
  account_id    VARCHAR(64)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  company_name  VARCHAR(255) NOT NULL,
  email         VARCHAR(255) NULL,
  failed_count  INT          NOT NULL DEFAULT 0,   -- 3回でロック
  is_locked     TINYINT      NOT NULL DEFAULT 0,   -- 解除は事務局のみ
  locked_at     DATETIME     NULL,
  last_login_at DATETIME     NULL,
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL
){{TAIL}};
CREATE UNIQUE INDEX ux_accounts_account_id ON accounts (account_id);

-- アカウント単位のロックとは別に、同一IPからの総当たりも見るための記録
CREATE TABLE login_attempts (
  id          {{PK}},
  login_kind  VARCHAR(16)  NOT NULL,          -- user | admin
  account_id  VARCHAR(64)  NULL,
  ip          VARCHAR(64)  NULL,
  succeeded   TINYINT      NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL
){{TAIL}};
CREATE INDEX ix_login_attempts_ip ON login_attempts (ip, created_at);

-- 「ID・パスワードを保持」の実体。パスワードは端末にもサーバーにも残さず、
-- 使い捨ての自動ログイン用トークンを置く。使うたび作り替えるので古いものは通らない。
CREATE TABLE remember_tokens (
  id             {{PK}},
  actor_kind     VARCHAR(16)  NOT NULL,          -- user | admin
  actor_id       INT          NOT NULL,
  selector       VARCHAR(32)  NOT NULL,
  validator_hash VARCHAR(255) NOT NULL,
  user_agent     VARCHAR(255) NULL,
  expires_at     DATETIME     NOT NULL,
  last_used_at   DATETIME     NULL,
  created_at     DATETIME     NOT NULL
){{TAIL}};
CREATE UNIQUE INDEX ux_remember_selector ON remember_tokens (selector);
CREATE INDEX ix_remember_actor ON remember_tokens (actor_kind, actor_id);

-- 圏外で端末に溜めた操作の受付台帳。同じ op_id が二度届いても一度しか処理しない
CREATE TABLE sync_ops (
  id         {{PK}},
  op_id      VARCHAR(64)  NOT NULL,
  account_id INT          NULL,
  path       VARCHAR(255) NULL,
  created_at DATETIME     NOT NULL
){{TAIL}};
CREATE UNIQUE INDEX ux_sync_ops_op_id ON sync_ops (op_id);

CREATE TABLE audit_logs (
  id          {{PK}},
  actor_kind  VARCHAR(16)  NOT NULL,          -- user | admin | system
  actor_id    VARCHAR(64)  NULL,
  action      VARCHAR(64)  NOT NULL,
  target      VARCHAR(128) NULL,
  detail      TEXT         NULL,
  ip          VARCHAR(64)  NULL,
  created_at  DATETIME     NOT NULL
){{TAIL}};
CREATE INDEX ix_audit_logs_created ON audit_logs (created_at);

-- ============================================================
-- マスタ
-- ============================================================

CREATE TABLE workers (
  id         {{PK}},
  account_id INT          NOT NULL,
  name       VARCHAR(128) NOT NULL,
  kana       VARCHAR(128) NULL,
  created_at DATETIME     NOT NULL,
  updated_at DATETIME     NOT NULL,
  deleted_at DATETIME     NULL
){{TAIL}};
CREATE INDEX ix_workers_account ON workers (account_id, deleted_at);

CREATE TABLE machine_models (
  id         {{PK}},
  name       VARCHAR(128) NOT NULL,
  kana       VARCHAR(128) NULL,
  sort_order INT          NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL,
  updated_at DATETIME     NOT NULL,
  deleted_at DATETIME     NULL
){{TAIL}};
CREATE INDEX ix_machine_models_sort ON machine_models (sort_order, id);

-- 1万点規模。kana はこちらからお願いして足してもらった列で、無いと50音順に並べられない
CREATE TABLE parts (
  id         {{PK}},
  name       VARCHAR(191) NOT NULL,
  kana       VARCHAR(191) NULL,
  unit       VARCHAR(16)  NOT NULL DEFAULT '個',
  priority   INT          NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL,
  updated_at DATETIME     NOT NULL,
  deleted_at DATETIME     NULL
){{TAIL}};
CREATE UNIQUE INDEX ux_parts_name ON parts (name);          -- 重複エラー検知用
CREATE INDEX ix_parts_kana ON parts (kana);                 -- 50音ソート
CREATE INDEX ix_parts_priority ON parts (priority, id);

CREATE TABLE report_texts (
  id         {{PK}},
  account_id INT          NULL,                 -- NULL = 全社共通（事務局登録）
  body       TEXT         NOT NULL,
  sort_order INT          NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL,
  updated_at DATETIME     NOT NULL,
  deleted_at DATETIME     NULL
){{TAIL}};
CREATE INDEX ix_report_texts_account ON report_texts (account_id, deleted_at);

-- 2-5 のチェック項目。概要書で3〜5が未定だったので、文言は後から変えられるようにした
CREATE TABLE checklist_items (
  id         {{PK}},
  label      VARCHAR(255) NOT NULL,
  sort_order INT          NOT NULL DEFAULT 0,
  is_active  TINYINT      NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL,
  updated_at DATETIME     NOT NULL
){{TAIL}};

-- ============================================================
-- 報告書（客先提出用）
-- ============================================================

CREATE TABLE reports (
  id             {{PK}},
  report_no      INT          NOT NULL,
  account_id     INT          NOT NULL,
  -- タブレット側で発行する固有キー。同じものが何度届いても1件にまとめる（二重登録防止）
  client_uuid    VARCHAR(64)  NOT NULL,

  status         VARCHAR(16)  NOT NULL DEFAULT 'draft', -- draft|submitted|completed
  created_date   DATE         NULL,
  hospital_name  VARCHAR(255) NULL,
  work_date      DATE         NULL,
  work_date_end  DATE         NULL,
  work_place     VARCHAR(255) NULL,
  work_title     VARCHAR(255) NULL,
  workers_text   VARCHAR(255) NULL,

  work_note      TEXT         NULL,
  parts_note     TEXT         NULL,
  report_body    TEXT         NULL,

  checked_ids    VARCHAR(255) NULL,               -- チェック済 checklist_items.id（カンマ区切り）
  submitter_name VARCHAR(128) NULL,               -- 2-5 下段の「作業者」＝報告書PDFの「担当」
  signature_file VARCHAR(255) NULL,
  signature_at   DATETIME     NULL,

  pdf_at         DATETIME     NULL,               -- 提出用PDFを作った日時（一覧のPDF●）
  pdf_file       VARCHAR(255) NULL,
  mail_count     INT          NOT NULL DEFAULT 0, -- 一覧のMail列
  submitted_at   DATETIME     NULL,
  completed_at   DATETIME     NULL,               -- 一覧の状態「完」

  device_saved_at DATETIME    NULL,
  synced_at       DATETIME    NULL,
  created_at      DATETIME    NOT NULL,
  updated_at      DATETIME    NOT NULL,
  deleted_at      DATETIME    NULL
){{TAIL}};
CREATE UNIQUE INDEX ux_reports_client_uuid ON reports (client_uuid);
CREATE UNIQUE INDEX ux_reports_no ON reports (report_no);
CREATE INDEX ix_reports_list ON reports (account_id, work_date, id);
CREATE INDEX ix_reports_hospital ON reports (hospital_name);

CREATE TABLE report_workers (
  id         {{PK}},
  report_id  INT          NOT NULL,
  worker_id  INT          NULL,                  -- NULL = 自由入力
  name       VARCHAR(128) NOT NULL,
  sort_order INT          NOT NULL DEFAULT 0
){{TAIL}};
CREATE INDEX ix_report_workers ON report_workers (report_id, sort_order);

CREATE TABLE report_models (
  id         {{PK}},
  report_id  INT          NOT NULL,
  model_id   INT          NULL,
  model_name VARCHAR(128) NOT NULL,              -- マスタが変わっても報告書は当時の名前を残す
  qty        INT          NOT NULL DEFAULT 0,
  sort_order INT          NOT NULL DEFAULT 0
){{TAIL}};
CREATE INDEX ix_report_models ON report_models (report_id, sort_order);

CREATE TABLE report_parts (
  id         {{PK}},
  report_id  INT          NOT NULL,
  part_id    INT          NULL,
  part_name  VARCHAR(191) NOT NULL,
  unit       VARCHAR(16)  NOT NULL DEFAULT '個',
  qty        INT          NOT NULL DEFAULT 0,
  sort_order INT          NOT NULL DEFAULT 0
){{TAIL}};
CREATE INDEX ix_report_parts ON report_parts (report_id, sort_order);

CREATE TABLE report_measurements (
  id               {{PK}},
  report_id        INT          NOT NULL,
  room_name        VARCHAR(128) NULL,
  model_name       VARCHAR(128) NULL,
  cumulative_hours INT          NULL,
  serial_no        VARCHAR(32)  NULL,
  manufactured_ym  VARCHAR(7)   NULL,
  sort_order       INT          NOT NULL DEFAULT 0
){{TAIL}};
CREATE INDEX ix_report_measurements ON report_measurements (report_id, sort_order);

-- ============================================================
-- 社内用報告書（4-1〜4-8）
-- ============================================================

CREATE TABLE internal_reports (
  id             {{PK}},
  report_id      INT          NOT NULL,
  created_date   DATE         NULL,
  hospital_name  VARCHAR(255) NULL,
  work_date      DATE         NULL,
  work_place     VARCHAR(255) NULL,
  workers_text   VARCHAR(255) NULL,
  work_title     VARCHAR(255) NULL,

  remaining_work TEXT         NULL,
  travel_out_from VARCHAR(5)  NULL,
  travel_out_to   VARCHAR(5)  NULL,
  work_from       VARCHAR(5)  NULL,
  work_to         VARCHAR(5)  NULL,
  travel_back_from VARCHAR(5) NULL,
  travel_back_to   VARCHAR(5) NULL,
  sales_approach TEXT         NULL,
  remarks        TEXT         NULL,

  pdf_at         DATETIME     NULL,              -- 一覧の「社内用」●
  pdf_file       VARCHAR(255) NULL,
  completed_at   DATETIME     NULL,
  created_at     DATETIME     NOT NULL,
  updated_at     DATETIME     NOT NULL
){{TAIL}};
CREATE UNIQUE INDEX ux_internal_reports_report ON internal_reports (report_id);

CREATE TABLE internal_report_parts (
  id                 {{PK}},
  internal_report_id INT          NOT NULL,
  part_id            INT          NULL,
  part_name          VARCHAR(191) NOT NULL,
  unit               VARCHAR(16)  NOT NULL DEFAULT '個',
  qty                INT          NOT NULL DEFAULT 0,
  sort_order         INT          NOT NULL DEFAULT 0
){{TAIL}};
CREATE INDEX ix_internal_report_parts ON internal_report_parts (internal_report_id, sort_order);

-- ============================================================
-- メール送信ログ（2-10）
-- ============================================================

CREATE TABLE mail_logs (
  id         {{PK}},
  report_id  INT          NOT NULL,
  kind       VARCHAR(16)  NOT NULL DEFAULT 'report', -- report | internal
  to_addr    VARCHAR(255) NOT NULL,
  cc_addr    VARCHAR(512) NULL,
  subject    VARCHAR(255) NULL,
  body       TEXT         NULL,
  attachment VARCHAR(255) NULL,
  is_dry_run TINYINT      NOT NULL DEFAULT 0,
  sent_at    DATETIME     NOT NULL
){{TAIL}};
CREATE INDEX ix_mail_logs_report ON mail_logs (report_id, sent_at);
