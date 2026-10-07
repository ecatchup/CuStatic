<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Utility;

use Cake\Core\Configure;

/**
 * CuStaticUtil
 *
 * CuStatic プラグインの汎用ユーティリティ。
 */
class CuStaticUtil
{

    /**
     * バックグラウンド実行に使う CLI PHP バイナリのパスを解決する
     *
     * Web コンテキストの PHP_BINARY は FPM/CGI/Apache モジュールでは CLI ではない
     * バイナリを指す。また PATH 上の `php` は別バージョン（OS 標準の古い PHP 等）の
     * ことがあるため、実行中と同じビルドの CLI（PHP_BINDIR/php）を PATH より優先する。
     *
     * 解決順:
     * 1. 設定 `CuStatic.phpBinary`（setting_customize.php で明示指定）
     * 2. PHP_BINARY（CLI バイナリの場合のみ）
     * 3. PHP_BINDIR/php（実行中と同一ビルドの CLI。Remi SCL 等のバージョン別配置に対応）
     * 4. PATH 上の php（バージョン不一致の可能性がある最終手段）
     * 5. /usr/local/bin/php
     *
     * @return string
     */
    public static function getPhpBinary(): string
    {
        $configured = (string)(Configure::read('CuStatic.phpBinary') ?? '');
        if (trim($configured) !== '') {
            return trim($configured);
        }

        if (self::isCliPhpBinary(PHP_BINARY)) {
            return PHP_BINARY;
        }

        $sameBuild = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
        if (is_executable($sameBuild)) {
            return $sameBuild;
        }

        // `which` は環境により存在しないため、シェルビルトインの `command -v` を使う
        $which = trim((string)shell_exec('command -v php 2>/dev/null'));
        if ($which !== '') {
            return $which;
        }

        return '/usr/local/bin/php';
    }

    /**
     * パスが CLI の PHP バイナリらしいかを判定する
     *
     * `php`・`php8.5`・`php85` 等は CLI とみなし、
     * `php-fpm`・`php-cgi`・`httpd` 等の SAPI バイナリは除外する。
     *
     * @param string $bin 判定対象のバイナリパス
     * @return bool
     */
    public static function isCliPhpBinary(string $bin): bool
    {
        if (trim($bin) === '') {
            return false;
        }
        return (bool)preg_match('/^php[0-9.]*$/', basename($bin));
    }

    /**
     * HTML内の内部リンク（<a href>）を静的HTML向けに書き換える
     *
     * DOMDocument を使うと saveHTML() が日本語を数値文字参照（&#xxxx;）へ変換するため、
     * href 属性のみを文字列置換で書き換え、本文は一切変更しない。
     *
     * 状態を持たない純粋な文字列変換のため、本体（CuStaticService::exportHtml）だけでなく
     * アドオンが自前生成するページ（CuStaticAddonMailForm のフォームページ等）からも再利用できる。
     *
     * @param string $html
     * @param string $currentUrl 取得元ページのURL（相対リンク・ページネーション解決に使用）
     * @return string
     */
    public static function convertHtmlLinks(string $html, string $currentUrl): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $baseHost = parse_url($currentUrl, PHP_URL_HOST) ?? '';
        $currentPath = parse_url($currentUrl, PHP_URL_PATH) ?? '/';

