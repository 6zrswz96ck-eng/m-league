# Mリーグ選手成績

2026-27 レギュラーシーズンの公式成績表から40選手のポイントを取得し、4人組の合計と順位を表示する Laravel アプリです。
公開の「選手紹介」ページでは選手をチーム別に表示します。チーム名の色は各チームのロゴを参考にした配色です。名前・所属・ポイントの編集画面は管理者専用です。
ランキングでは最下位のグループを赤で示します。選手データの手動修正は公式データを取得できない場合の予備機能として、グループ管理画面から開けます。手動修正したポイントは次回の自動更新で上書きされます。

## ローカル起動

```powershell
composer install
php artisan migrate
php artisan db:seed
php artisan mleague:update
php artisan serve
```

別のターミナルで `php artisan schedule:work` を実行すると、1時間ごとに更新を試みます。画面の「最新成績に更新」でも更新できます。初回の取得はこの作業環境で実施済みです。

管理画面は `/login` からログインします。ユーザー名は `admin`（`.env` の `LEAGUE_ADMIN_USER` で変更可）、パスワードは `.env` の `LEAGUE_ADMIN_PASSWORD` です。ログアウトすると管理画面のセッションは失効します。公開環境では必ず HTTPS を使ってください。

## Render で公開

1. このリポジトリを GitHub に push します。`.env` と `database/database.sqlite` は含めません。
2. Render で **New → Blueprint** を開き、そのリポジトリを選びます。`render.yaml` が Web サービスと 1GB の永続ディスクを設定します。
3. 初回作成画面で `LEAGUE_ADMIN_PASSWORD` に新しい強いパスワードを入力し、料金を確認して作成します。ローカルのパスワードは使い回さないでください。
4. デプロイ後、公開 URL の `/up` が応答し、`/players` と `/` が開くことを確認します。`/login` から管理者ログインし、グループを登録します。

現在の Render 設定は有料の Web サービス（約 $7/月）と永続ディスク 1GB（約 $0.25/月）です。公開前に Render 画面で最新料金を確認してください。Render の Cron Job は永続ディスクを利用できないため、スケジューラは Web サービス内で動かします。

## データと運用

- データ元: [M.LEAGUE 公式成績表](https://m-league.jp/stats/)
- 取得対象: 2026-27 レギュラーシーズンの40選手
- HTML 構造、選手数、名前、数値を検証してからトランザクションで更新します。失敗時は既存データを保持し、`storage/logs` に記録します。
- シーズンが変わる際は `MLeagueScoreService` の見出し判定と Seeder の選手名を更新してください。
- Render 公開用の `render.yaml` と `Dockerfile` を用意しています。GitHub にこのリポジトリを置き、Render で Blueprint を作成して `LEAGUE_ADMIN_PASSWORD` を設定すると、Web と定期更新が同じサービス内で動きます。SQLite は `/var/data` の永続ディスクに保存されます。ディスクを利用するため有料プランが必要です。
- Render の初回起動時にマイグレーション、選手の登録、公式成績の取得を実行します。以後は毎時0分に更新します。画面は Render の公開 URL で友人も閲覧できます。管理画面はログインが必要です。
- `.env` と SQLite ファイルは Git に含めません。ローカルのグループ情報をクラウドに移す場合は、別途データ移行が必要です。

テスト: `php artisan test`
