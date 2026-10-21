@AGENTS.md

# ミミガイド

複数の美術館・博物館で共通して使える、Webベースの音声ガイドプラットフォーム。管理者が自館の展示・作品・音声・解説文を登録、更新する。来館者が、QRコードから作品の音声ガイドを聴く。ログインなし（ゲスト）で利用できることを基本とする。プラットフォーム管理者が、参加館の登録・管理を行う。

## このプロジェクトの決まり

- 画面は Livewire 4 の単一ファイルコンポーネント（`resources/views/pages/**/⚡*.blade.php`）で作る。Volt は使わない
- UI は Flux UI の Free 版のコンポーネントだけを使う（Pro 専用は使わない）。無いものは Tailwind で作る
- 文言は日本語。日付は `isoFormat('M月D日(ddd) HH:mm')`、金額は「3,000円」の形式
- 認可は `app/Policies/` のポリシーで行い、画面側では `$this->authorize()` を使う
- 外部サービス（Stripe など）に触るコードは `app/Services/` に閉じ込める。テストではそのクラスをモックする
- 機能を追加したら `tests/Feature/` に Pest テストを追加し、`/check` が通ることを確認する
- 開発コマンドは Sail 経由（`sail artisan ...`、`sail composer ...`、`sail npm ...`）。ホストの php や composer は使わない
- ビューを追加・変更したら `sail npm run build` を実行する（Tailwind がクラスを拾うため）

## 作業の進め方

- 変更の前に、何をどのファイルに書くかを短く説明してから実装する
- 1回の指示で扱う範囲は1機能まで。大きい依頼は分割案を先に出す
- 既存のファイルの書き方に合わせる（同じ階層のファイルを先に読む）
- `.env` の値、API キー、パスワードはコードやコミットに含めない
- 失敗したコマンドは、原因の推測と対処案を1つずつ示してから再実行する

## 設計

詳細: [database.md](doc/design/database.md)（ER 図・テーブル定義）、[screens.md](doc/design/screens.md)（画面と URL）、[glossary.md](doc/design/glossary.md)（用語）、仕様は `doc/spec/`

### ロールとデータの分離

- ロールは `users.role`（`admin` / `staff`）。来館者はゲストで、ユーザーとして保存しない
- `staff` は `users.museum_id` の館だけを扱う。クエリとポリシーで必ず自館に絞り、他館は 403。作品は `exhibition` を通して館を判定する

### データ

- 言語で変わる文言（館名・展示名・作品名・作者・制作年・解説文）は `〜_translations` に持つ。表示は「選択言語 → 館の既定言語」の順で、一覧は `with('translations')`
- 状態は Enum（`UserRole` / `PublishStatus` / `GuidePassStatus`）で文字列保存。館の稼働・停止は `is_active`
- 金額は円の整数。`guide_passes.amount` は注文時点の単価で、後から書き換えない
- 一意: 各 `slug`、(`exhibition_id`, `guide_number`)、(`artwork_id`, `locale`)
- 削除: 翻訳・音声は cascade、館→展示・パス・staff と 展示→作品 は restrict。館は削除せず停止。画像・音声のファイルはレコードより先に消す

### 来館者側

- URL は slug（館 `/m/{slug}`、展示 `/e/{slug}`、作品 `/a/{slug}`）。展示・作品の slug は作成時にランダム 12 文字で生成し、変えない。連番 ID と言語は URL に入れない（言語は `?lang=` → セッション）
- 見せる条件は「館が稼働中・展示が公開かつ期間内・作品が公開」のすべて。満たさなければ 404
- 有料の館（`guide_price > 0`）では、解説文と音声に有効なパス（`paid` かつ `expires_at` が未来。Cookie のトークンで判定）が必要
- 画像は `public` ディスク、音声は `local` ディスク。音声は `/a/{slug}/audio` でパスを確認してから返す
- Stripe は `CheckoutService` に閉じ込める。`paid` への更新は `pending` のときだけの条件付き更新にし、購入完了メールは更新に成功した側だけが送る
