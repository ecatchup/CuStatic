<section class="bca-section" data-bca-section-type='form-group'>

<?php echo $this->BcForm->create('CuStaticConfig', ['type' => 'file']) ?>

	<?php echo $this->BcFormTable->dispatchBefore() ?>

	<table id="FormTable" class="form-table bca-form-table">
		<tr>
			<th class="col-head bca-form-table__label">
				<?php echo $this->BcForm->label('CuStaticConfig.exportPath', __d('baser', '出力先')) ?>
				&nbsp;<span class="required bca-label" data-bca-label-type="required"><?php echo __d('baser', '必須') ?></span>
			</th>
			<td class="col-input bca-form-table__input">
				<?php
					echo $this->BcForm->input(
						'CuStaticConfig.exportPath',
						[
							'type' => 'text',
							'size' => 100,
							'maxlength' => 255,
							'default' => WWW_ROOT . 'html' . DS,
							'autofocus' => true,
							'class' => 'bca-textbox__input',
						]
					);
				?>
				<i class="bca-icon--question-circle btn help bca-help"></i>
				<?php echo $this->BcForm->error('CuStaticConfig.exportPath') ?>
				<div id="helptextFormalName" class="helptext">
					<ul>
						<li><?php echo __d('baser', 'HTMLファイルなどの出力先を指定します。') ?></li>
					</ul>
				</div>
				<p class="info">
					※ HTML出力実行時、出力先フォルダ内のすべてファイルを削除した上で出力を行います。
				</p>
			</td>
		</tr>
	</table>

<?php
	$modeList = Configure::read('CuStatic.mode');
	foreach ($modeList as $modeId => $mode):
