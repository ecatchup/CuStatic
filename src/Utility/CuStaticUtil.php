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
