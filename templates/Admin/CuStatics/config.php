<?php

/**
 * CuStatic Plugin - オプション設定画面
 *
 * @var \BaserCore\View\BcAdminAppView $this
 * @var \CuStatic\Model\Entity\CuStaticConfig|null $config
 * @var array $sites サイト一覧 [id => display_name]
 * @var array $blogContentsBySite ブログコンテンツ一覧 [site_id => Content[]]
 * @var array $blogPostCounts ブログごとの公開中の記事の数 [blog_content_id => int]
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
					'placeholder' => '/var/www/html/tmp/static/',
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
				<?= $this->BcAdminForm->label('public_url', '公開URL') ?>
			</th>
			<td class="col-input bca-form-table__input">
				<?= $this->BcAdminForm->control('public_url', [
					'type' => 'text',
					'size' => 60,
					'maxlength' => 255,
					'placeholder' => 'https://www.example.com（静的サイトを配信するURL）',
					'label' => false,
				]) ?>
				<?= $this->BcAdminForm->error('public_url') ?>
				<i class="bca-icon--question-circle bca-help"></i>
				<div class="bca-helptext">
					書き出したHTMLを配信する静的サイト側のURLです。<br>
					設定すると、出力HTML・RSS内の管理側（取得元）の絶対URL（canonical・RSSの記事リンク等）をこのURLに書き換えます。<br>
					アドオン（静的メールフォーム等）が、静的サイトからのアクセスを許可するオリジンの既定値としても参照します。
				</div>
			</td>
		</tr>
		<tr>
			<th class="bca-form-table__label">rsyncコマンド</th>
			<td class="col-input bca-form-table__input">
				<?php // 管理画面から任意のコマンドを登録できないよう、表示のみ（書き出し後コマンドと同じ方針） ?>
				<?php $rsyncCommand = (string)(\Cake\Core\Configure::read('CuStatic.rsyncCommand') ?? '') ?>
				<?php if ($rsyncCommand !== ''): ?>
					<code><?= h($rsyncCommand) ?></code>
				<?php else: ?>
					未設定（PHP でコピーします）
				<?php endif ?>
				<i class="bca-icon--question-circle bca-help"></i>
				<div class="bca-helptext">
					大量の画像などで静的コンテンツのコピーに時間がかかる場合に、高速でコピーできるコマンドを指定できます。<br>
					登録・変更は <code>config/setting_customize.php</code> の <code>CuStatic.rsyncCommand</code> で行います。
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
$blogPostCounts = $blogPostCounts ?? [];
/**
 * ブログそのものが公開中か（公開状態・公開期間）。非公開のブログは書き出しの対象にならない
 */
$isBlogPublished = function ($content): bool {
    if (!$content->status) {
        return false;
    }
    $now = time();
    if ($content->publish_begin && strtotime((string) $content->publish_begin) > $now) {
        return false;
    }
    if ($content->publish_end && strtotime((string) $content->publish_end) < $now) {
        return false;
    }
    return true;
};
$renderSiteSection = function(string $modePrefix, bool $isDiff) use ($sites, $blogContentsBySite, $customContentsBySite, $targetConfig, $blogPostCounts, $isBlogPublished): void {
    foreach ($sites as $siteId => $siteName):
?>
	<div class="section">
		<h3 class="bca-main__heading cu-static-toggle-site" data-bca-heading-size="sm" title="クリックすると、このサイトのチェックをまとめて ON/OFF します"><?= h($siteName) ?></h3>
		<table class="form-table bca-form-table">
			<tr>
				<th class="bca-form-table__label cu-static-toggle-row" title="クリックすると、この行のチェックをまとめて ON/OFF します">ページ設定</th>
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
						<?php $published = $isBlogPublished($blogContent);
						$postCount = (int) ($blogPostCounts[$blogId] ?? 0); ?>
						<th class="bca-form-table__label cu-static-toggle-row" title="クリックすると、この行のチェックをまとめて ON/OFF します">
							<?= h($blogContent->title) ?>
							<br><small class="cu-static-blog-status<?= $published ? '' : ' is-unpublished' ?>"><?= $published ? '公開中' : '非公開（書き出しません）' ?>・記事 <?= $postCount ?> 件</small>
						</th>
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
						<th class="bca-form-table__label cu-static-toggle-row" title="クリックすると、この行のチェックをまとめて ON/OFF します"><?= h($customContent->title) ?></th>
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
					<?php
					// 画面では disabled で作り、「ステータスを変更する」を入れたときだけ JS で有効にする。
					// disabled の項目は改ざん防止の一覧に入らないため、有効にして送ると
					// 「Form tampering protection token validation failed」になる。対象から外す
					$this->BcAdminForm->unlockField('status');
					?>
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
.cu-static-toggle-site, .cu-static-toggle-row { cursor: pointer; }
.cu-static-blog-status { font-weight: normal; color: #2e7d32; }
.cu-static-blog-status.is-unpublished { color: #c62828; }
</style>
<script>
(function() {
	// サイト名・行の名前をクリックすると、その中のチェックをまとめて ON/OFF する（最初のチェックの逆にそろえる。4系と同じ）
	function toggleAll(container) {
		var boxes = container.querySelectorAll('input[type=checkbox]');
		if (!boxes.length) return;
		var checked = !boxes[0].checked;
		boxes.forEach(function(box) { box.checked = checked; });
	}
	document.querySelectorAll('.cu-static-toggle-site').forEach(function(el) {
		el.addEventListener('click', function() {
			var table = el.parentNode.querySelector('table');
			if (table) toggleAll(table);
		});
	});
	document.querySelectorAll('.cu-static-toggle-row').forEach(function(el) {
		el.addEventListener('click', function() {
			var td = el.nextElementSibling;
			if (td) toggleAll(td);
		});
	});
})();
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