?>
	<section class="bca-section" data-bca-section-type="form-group">
		<div class="bca-collapse__action">
			<button type="button" class="bca-collapse__btn" data-bca-collapse="collapse" data-bca-target="#<?php echo $mode['prefix'] ?>formAdminSettingBody" aria-expanded="false" aria-controls="<?php echo $mode['prefix'] ?>formAdminSettingBody">
				<?php echo $mode['title'] ?>設定&nbsp;&nbsp;<i class="bca-icon--chevron-down bca-collapse__btn-icon"></i>
			</button>
		</div>
		<div class="bca-collapse" id="<?php echo $mode['prefix'] ?>formAdminSettingBody" data-bca-state="">
			<table>
			<?php
				foreach ($sites as $siteId => $siteName):
					if (isset($blogContents[$siteId])) {
						$blogContentsCount = count($blogContents[$siteId]);
					} else {
						$blogContentsCount = 0;
					}
			?>
				<tr id="<?php echo $mode['prefix'] ?><?php echo $siteId ?>" class="<?php echo $mode['prefix'] ?>output">
					<th class="col-head bca-form-table__label">
						<?php echo $this->BcForm->label('CuStaticConfig.page', __d('baser', '出力対象：')) ?>
						<span class="site_title"><?php echo $siteName ?></span>
					</th>
					<td class="col-input bca-form-table__input">

						<table class="form-table bca-form-table">
							<tr>
								<td class="col-input bca-form-table__input">
									<?php
										$prefix = '_' . $mode['prefix'] . $siteId;
										echo $this->BcForm->input(
											'CuStaticConfig.folder' . $prefix,
											[
												'type' => 'checkbox',
												'label' => 'フォルダ',
												'default' => true,
												'class' => 'bca-checkbox__input page' . $prefix,
											]
										);
									?>
									<?php echo $this->BcForm->error('CuStaticConfig.folder' . $prefix); ?>
								</td>
							</tr>
							<tr>
								<td class="col-input bca-form-table__input">
									<?php
										$prefix = '_' . $mode['prefix'] . $siteId;
										echo $this->BcForm->input(
											'CuStaticConfig.page' . $prefix,
											[
												'type' => 'checkbox',
												'label' => '固定ページ',
												'default' => true,
												'class' => 'bca-checkbox__input page' . $prefix,
											]
										);
									?>
									<?php echo $this->BcForm->error('CuStaticConfig.page' . $prefix); ?>
								</td>
							</tr>
							<?php
								if (isset($blogContents[$siteId])):
									foreach ($blogContents[$siteId] as $blogContent):
										$blogContentId = $blogContent['entity_id'];
										$blogName = $blogContent['title'];
										$prefix = '_' . $mode['prefix'] . $siteId . '_' . $blogContentId;
										if ($blogContent['alias_id']) {
											$prefix .= '_a_' . $blogContent['id'];
										}
							?>
							<tr <?php if (!$this->BcContents->isAllowPublish($blogContent)): ?>style="background-color: #ccc"<?php endif ?>>
								<td class="col-input bca-form-table__input">
									<h4 class="blog_title blog_title<?php echo $prefix ?>"><?php echo $blogName ?></h4>
									<?php
										echo $this->BcForm->input(
											'CuStaticConfig.blog_index' . $prefix,
											[
												'type' => 'checkbox',
												'label' => '記事一覧',
												'default' => true,
												'class' => 'bca-checkbox__input blog' . $prefix,
											]
										);
										echo $this->BcForm->input(
											'CuStaticConfig.blog_category' . $prefix,
											[
												'type' => 'checkbox',
												'label' => 'カテゴリ一覧',
												'default' => true,
												'class' => 'bca-checkbox__input blog' . $prefix,
											]
										);
										echo $this->BcForm->input(
											'CuStaticConfig.blog_tag' . $prefix,
											[
												'type' => 'checkbox',
												'label' => 'タグ一覧',
												'default' => true,
												'class' => 'bca-checkbox__input blog' . $prefix,
											]
										);
										echo $this->BcForm->input(
											'CuStaticConfig.blog_date_year' . $prefix,
											[
												'type' => 'checkbox',
												'label' => '年別一覧',
												'default' => true,
												'class' => 'bca-checkbox__input blog' . $prefix,
											]
										);
										echo $this->BcForm->input(
											'CuStaticConfig.blog_date_month' . $prefix,
											[
												'type' => 'checkbox',
												'label' => '年月別一覧',
												'default' => true,
												'class' => 'bca-checkbox__input blog' . $prefix,
											]
										);
										echo $this->BcForm->input(
											'CuStaticConfig.blog_date_day' . $prefix,
											[
												'type' => 'checkbox',
												'label' => '年月日別一覧',
												'default' => true,
												'class' => 'bca-checkbox__input blog' . $prefix,
											]
										);
										echo $this->BcForm->input(
											'CuStaticConfig.blog_author' . $prefix,
											[
												'type' => 'checkbox',
												'label' => '作者一覧',
												'default' => true,
												'class' => 'bca-checkbox__input blog' . $prefix,
											]
										);
										echo $this->BcForm->input(
											'CuStaticConfig.blog_single' . $prefix,
											[
												'type' => 'checkbox',
												'label' => '記事詳細',
												'default' => true,
												'class' => 'bca-checkbox__input blog' . $prefix,
											]
										);
									?>
									<?php echo $this->BcForm->error('CuStaticConfig.blog_index' . $prefix); ?>
									<?php echo $this->BcForm->error('CuStaticConfig.blog_category' . $prefix); ?>
									<?php echo $this->BcForm->error('CuStaticConfig.blog_tag' . $prefix); ?>
									<?php echo $this->BcForm->error('CuStaticConfig.blog_date_year' . $prefix); ?>
									<?php echo $this->BcForm->error('CuStaticConfig.blog_date_month' . $prefix); ?>
									<?php echo $this->BcForm->error('CuStaticConfig.blog_date_day' . $prefix); ?>
									<?php echo $this->BcForm->error('CuStaticConfig.blog_author' . $prefix); ?>
									<?php echo $this->BcForm->error('CuStaticConfig.blog_single' . $prefix); ?>
									<?php 
										// CUSTOMIZE ADD 
										// >>>
									?>
									<?php if ($modeId == 'diff'): ?>
										<?php 
											echo $this->BcForm->input(
												'CuStaticConfig.blog_index_one' . $prefix,
												[
													'type' => 'checkbox',
													'label' => '記事一覧（1ページ目のみ）',
													'default' => false,
													'class' => 'bca-checkbox__input blog' . $prefix,
												]
											);
										?>
										<?php echo $this->BcForm->error('CuStaticConfig.blog_index_one' . $prefix); ?>
									<?php endif ?>
									<?php 
										// <<<
									?>
							<?php if ($modeId == 'diff'): ?>
									<?php
										echo $this->BcForm->input(
											'CuStaticConfig.blog_callback' . $prefix,
											[
												'type' => 'textarea',
												'placeholder' => '/index',
												'rows' => 3,
											]
										);
									?>
									<p class="info">
										※ ブログ記事を更新したタイミングで最新に更新するページのURLを記載してください。（トップページに新着情報を読み込んでいる場合など）<br>
										※ 複数指定する場合は改行を入れて指定してください。<br>
									</p>
							<?php endif ?>
								</td>
							</tr>
							<?php
									endforeach;
								endif;
							?>
						</table>
					</td>
				</tr>
		<?php endforeach; ?>
			</table>
		</div>
	</section>
	<?php endforeach; ?>

	<?php if (BcUtil::isAdminUser()): ?>
	<section class="bca-section" data-bca-section-type="form-group">
		<div class="bca-collapse__action">
			<button type="button" class="bca-collapse__btn" data-bca-collapse="collapse" data-bca-target="#formAdminSettingIrregular" aria-expanded="false" aria-controls="formAdminSettingIrregular">
				イレギュラー時設定（取扱注意）&nbsp;&nbsp;<i class="bca-icon--chevron-down bca-collapse__btn-icon"></i>
			</button>
		</div>
		<div class="bca-collapse" id="formAdminSettingIrregular" data-bca-state="">
			<table>
				<tr>
					<th class="col-head bca-form-table__label">
						書出処理ステータス
					</th>
					<td class="col-input bca-form-table__input">
						<?php
							echo $this->BcForm->input(
								'CuStaticConfig.status',
								[
									'type' => 'select',
									'options' => [
										0 => '0: 待機中',
										1 => '1: 実行中', 
									],
									'default' => 0,
									'style' => 'background-color: #ccc',
									'disabled',
								]
							);
						?>
						<br>
						<?php
							echo $this->BcForm->input(
								'CuStaticConfig.status_change',
								[
									'type' => 'checkbox',
									'label' => 'ステータスを変更する',
									'default' => false,
									'value' => false,
									'class' => 'bca-checkbox__input',
								]
							);

						?>
						<p class="info important">
							※ CRON処理途中で止まった時等の緊急対応用ですので
							通常は絶対に変更しないでください。<br>
							※ ステータスを変更するとシステム全体に影響がございます。
							十分に仕組みを理解した上で変更してください。<br>
							※ 事前に必ずバックアップ後に変更してください。<br>
						</p>
					</td>
				</tr>
			</table>
		</div>
	</section>
	<?php endif ?>

	<?php echo $this->BcForm->dispatchAfterForm('option') ?>

	<?php echo $this->BcFormTable->dispatchAfter() ?>

	<section class="bca-actions">
		<div class="bca-actions__main">
			<?php echo $this->BcForm->submit(
				__d('baser', '保存'),
				[
					'id' => 'BtnSave',
					'div' => false,
					'class' => 'button bca-btn bca-actions__item',
					'data-bca-btn-type' => 'save',
					'data-bca-btn-size' => 'lg',
					'data-bca-btn-width' => 'lg',
				]
			) ?>
		</div>
	</section>

