<?php

/**
 * CuStatic Plugin - オプション設定画面
 *
 * @var \BaserCore\View\BcAdminAppView $this
 * @var \CuStatic\Model\Entity\CuStaticConfig|null $config
 * @var array $sites サイト一覧 [id => display_name]
 * @var array $blogContentsBySite ブログコンテンツ一覧 [site_id => Content[]]
 */
use Cake\Core\Configure;

$this->BcAdmin->setTitle('[静的HTML出力] オプション設定');
$targetConfig = json_decode($config->target_config ?? '{}', true) ?? [];
?>

<!-- form -->
<?= $this->BcAdminForm->create($config, ['url' => ['action' => 'config']]) ?>

<?= $this->BcFormTable->dispatchBefore() ?>

<div class="section">
	<table id="FormTable" class="form-table bca-form-table">
		<tr>
			<th class="bca-form-table__label">
				<?= $this->BcAdminForm->label('export_path', '出力先') ?>
				&nbsp;<span class="required bca-label" data-bca-label-type="required">必須</span>
			</th>
			<td class="col-input bca-form-table__input">
				<?= $this->BcAdminForm->control('export_path', [
					'type' => 'text',
					'size' => 60,
					'maxlength' => 255,
					'placeholder' => '/var/www/html/static/',
					'label' => false,
				]) ?>
				<?= $this->BcAdminForm->error('export_path') ?>
				<i class="bca-icon--question-circle bca-help"></i>
				<div class="bca-helptext">
					HTML出力実行時、出力先フォルダ内の全ファイルを削除した上で出力します。<br>
					必要なファイルがないかどうか事前に確認の上、指定先のパスにご注意ください。
				</div>
			</td>
		</tr>
		<tr>
			<th class="bca-form-table__label">
				<?= $this->BcAdminForm->label('base_url', 'ベースURL') ?>
			</th>
			<td class="col-input bca-form-table__input">
				<?= $this->BcAdminForm->control('base_url', [
					'type' => 'text',
					'size' => 60,
					'maxlength' => 255,
					'placeholder' => 'https://example.com（空の場合はサイト設定を使用）',
					'label' => false,
				]) ?>
				<?= $this->BcAdminForm->error('base_url') ?>
				<i class="bca-icon--question-circle bca-help"></i>
				<div class="bca-helptext">
					通常は指定不要ですが、HTML生成元の公開側のURLを個別に指定できます。<br>
					特殊な環境（管理側とフロント側で異なる、DNS切り替え前でhost対応している、など）用です。
				</div>
			</td>
		</tr>
		<tr>
			<th class="bca-form-table__label">
				<?= $this->BcAdminForm->label('rsync_command', 'rsyncコマンド') ?>
			</th>
			<td class="col-input bca-form-table__input">
				<?= $this->BcAdminForm->control('rsync_command', [
					'type' => 'text',
					'size' => 60,
					'maxlength' => 255,
					'placeholder' => 'rsync -a --delete（空の場合はPHPコピーを使用）',
					'label' => false,
				]) ?>
				<?= $this->BcAdminForm->error('rsync_command') ?>
				<i class="bca-icon--question-circle bca-help"></i>
				<div class="bca-helptext">
					通常は指定不要ですが、大量の画像があるなどで静的コンテンツのコピーに時間がかかる場合などに高速でコピーできるコマンドを指定できます
				</div>
			</td>
		</tr>
		<?php echo $this->BcAdminForm->dispatchAfterForm() ?>
	</table>
</div>

<?php
/**
 * @param string $modePrefix  'main_' または 'diff_'
 * @param bool   $isDiff      差分モード追加オプションを出すか
 */
