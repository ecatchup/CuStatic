CuStatic 応用機能（CLI・並列処理・差分書き出し）
==========

CuStatic のオプション機能（CLI コマンド・並列処理・差分書き出しと定期実行）をまとめたドキュメントです。基本的なインストール・管理画面の使い方は [README](../README.md) を参照してください。

## CLI コマンド

```bash
# 全件書き出し
bin/cake cu_static main
bin/cake cu_static main --workers=8          # 並列ワーカー数を指定
bin/cake cu_static main --site-ids=1,2       # 対象サイトを限定

# 差分書き出し（差分キューに溜まったコンテンツのみ）
bin/cake cu_static diff
bin/cake cu_static diff --workers=4
```

Docker 環境の例:

```bash
docker exec bc-php bin/cake cu_static main --workers=4
```

## 並列処理（PCNTL）

`--workers` に 2 以上を指定し、かつ `ext-pcntl` が利用可能な場合、`pcntl_fork()` によるワーカープールで URL 群を並列に書き出します。`ext-pcntl` が無い環境では自動的にシリアル実行になります。

> **動作条件（重要）**
> - `ext-pcntl` は **Linux / macOS のみ**で利用できる拡張です。**Windows は非対応**で、Windows 環境では常にシリアル実行になります。
> - Linux / macOS でも既定では無効な場合があります。CLI 用の `php.ini` で `extension=pcntl`（もしくはビルド時に `--enable-pcntl`）を有効化してください。`php -m | grep pcntl` で有効かどうか確認できます。
> - 有効でない場合や `--workers=1` の場合はシリアル実行にフォールバックするため、書き出し自体は動作します（並列化されないだけです）。

- 対象URLをワーカー数で分割し、子プロセスごとに HTTP 取得＋ファイル書き込みを実行します。
- fork 前に DB コネクションを切断し、各プロセスで再接続します。
- 進捗は一時ファイル経由で親プロセスに集計され、管理画面の進捗バーへ反映されます。

## 差分書き出しと定期実行（CRON）について

差分書き出しは、更新のあったコンテンツだけを再生成する機能です。全件書き出しに比べて短時間で完了するため、CRON による定期実行に向いています。

### 有効化

`config/setting_customize.php`（`config/setting_customize.php.default` をコピーして作成）で `CuStatic.cronEnabled` を `true` にすると有効になります。有効化すると次が使えます。

- 管理画面（静的HTML出力）に「差分書き出し」ボタンと「定期実行書出（CRON）設定」セクションが表示されます。
- コンテンツ・ブログ記事の **追加／変更／削除／公開・非公開切替** 時に、対象が差分キュー（`cu_static_contents`）へ自動で蓄積されます。
- 蓄積された差分は、管理画面の「差分書き出し」ボタン、または `bin/cake cu_static diff` で書き出せます。

### 差分で行われること

- **追加・変更**: 対象ページを再生成します。ブログ記事の保存では、記事詳細に加えて所属ブログの一覧・カテゴリ・タグ・日付・著者アーカイブとページネーションも再生成されます。
- **削除・非公開**: 対象の静的ファイルを削除します（全件書き出しは公開中のみ出力するため、非公開＝削除で整合）。
- **連動更新URL（`blog_callback`）**: ブログの差分設定に記入したURL（例: 新着を読み込むトップページ）を、記事更新時に一緒に再生成します。
- **記事一覧を1ページ目のみ（`blog_index_one`）**: 差分では記事一覧のページネーション（`page-N.html`）を再生成せず1ページ目だけ更新します。
- **時限公開（`publish_begin` / `publish_end`）**: 公開開始前はキューに保留し、公開開始後の差分実行で生成します。公開終了日時を過ぎると次の差分実行で静的ファイルを自動削除します。
  反映は **ポーリング型**（次回の差分実行時に処理）で、精度は **cron の実行間隔**に一致します（例: 1分間隔なら最大1分遅延）。

### OS cron への登録

差分の定期実行は `bin/cake cu_static diff` を OS の cron に登録して運用します。実行間隔が時限公開の反映精度になります。

```cron
# 毎分 差分書き出し（時限公開を分単位で反映したい場合）
* * * * * cd /path/to/app && bin/cake cu_static diff --workers=4 >> /path/to/app/logs/cu_static_cron.log 2>&1

# 5分ごと（反映の即時性より負荷を優先する場合）
*/5 * * * * cd /path/to/app && bin/cake cu_static diff --workers=4 >> /path/to/app/logs/cu_static_cron.log 2>&1
```

Docker 環境ではホストの crontab から `docker exec bc-php bin/cake cu_static diff --workers=4` を実行します。

### 多重起動防止・終了コード

- **多重起動防止**: 実行中は DB の実行フラグでロックし、後続の起動は安全にスキップします。異常終了でフラグが残っても、開始から `CuStatic.lockTimeout`（既定 3600 秒）を超えた実行中フラグは stale とみなして次回実行が自動的に奪取します。
- **終了コード**: 正常終了・実行中スキップは `0`、設定不備や処理エラーは `1` を返します。cron 連打（間隔内に前回が終わらない）でも二重実行されず終了コード `0` で安全終了するため、監視は終了コード `1` を異常として扱えます。