        return (string) preg_replace_callback(
            '/(<a\b[^>]*?\shref\s*=\s*)(["\'])(.*?)\2/i',
            fn($m) => $m[1] . $m[2] . self::convertHref($m[3], $baseHost, $currentPath) . $m[2],
            $html
        );
    }

    /**
     * 単一の href を静的HTML向けパスへ変換する
     *
     * 変換対象は同一ホストの内部リンクのみ。外部URL・アンカー・mailto 等はそのまま返す。
     *   - 末尾 / → /index.html
     *   - 拡張子なし → .html 付与
     *   - ページネーション ?page=N（N>=2）→ {パス}/page-N.html（baserCMS5 のクエリ形式に対応）
     *
     * @param string $href 元の href
     * @param string $baseHost 対象ホスト
     * @param string $currentPath 取得元ページのパス（相対リンク解決の基準）
     * @return string
     */
    public static function convertHref(string $href, string $baseHost, string $currentPath): string
    {
        $raw = trim($href);
        if ($raw === '') {
            return $href;
        }

        // アンカー・外部スキームはそのまま
        // 区切り文字は ~ を使用（パターンに含まれるアンカー # をリテラルとして扱うため。# 区切りだと誤認して warning になる）
        if (preg_match('~^(#|mailto:|tel:|javascript:|ftp:|data:)~i', $raw)) {
            return $href;
        }

        // 属性値のエスケープを戻し（&amp; → &）、フラグメントを除去して解析
        $decoded = explode('#', str_replace('&amp;', '&', $raw), 2)[0];
        if ($decoded === '') {
            return $href;
        }

        $query = '';
        if (preg_match('#^https?://#i', $decoded) || str_starts_with($decoded, '//')) {
            // 絶対URL：別ホストはスキップ
            $p = parse_url($decoded);
            if (($p['host'] ?? '') !== $baseHost) {
                return $href;
            }
            $path = $p['path'] ?? '/';
            $query = $p['query'] ?? '';
        } elseif (str_starts_with($decoded, '?')) {
            // クエリのみ（ページネーション等）→ 現在ページのパスに対する相対
            $path = $currentPath;
            $query = substr($decoded, 1);
        } else {
            [$p, $query] = array_pad(explode('?', $decoded, 2), 2, '');
            if (str_starts_with($p, '/')) {
                $path = $p; // ルート相対
            } else {
                // その他の相対 → 現在パスのディレクトリ基準
                $path = rtrim(str_replace('\\', '/', dirname($currentPath)), '/') . '/' . $p;
            }
        }

        if ($path === '') {
            $path = '/';
        }

        // 拡張子付き（CSS/JS/画像等）はそのまま
        if (pathinfo($path, PATHINFO_EXTENSION) !== '') {
            return $href;
        }

        // 末尾スラッシュ → index
        if (str_ends_with($path, '/')) {
            $path .= 'index';
        }

        // 日付アーカイブの月・日をゼロ埋めに正規化する。
        // baserCMS はウィジェットにより前ゼロ有無が異なる（カレンダー: /date/2026/6・/date/2026/7/2、
        // 月別/日別アーカイブ: /date/2026/07）。出力ファイルは前ゼロ形式のため、リンク側を揃える。
        $path = (string) preg_replace_callback(
            '#(/archives/date)/(\d{4})(?:/(\d{1,2}))?(?:/(\d{1,2}))?$#',
            function ($mm) {
                $out = $mm[1] . '/' . $mm[2];
                if (($mm[3] ?? '') !== '') {
                    $out .= '/' . str_pad($mm[3], 2, '0', STR_PAD_LEFT);
                }
                if (($mm[4] ?? '') !== '') {
                    $out .= '/' . str_pad($mm[4], 2, '0', STR_PAD_LEFT);
                }
                return $out;
            },
            $path
        );

        // ページネーション ?page=N（N>=2）。page=1・クエリなしは一覧本体（index等）
        if ($query !== '' && preg_match('/(?:^|&)page=(\d+)/', $query, $m) && (int) $m[1] >= 2) {
            return rtrim($path, '/') . '/page-' . (int) $m[1] . '.html';
        }

        return $path . '.html';
    }

    /**
     * HTML の head 内で絶対URLを持つ参照（canonical・og:url）を静的HTML向けURLへ書き換える
     *
     * canonical はフルURL（取得元ホスト）で出力されるため convertHtmlLinks() の対象外であり、
     * 拡張子なし（/about 等）のまま残ると静的サイト上では存在しないURLを指してしまう。
     * 内部リンクと同じく .html 付きのURLへ揃える（ホスト部分は保持する）。
     *
     * @param string $html
     * @param string $currentUrl 取得元ページのURL
     * @return string
     */
    public static function convertHeadUrls(string $html, string $currentUrl): string
    {
        if (trim($html) === '') {
            return $html;
        }
        $baseHost = parse_url($currentUrl, PHP_URL_HOST) ?? '';

        // <link rel="canonical" href="..."> / <meta property="og:url" content="...">（属性順は問わない）
        return (string) preg_replace_callback(
            '/<(?:link\b[^>]*\brel\s*=\s*(["\'])canonical\1|meta\b[^>]*\bproperty\s*=\s*(["\'])og:url\2)[^>]*>/i',
            function ($m) use ($baseHost) {
                return (string) preg_replace_callback(
                    '/(\s(?:href|content)\s*=\s*)(["\'])(.*?)\2/i',
                    fn($a) => $a[1] . $a[2] . self::toStaticAbsoluteUrl($a[3], $baseHost) . $a[2],
                    $m[0]
                );
            },
            $html
        );
    }

    /**
     * RSS/XML フィード内の <link>・<guid> の記事URLを静的HTML向けURLへ書き換える
     *
     * フィードの記事URLは拡張子なし（/news/archives/1 等）で出力されるため、
     * 静的サイト上で存在する .html 付きのURLへ変換する。フィードでは絶対URLが必須のため
     * ホスト部分は保持する（enclosure 等の拡張子付きURLは変換しない）。
     *
     * @param string $xml
     * @param string $currentUrl 取得元フィードのURL
     * @return string
     */
    public static function convertFeedLinks(string $xml, string $currentUrl): string
    {
        if (trim($xml) === '') {
            return $xml;
        }
        $baseHost = parse_url($currentUrl, PHP_URL_HOST) ?? '';

        return (string) preg_replace_callback(
            '#(<(link|guid)\b[^>]*>)\s*([^<]*?)\s*(</\2>)#i',
            fn($m) => $m[1] . self::toStaticAbsoluteUrl($m[3], $baseHost) . $m[4],
            $xml
        );
    }

    /**
     * 同一ホストの絶対URLを、ホストを保ったまま静的HTML向けURLへ変換する
     *
     * 末尾スラッシュのURL（ディレクトリ）は静的サイトでもそのまま解決できるため変換しない。
     * 別ホスト・相対URL・拡張子付きURLはそのまま返す。
     *
     * @param string $url
     * @param string $baseHost 取得元ホスト
     * @return string
     */
    public static function toStaticAbsoluteUrl(string $url, string $baseHost): string
    {
        $decoded = str_replace('&amp;', '&', trim($url));
        if (!preg_match('#^(https?:)?//#i', $decoded)) {
            return $url;
        }
        $p = parse_url($decoded);
        if ($p === false || ($p['host'] ?? '') !== $baseHost) {
            return $url;
        }
        $path = $p['path'] ?? '/';
        if ($path === '' || str_ends_with($path, '/') && empty($p['query'])) {
            return $url;
        }

        $converted = self::convertHref($url, $baseHost, '/');
        if ($converted === $url) {
            return $url;
        }
        $origin = (isset($p['scheme']) ? $p['scheme'] . ':' : '') . '//' . $p['host']
            . (isset($p['port']) ? ':' . $p['port'] : '');
        return $origin . $converted;
    }

    /**
     * 出力内容に含まれる取得元のURL（オリジン＋ベースパス）を公開URLへ置き換える
     *
     * canonical・RSS・OGP・JSON-LD 等のフルURLは取得元（管理側）のホストで生成されるため、
     * 静的サイトの公開URLへ書き換える。次の表記に対応する。
     *   - https://host / http://host（スキームの違いは問わない）
     *   - //host（プロトコル相対。置換後もプロトコル相対のまま）
     *   - https:\/\/host（JSON エスケープ形式）
     * ホストの直後が区切り文字（/ ? # 引用符 < > 空白 \ 等）または末尾の場合のみ置換し、
     * host.example.com.evil や host:8080 等の別オリジンを誤って置換しない。
     *
     * @param string $content
     * @param array<int, string> $fromUrls 置換元URL（取得元ベースURL・サイトURL等）
     * @param string $toUrl 置換先（公開URL）
     * @return string
     */
    public static function replaceOrigins(string $content, array $fromUrls, string $toUrl): string
    {
        $to = self::splitUrl($toUrl);
        if ($content === '' || $to === null) {
            return $content;
        }

        $targets = [];
        foreach ($fromUrls as $fromUrl) {
            $from = self::splitUrl((string) $fromUrl);
            if ($from === null || strcasecmp($from['authority'], $to['authority']) === 0) {
                continue;
            }
            $targets[strtolower($from['authority'])] = $from['authority'];
        }
        if (!$targets) {
            return $content;
        }
        // パス付き（サブディレクトリ設置）を優先して置換するため長い順に並べる
        uksort($targets, fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($targets as $authority) {
            $content = (string) preg_replace_callback(
                '~(?:(https?:)|(?<![:\w\\\\/]))(//|\\\\/\\\\/)' . preg_quote($authority, '~')
                    . '(?=[/?#"\'<>\s\\\\),;&]|$)~i',
                function ($m) use ($to) {
                    $replacement = ($m[1] !== '' ? $to['scheme'] . ':' : '') . '//' . $to['authority'];
                    // JSON エスケープ形式（\/\/）はスラッシュを同じ形式へ揃える
                    return $m[2] === '//' ? $replacement : str_replace('/', '\\/', $replacement);
                },
                $content
            );
        }
        return $content;
    }

    /**
     * URL をスキームと「ホスト[:ポート][/パス]」（末尾スラッシュなし）に分解する
     *
     * @param string $url
     * @return array{scheme:string, authority:string}|null 解析できない場合は null
     */
    private static function splitUrl(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return null;
        }
        $p = parse_url($url);
        if ($p === false || empty($p['host'])) {
            return null;
        }
        return [
            'scheme' => strtolower($p['scheme']),
            'authority' => $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . rtrim($p['path'] ?? '', '/'),
        ];
    }

    /**
     * 破壊的削除に対して危険な出力先パスなら理由文字列を、安全なら null を返す
     *
     * 全件モードは出力先を丸ごと削除するため、設定ミスでアプリ本体・webroot・config 等や
     * その祖先、システム上位ディレクトリを指していると重大事故になる。
     * 出力時（Service）と保存時（Table バリデーション）の両方から同一基準で利用する。
     * 実在しないパスは（削除対象にならないため）安全扱いとし null を返す。
     * 空文字は必須バリデーションへ委ねるため null を返す。
     *
     * @param string $exportPath 判定対象の出力先パス
     * @return string|null 危険な場合は理由、安全な場合は null
     */
    public static function unsafeReason(string $exportPath): ?string
    {
        if (trim($exportPath) === '') {
            return null;
        }

        $real = realpath($exportPath);
        // 実在しない（＝そもそも削除対象にならない）場合は安全扱い
        if ($real === false) {
            return null;
        }
        $real = rtrim($real, DIRECTORY_SEPARATOR);

        // ファイルシステム直下（空・"/"）は拒否
        if ($real === '') {
            return 'ファイルシステム直下は指定できません。';
        }

        // セグメントが浅すぎるパス（例: /var, /usr, /home）は拒否
        $segments = array_values(array_filter(explode(DIRECTORY_SEPARATOR, $real), 'strlen'));
        if (count($segments) < 2) {
            return 'パスが浅すぎます。システム上位ディレクトリは指定できません。';
        }

        $root = rtrim(ROOT, DIRECTORY_SEPARATOR);

        // アプリの重要ディレクトリそのものを指している場合は拒否
        if (in_array($real, self::protectedPaths($root), true)) {
            return 'アプリケーションの重要ディレクトリ（webroot・config・plugins・vendor 等）は指定できません。';
        }

        // アプリルート自身、またはその祖先（配下にアプリ本体を含む）を指している場合は拒否
        if ($real === $root || str_starts_with($root . DIRECTORY_SEPARATOR, $real . DIRECTORY_SEPARATOR)) {
            return 'アプリ本体を含む上位ディレクトリは指定できません。';
        }

        return null;
    }

    /**
     * 保護対象（削除してはならない）ディレクトリの実パス一覧を返す
     *
     * @param string $root ROOT（末尾セパレータなし）
     * @return array<int, string>
     */
    private static function protectedPaths(string $root): array
    {
        $paths = [
            $root,
            defined('WWW_ROOT') ? WWW_ROOT : $root . DIRECTORY_SEPARATOR . 'webroot',
            defined('CONFIG') ? CONFIG : $root . DIRECTORY_SEPARATOR . 'config',
            defined('TMP') ? TMP : null,
            defined('LOGS') ? LOGS : null,
            $root . DIRECTORY_SEPARATOR . 'plugins',
            $root . DIRECTORY_SEPARATOR . 'vendor',
            $root . DIRECTORY_SEPARATOR . 'src',
            $root . DIRECTORY_SEPARATOR . 'bin',
        ];

        $result = [];
        foreach ($paths as $p) {
            if ($p) {
                $result[] = rtrim((string) $p, DIRECTORY_SEPARATOR);
            }
        }

        return $result;
    }

}