$renderSiteSection = function(string $modePrefix, bool $isDiff) use ($sites, $blogContentsBySite, $customContentsBySite, $targetConfig): void {
    foreach ($sites as $siteId => $siteName):
?>
	<div class="section">
		<h3 class="bca-main__heading" data-bca-heading-size="sm"><?= h($siteName) ?></h3>
		<table class="form-table bca-form-table">
			<tr>
				<th class="bca-form-table__label">ページ設定</th>
				<td class="col-input bca-form-table__input">
					<?php
					foreach ([
						$modePrefix . 'folder_' . $siteId => 'フォルダ（インデックスページ）',
						$modePrefix . 'page_'   . $siteId => '固定ページ',
					] as $key => $label):
						echo $this->BcAdminForm->control('target_config[' . $key . ']', [
							'type' => 'checkbox',
							'label' => $label,
							'checked' => $targetConfig[$key] ?? true,
						]);
					endforeach;
					?>
				</td>
			</tr>
			<?php if (isset($blogContentsBySite[$siteId])): ?>
				<?php foreach ($blogContentsBySite[$siteId] as $blogContent): ?>
					<?php $blogId = $blogContent->entity_id;
					$prefix = $modePrefix . $siteId . '_' . $blogId; ?>
					<tr>
						<th class="bca-form-table__label"><?= h($blogContent->title) ?></th>
						<td class="col-input bca-form-table__input">
							<?php
							foreach ([
								'blog_index_'      . $prefix => '記事一覧',
								'blog_single_'     . $prefix => '記事詳細',
								'blog_category_'   . $prefix => 'カテゴリ別',
								'blog_tag_'        . $prefix => 'タグ別',
								'blog_date_year_'  . $prefix => '年別',
								'blog_date_month_' . $prefix => '月別',
								'blog_date_day_'   . $prefix => '日別',
								'blog_author_'     . $prefix => '著者別',
							] as $key => $label):
								echo $this->BcAdminForm->control('target_config[' . $key . ']', [
									'type' => 'checkbox',
									'label' => $label,
									'checked' => $targetConfig[$key] ?? true,
								]);
							endforeach;
							if ($isDiff):
								$oneKey = 'blog_index_one_' . $prefix;
								echo $this->BcAdminForm->control('target_config[' . $oneKey . ']', [
									'type' => 'checkbox',
									'label' => '記事一覧（1ページ目のみ）',
									'checked' => $targetConfig[$oneKey] ?? false,
								]);
								$cbKey = 'blog_callback_' . $prefix;
							?>
							<?= $this->BcAdminForm->control('target_config[' . $cbKey . ']', [
								'type'        => 'textarea',
								'rows'        => 3,
								'placeholder' => '/index',
								'value'       => $targetConfig[$cbKey] ?? '',
								'label'       => false,
							]) ?>
							<p class="cu-static-info">
								※ ブログ記事を更新したタイミングで最新に更新するページのURLを記載してください。（トップページに新着情報を読み込んでいる場合など）<br>
								※ 複数指定する場合は改行を入れて指定してください。
							</p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			<?php if (isset($customContentsBySite[$siteId])): ?>
				<?php foreach ($customContentsBySite[$siteId] as $customContent): ?>
					<?php $customContentId = $customContent->entity_id;
					$prefix = $modePrefix . $siteId . '_' . $customContentId; ?>
					<tr>
						<th class="bca-form-table__label"><?= h($customContent->title) ?></th>
						<td class="col-input bca-form-table__input">
							<?php
							foreach ([
								'custom_index_'  . $prefix => 'エントリー一覧',
								'custom_single_' . $prefix => 'エントリー詳細',
							] as $key => $label):
								echo $this->BcAdminForm->control('target_config[' . $key . ']', [
									'type' => 'checkbox',
									'label' => $label,
									'checked' => $targetConfig[$key] ?? true,
								]);
							endforeach;
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</table>
	</div>
<?php
    endforeach;
};
?>

<div class="section" id="cu-static-main-export">
	<div class="bca-collapse__action">
		<button type="button" class="bca-collapse__btn" data-bca-collapse="collapse" data-bca-target="#cu-static-main-export-body" aria-expanded="false" aria-controls="cu-static-main-export-body">
			全件書出設定&nbsp;&nbsp;<i class="bca-icon--chevron-down bca-collapse__btn-icon"></i>
		</button>
	</div>
	<div class="bca-collapse" id="cu-static-main-export-body" data-bca-state="">
		<?php $renderSiteSection('main_', false); ?>
	</div>
