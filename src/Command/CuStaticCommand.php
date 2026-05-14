<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Command;

use BaserCore\Utility\BcContainerTrait;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use CuStatic\Service\CuStaticServiceInterface;

/**
 * CuStaticCommand
 *
 * 静的HTML出力のCLIコマンド。
 *
 * 使用例:
 *   bin/cake cu_static main           # 全件出力
 *   bin/cake cu_static main --workers=8
 *   bin/cake cu_static diff           # 差分出力
 */
class CuStaticCommand extends Command
{

    use BcContainerTrait;

    /**
     * @var CuStaticServiceInterface
     */
    protected CuStaticServiceInterface $CuStaticService;

    /**
     * コマンド名
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'cu_static';
    }

    /**
     * オプション定義
     *
     * @param ConsoleOptionParser $parser
     * @return ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('静的HTML出力プラグイン - 管理画面のコンテンツを静的HTMLとして出力ます。')
            ->addArgument('mode', [
                'help' => '実行モード: main（全件）または diff（差分）',
                'required' => true,
                'choices' => ['main', 'diff'],
            ])
            ->addOption('workers', [
                'help' => 'PCNTL並列ワーカー数（PCNTLが利用可能な場合のみ有効）',
                'default' => (string)(Configure::read('CuStatic.defaultWorkers') ?? 4),
                'short' => 'w',
            ])
            ->addOption('site-ids', [
                'help' => '対象サイトIDをカンマ区切りで指定（省略時は全サイト）',
                'default' => '',
            ]);

        return $parser;
    }

    /**
     * コマンド実行
     *
     * @param Arguments $args
     * @param ConsoleIo $io
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $this->CuStaticService = $this->getService(CuStaticServiceInterface::class);

        $mode = $args->getArgument('mode');
        $workers = (int) $args->getOption('workers');
        $siteIdsRaw = $args->getOption('site-ids');
        $siteIds = $siteIdsRaw ? array_map('intval', explode(',', $siteIdsRaw)) : null;

        $io->out(sprintf('[CuStatic] %s 開始 (workers=%d)', $mode, $workers));

        $options = [
            'all' => ($mode === 'main'),
            'workers' => $workers,
            'siteIds' => $siteIds,
        ];

        try {
            $result = $this->CuStaticService->export($options);
            if ($result) {
                $io->success('[CuStatic] 完了');
                return self::CODE_SUCCESS;
            }
            // 実行中のためスキップ＝正常系（CRON連続起動でも二重実行せず安全終了）。
            // 監視が誤検知しないよう終了コードは成功(0)とする。
            $io->out('[CuStatic] 実行中のためスキップしました。');
            return self::CODE_SUCCESS;
        } catch (\Throwable $e) {
            // 設定なし・処理中エラー等は失敗として終了コード1（監視で検知可能）
            $io->error('[CuStatic] エラー: ' . $e->getMessage());
            return self::CODE_ERROR;
        }
    }

}
