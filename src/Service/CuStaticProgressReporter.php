<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Service;

use CuStatic\Model\Table\CuStaticConfigsTable;

/**
 * CuStaticProgressReporter
 *
 * 静的HTML出力の進捗（cu_static_configs.progress / progress_max）を更新する薄いラッパー。
 *
 * 本体のジョブ出力だけでなく、CuStatic.beforeExport / CuStatic.afterExport を購読する
 * アドオンプラグインが、configId や DB を直接触らずに進捗へ加算できるようにするための拡張点。
 *
 * - reserve() で分母（progress_max）を増やし、advance() で分子（progress）を進める。
 * - 追加処理の件数を reserve() してから advance() すれば、進捗バーが「100%のまま静止」せず
 *   正直に進む（分母が増えるので一旦割合が下がり、書き出しごとに 100% へ向かう）。
 *
 * 進捗の真実源はこのインスタンスがメモリ上に保持し、更新のたびに DB へ反映する。
 */
class CuStaticProgressReporter
{

    /**
     * @var \CuStatic\Model\Table\CuStaticConfigsTable
     */
    protected CuStaticConfigsTable $table;

    /**
     * @var int 設定レコードID
     */
    protected int $configId;

    /**
     * @var int 現在の進捗（分子）
     */
    protected int $progress = 0;

    /**
     * @var int 進捗最大値（分母）
     */
    protected int $progressMax = 0;

    /**
     * Constructor
     *
     * @param \CuStatic\Model\Table\CuStaticConfigsTable $table 設定テーブル
     * @param int $configId 設定レコードID
     */
    public function __construct(CuStaticConfigsTable $table, int $configId)
    {
        $this->table = $table;
        $this->configId = $configId;
    }

    /**
     * 進捗の総数（分母）を設定する。進捗はリセットしない。
     *
     * @param int $total 総件数
     * @return void
     */
    public function setTotal(int $total): void
    {
        $this->progressMax = max(0, $total);
        if ($this->progress > $this->progressMax) {
            $this->progress = $this->progressMax;
        }
        $this->flush();
    }

    /**
     * 分母（progress_max）を加算する。追加処理の件数を事前に予約する用途。
     *
     * @param int $count 追加件数
     * @return void
     */
    public function reserve(int $count): void
    {
        if ($count <= 0) {
            return;
        }
        $this->progressMax += $count;
        $this->flush();
    }

    /**
     * 進捗（分子）を絶対値で設定する。分母を超えない範囲に丸める。
     *
     * @param int $progress 現在の進捗
     * @return void
     */
    public function set(int $progress): void
    {
        $progress = max(0, $progress);
        $this->progress = min($progress, $this->progressMax);
        $this->flush();
    }

    /**
     * 進捗（分子）を相対的に進める。
     *
     * @param int $n 進める件数
     * @return void
     */
    public function advance(int $n = 1): void
    {
        $this->set($this->progress + $n);
    }

    /**
     * 現在の進捗（分子）
     *
     * @return int
     */
    public function getProgress(): int
    {
        return $this->progress;
    }

    /**
     * 進捗最大値（分母）
     *
     * @return int
     */
    public function getProgressMax(): int
    {
        return $this->progressMax;
    }

    /**
     * 現在値を DB へ反映する。
     *
     * @return void
     */
    protected function flush(): void
    {
        $this->table->updateProgress($this->configId, $this->progress, $this->progressMax);
    }

}
