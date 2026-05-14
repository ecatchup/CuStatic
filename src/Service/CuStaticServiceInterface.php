<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Service;

/**
 * CuStaticServiceInterface
 */
interface CuStaticServiceInterface
{

    /**
     * 全件または差分のHTML出力を実行する
     *
     * @param array $options
     *   - all: bool 全件対象かどうか（false=差分）
     *   - siteIds: array|null 対象サイトID一覧
     *   - workers: int PCNTL並列ワーカー数
     * @return bool
     */
    public function export(array $options = []): bool;

    /**
     * 指定URLのHTMLを取得してファイルに保存する
     *
     * @param string $url 取得対象のURL（スラッシュ始まり）
     * @param string $path 出力先ファイルパス
     * @param bool $publish 公開状態（false=空ファイルを作成）
     * @return void
     */
    public function exportHtml(string $url, string $path, bool $publish = true): void;

    /**
     * PCNTL fork によるURL群の並列出力
     *
     * @param array $jobs [['url' => string, 'path' => string, 'publish' => bool], ...]
     * @param int $workers 並列ワーカー数
     * @param callable|null $onProgress 完了件数(int)を受け取る進捗コールバック
     * @return void
     */
    public function exportParallel(array $jobs, int $workers, ?callable $onProgress = null): void;

    /**
     * 静的アセット（css/js/img/files/テーマ・プラグイン webroot）をコピーする
     *
     * @param string $exportPath 出力先フォルダパス
     * @param array $themes 対象テーマ名の配列
     * @return void
     */
    public function copyAssets(string $exportPath, array $themes = []): void;

    /**
     * 出力時に使うベースURLを取得する
     *
     * @param string $configBaseUrl 設定上のベースURL（空の場合はサイト設定を使用）
     * @return string
     */
    public function getBaseUrl(string $configBaseUrl = ''): string;

}