## 書き出し後コマンド

書き出しの完了後に、任意のコマンドを自動実行できます。CDN キャッシュの削除（Cloudflare 等）や、静的ホスティングへのデプロイ（`wrangler deploy` 等）に使います。全件書き出し・差分書き出しのいずれも、管理画面・CLI・cron のどこから実行しても同じように動作します。

### 設定

コマンドは `config/setting_customize.php` の `CuStatic.afterExportCommands` で設定します。**管理画面からは登録・変更できません**（管理画面から任意のシェルコマンドを実行できる経路を作らないため）。登録済みのコマンドは「静的HTML出力」画面に一覧表示されます。

```php
'CuStatic' => [
    'afterExportCommands' => [
        // 文字列だけでも指定できる（既定値で実行）
        'echo "done: $CU_STATIC_MODE"',

        // 配列で詳細を指定する
        [
            'label' => 'Cloudflare キャッシュ削除',   // 画面・ログの表示名（省略時はトークン等をマスクしたコマンド）
            'command' => 'curl -fsS -X POST "https://api.cloudflare.com/client/v4/zones/$CF_ZONE_ID/purge_cache"'
                . ' -H "Authorization: Bearer $CF_API_TOKEN" -H "Content-Type: application/json"'
                . ' --data \'{"purge_everything":true}\'',
        ],
        [
            'label' => 'Workers デプロイ',
            'command' => '/usr/local/bin/npx wrangler deploy',
            'cwd' => '/path/to/worker',   // 作業ディレクトリ（省略時は出力先）
            'modes' => ['all'],           // 実行するモード（all=全件 / diff=差分。省略時は両方）
            'timeout' => 600,             // タイムアウト秒（省略時は afterExportCommandTimeout = 300）
            'stopOnError' => true,        // 失敗したら後続のコマンドを実行しない（既定 false）
            'env' => ['NODE_ENV' => 'production'], // 追加の環境変数
        ],
    ],
],
```

| オプション | 既定値 | 説明 |
|------|--------|------|
| `command` | （必須） | 実行するコマンド。シェル経由で実行します |
| `label` | マスクしたコマンド | 画面・ログに表示する名前 |
| `cwd` | 出力先フォルダ | 作業ディレクトリ |
| `modes` | `['all', 'diff']` | 実行するモード（`main` は `all` の別名） |
| `skipIfNoChange` | `true` | 差分書き出しで出力・削除が 0 件のときは実行しない（cron の毎分実行で毎回キャッシュを消さないため） |
| `timeout` | `300` | タイムアウト（秒）。超えるとコマンドとその子プロセスを停止します |
| `stopOnError` | `false` | 失敗（終了コード 0 以外・タイムアウト）時に後続のコマンドを実行しない |
| `env` | `[]` | 追加の環境変数 |

### コマンドに渡される環境変数

親プロセスの環境変数（`.env` で設定した値を含む）に加えて、次の値が渡されます。

| 環境変数 | 内容 |
|------|------|
| `CU_STATIC_EXPORT_PATH` | 出力先フォルダ（末尾の `/` なし） |
| `CU_STATIC_MODE` | `all`（全件）または `diff`（差分） |
| `CU_STATIC_JOB_COUNT` | 書き出したページ数（アドオンが追加した分を除く） |
| `CU_STATIC_DELETE_COUNT` | 差分書き出しで削除したファイル数（全件は 0） |
| `CU_STATIC_PUBLIC_URL` | オプション設定の「公開URL」 |
| `CU_STATIC_BASE_URL` | 書き出しに使った取得元のベースURL |

### 実行順と結果

- `CuStatic.afterExport` イベント（GitDeploy アドオンの自動デプロイ等）の後に、上から順に実行します。
- 実行中は進捗バーに反映され、すべてのコマンドが終わるまで「実行中」のままになります。
- 標準出力・標準エラー・終了コード・所要時間は `logs/cu_static.log` に記録されます（画面の「最新ログ表示」で確認できます）。
- コマンドが失敗しても、書き出し自体は成功として扱います。

### 注意

- **トークン等の秘密情報はコマンドに直書きせず、環境変数で渡してください**（`.env` に `export CF_API_TOKEN="..."` を追加し、コマンドでは `$CF_API_TOKEN` と書く）。直書きした場合も、画面とログでは `Bearer`・`token=` 等に続く値をマスクしますが、設定ファイルには残ります。
- 管理画面の「静的HTML出力」ボタンからの実行では、Web サーバーの実行ユーザー・環境変数（`PATH` 等）でコマンドが動きます。`npx` 等は絶対パスで指定するか、`env` で `PATH` を指定してください。
- Cloudflare のキャッシュ削除 API は、URL を指定する方法（`files`）にも対応していますが、現時点では全削除（`purge_everything`）の例のみを案内しています。
