CuStatic（静的HTML出力）プラグイン
==========

CuStatic は、baserCMS で作成した Web サイト内のページを **静的な HTML ファイルとして書き出す** baserCMS5 用プラグインです。

固定ページ・フォルダ・ブログ（記事一覧／記事詳細／カテゴリ別／タグ別／日付別／著者別／RSS）を HTML として出力し、あわせて CSS・JS・画像などの静的アセット（テーマ・プラグインの `webroot` を含む）も出力先へコピーします。

## 動作環境

- baserCMS 5.x
- PHP 8.1 以上 / CakePHP 5
- `ext-pcntl`（任意）: 並列書き出しに使用。**Linux / macOS のみ**（Windows 非対応）で、CLI の `php.ini` で有効化が必要です。無い環境では自動的にシリアル実行にフォールバックします。

## インストール

1. 管理画面から、またはFTPで `plugins`フォルダにプラグインをアップロードします。（`plugins/CuStatic`）
2. 管理システムの「プラグイン管理」から **CuStatic** をインストール（有効化）します。
3. 「オプション設定」画面で出力先フォルダなどを設定し、保存します。

## 使い方（管理画面）

管理画面メニュー「コンテンツ」内に「静的HTML出力」が追加されます。

### オプション設定

`/baser/admin/cu-static/cu_statics/config`

| 項目 | 説明 |
|------|------|
| 出力先（必須） | HTML の書き出し先フォルダの絶対パス。**全件出力時は出力先フォルダ内を一旦削除してから出力**します。 |
| ベースURL | HTML 取得元のベースURL。空の場合はサイト設定（`BcEnv`）の URL を使用します。 |
| rsyncコマンド | アセットコピーに使う rsync コマンド。空の場合は PHP でのファイルコピーを行います。 |
| 出力対象 | サイト × コンテンツ種別（フォルダ／固定ページ／ブログの各種一覧・詳細）のチェックボックス。 |

### 静的HTML出力

`/baser/admin/cu-static/cu_statics/index`

「静的HTML出力」ボタンで書き出しをバックグラウンド実行します。
進捗バーと最新ログをリアルタイム表示（ポーリング）し、開始/終了時刻・経過時間の表示とログファイルのダウンロードもできます。

## 出力対象

- 固定ページ / フォルダ（インデックス）
- ブログ: 記事一覧（＋ページネーション）・RSS・カテゴリ別・タグ別・日付別（年／月／日）・著者別・記事詳細
- 静的アセット: `webroot` 直下の `css/js/img/files`、および対象サイトのテーマ・プラグインの `webroot`（URL `/{アンダースコア名}/` に対応。例: テーマ `BcThemeSample` → `bc_theme_sample/`）

※ 動的なコンテンツ（メールフォーム、サイト内検索など）には非対応です。
必要な場合は外部サービスで対応してください。

## ログ

書き出しログは `logs/cu_static.log` に出力されます。並列実行時はどのワーカーの出力かを判別できるよう、各行にプロセスID（`[pid:12345]`）を付与します。

## 設定のカスタマイズ

`config/setting_customize.php.default` を `config/setting_customize.php` にコピーすると、既定値を上書きできます（`.gitignore` 対象のためコミットされません）。

| キー | 既定値 | 説明 |
|------|--------|------|
| `CuStatic.defaultWorkers` | `4` | 既定の並列ワーカー数 |
| `CuStatic.cronEnabled` | `false` | 差分書き出し（CRON）機能の有効化フラグ |
| `CuStatic.types` | `Page` / `ContentFolder` / `BlogContent` | 書き出し対象のコンテンツ種別 |
| `CuStatic.plugins` | `BcBlog` / `BcFront` | `webroot` コピー対象のプラグイン |
| `CuStatic.httpMaxAttempts` | `3` | HTML取得の最大試行回数（5xx・接続エラー時にリトライ。1でリトライなし） |
| `CuStatic.chunkSize` | `1000` | ブログ投稿集計時のチャンク件数（大量投稿時のメモリ抑制） |
| `CuStatic.lockTimeout` | `3600` | 実行ロックの有効期限（秒）。開始からこの秒数を超えた実行中フラグは stale として次回実行が奪取 |

## Thanks
- [http://basercms.net](http://basercms.net/)
- [http://wiki.basercms.net/](http://wiki.basercms.net/)
- [http://cakephp.jp](http://cakephp.jp)

## License
Lincensed under the MIT lincense since version 2.0
