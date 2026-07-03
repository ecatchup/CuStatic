<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Utility;

/**
 * CuStaticUtil
 *
 * CuStatic プラグインの汎用ユーティリティ。
 */
class CuStaticUtil
{

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