<?php echo $this->BcForm->end() ?>

</section>

<script>
$(function() {
	$('.site_title').click(function() {
		var checked;
		$(this).parent('th').next('td').find('.bca-checkbox input[type=checkbox]').each(function(i, elm) {
			if (i == 0) {
				checked = !$(elm).prop('checked');
			}
			$(elm).prop('checked', checked);
		});
	});
	$('.blog_title').click(function() {
		var checked;
		$(this).parent().find('.bca-checkbox input[type=checkbox]').each(function(i, elm) {
			if (i == 0) {
				checked = !$(elm).prop('checked');
			}
			$(elm).prop('checked', checked);
		});
	});
	$('#CuStaticConfigStatusChange').click(function() {
		var status = $('#CuStaticConfigStatus');
		if (status.attr('disabled')) {
			if (confirm('ステータスを変更するとシステム全体に影響がございます。\n' +
						'必ずバックアップ後に実行してください。\n\n' + 
						'※ 変更後は元には戻せません。\n' + 
						'※ 問題発生時に自己解決できる場合のみ変更するようにしてください。\n\n' + 
						'ご確認いただけましたでしょうか？')) {
				status.css('background-color' ,'#fff');
				status.attr('disabled', false);
			} else {
				return false;
			}
		} else {
			status.css('background-color' ,'#ccc');
			status.attr('disabled', true);
		}
	})
});
</script>

<style>
.important {
	color: red;
	font-weight: bold;
}
.site_title,
.blog_title {
	cursor: pointer;
}
.bca-form-table__input {
	padding: 5px 10px;
}
.info {
	margin: 5px 10px;
}
</style>