</div>

<?php if (Configure::read('CuStatic.cronEnabled')): ?>
<div class="section" id="cu-static-cron-export">
	<div class="bca-collapse__action">
		<button type="button" class="bca-collapse__btn" data-bca-collapse="collapse" data-bca-target="#cu-static-cron-export-body" aria-expanded="false" aria-controls="cu-static-cron-export-body">
			定期実行書出（CRON）設定&nbsp;&nbsp;<i class="bca-icon--chevron-down bca-collapse__btn-icon"></i>
		</button>
	</div>
	<div class="bca-collapse" id="cu-static-cron-export-body" data-bca-state="">
		<?php $renderSiteSection('diff_', true); ?>
	</div>
</div>
<?php endif; ?>

<?= $this->BcFormTable->dispatchAfter() ?>

<div class="section" id="cu-static-irregular">
	<div class="bca-collapse__action">
		<button type="button" class="bca-collapse__btn" data-bca-collapse="collapse" data-bca-target="#cu-static-irregular-body" aria-expanded="false" aria-controls="cu-static-irregular-body">
			イレギュラー時設定（取扱注意）&nbsp;&nbsp;<i class="bca-icon--chevron-down bca-collapse__btn-icon"></i>
		</button>
	</div>
	<div class="bca-collapse" id="cu-static-irregular-body" data-bca-state="">
		<table class="form-table bca-form-table">
			<tr>
				<th class="bca-form-table__label">書出処理ステータス</th>
				<td class="col-input bca-form-table__input">
					<?= $this->BcAdminForm->control('status', [
						'type' => 'select',
						'options' => [0 => '0: 待機中', 1 => '1: 実行中'],
						'default' => 0,
						'id' => 'CuStaticStatus',
						'style' => 'background-color:#ccc',
						'disabled' => true,
						'label' => false,
					]) ?>
					<br>
					<?= $this->BcAdminForm->control('status_change', [
						'type' => 'checkbox',
						'label' => 'ステータスを変更する',
						'id' => 'CuStaticStatusChange',
						'value' => '1',
						'checked' => false,
					]) ?>
					<p class="cu-static-warning">
						※ CRON処理途中で止まった時等の緊急対応用ですので通常は絶対に変更しないでください。<br>
						※ ステータスを変更するとシステム全体に影響がございます。十分に仕組みを理解した上で変更してください。<br>
						※ 事前に必ずバックアップ後に変更してください。
					</p>
				</td>
			</tr>
		</table>
	</div>
</div>

<style>
.cu-static-warning { color: red; font-weight: bold; margin: 5px 0; }
</style>
<script>
(function() {
	var statusEl = document.getElementById('CuStaticStatus');
	var changeEl = document.getElementById('CuStaticStatusChange');
	if (!statusEl || !changeEl) return;
	changeEl.addEventListener('change', function() {
		if (changeEl.checked) {
			if (confirm('ステータスを変更するとシステム全体に影響がございます。\n必ずバックアップ後に実行してください。\n\n※ 変更後は元には戻せません。\n※ 問題発生時に自己解決できる場合のみ変更するようにしてください。\n\nご確認いただけましたでしょうか？')) {
				statusEl.style.backgroundColor = '#fff';
				statusEl.disabled = false;
			} else {
				changeEl.checked = false;
			}
		} else {
			statusEl.style.backgroundColor = '#ccc';
			statusEl.disabled = true;
		}
	});
})();
</script>

<!-- button -->
<div class="submit bca-actions">
	<div class="bca-actions__main">
		<?= $this->BcAdminForm->button('保存', [
			'div' => false,
			'class' => 'button bca-btn bca-actions__item bca-loading',
			'data-bca-btn-type' => 'save',
			'data-bca-btn-size' => 'lg',
			'data-bca-btn-width' => 'lg',
		]) ?>
	</div>
</div>

<?= $this->BcAdminForm->end() ?>
