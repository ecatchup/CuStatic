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

<?php
	foreach ($sites as $siteId => $siteName):
		if (isset($blogContents[$siteId])) {
			$blogContentsCount = count($blogContents[$siteId]);
		} else {
			$blogContentsCount = 0;
		}
?>
		<tr>
			<th class="col-head bca-form-table__label">
				<?php echo $this->BcForm->label('CuStaticConfig.page', __d('baser', '出力対象：')) ?>
				<?php echo $siteName ?>
			</th>
			<td class="col-input bca-form-table__input">

				<table class="form-table bca-form-table">
					<tr>
						<td class="col-input bca-form-table__input">
							<?php
								$prefix = '_' . $siteId;
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
								$prefix = '_' . $siteId;
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
							foreach ($blogContents[$siteId] as $bloContentId => $blogName):
								$prefix = '_' . $siteId . '_' . $bloContentId;
					?>
					<tr>
						<td class="col-input bca-form-table__input">
							<h4 class="blog_title<?php echo $prefix ?>"><?php echo $blogName ?></h4>
							<script>
								$(function(){
									$('.blog_title<?php echo $prefix ?>').on('click', function() {
										var checked = $('input.blog<?php echo $prefix ?>:first:checkbox').prop('checked');
										$('.blog<?php echo $prefix ?>').prop('checked', !checked);
										return false;
									});
								});
							</script>
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
										'label' => '年月別一覧',
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
						</td>
					</tr>
					<tr>
						<td class="col-input bca-form-table__input">
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
							※ 記事詳細を更新した場合にあわせて更新するページのURLを記載してください。<br>
							※ 複数指定する場合は改行してください。<br>
							※ （例：TOPページの新着情報など）<br>
							</p>
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
<!--
		<tr>
			<th class="col-head bca-form-table__label">
				<?php echo $this->BcForm->label('CuStaticConfig.page', __d('baser', '出力対象：固定ページ')) ?>
			</th>
			<td class="col-input bca-form-table__input">
				<?php
					echo $this->BcForm->input(
						'CuStaticConfig.page',
						[
							'type' => 'checkbox',
							'label' => '固定ページ',
						]
					);
				?>
				<?php echo $this->BcForm->error('CuStaticConfig.page'); ?>
			</td>
		</tr>
		<tr>
			<th class="col-head bca-form-table__label">
				<?php echo $this->BcForm->label('CuStaticConfig.blog', __d('baser', '出力対象：ブログ')) ?>
			</th>
			<td class="col-input bca-form-table__input">
				<?php
					echo $this->BcForm->input(
						'CuStaticConfig.blog_index',
						[
							'type' => 'checkbox',
							'label' => '記事一覧',
						]
					);
					echo $this->BcForm->input(
						'CuStaticConfig.blog_category',
						[
							'type' => 'checkbox',
							'label' => 'カテゴリ一覧',
						]
					);
					echo $this->BcForm->input(
						'CuStaticConfig.blog_tag',
						[
							'type' => 'checkbox',
							'label' => 'タグ一覧',
						]
					);
					echo $this->BcForm->input(
						'CuStaticConfig.blog_date_year',
						[
							'type' => 'checkbox',
							'label' => '年別一覧',
						]
					);
					echo $this->BcForm->input(
						'CuStaticConfig.blog_date_month',
						[
							'type' => 'checkbox',
							'label' => '年月別一覧',
						]
					);
					echo $this->BcForm->input(
						'CuStaticConfig.blog_date_day',
						[
							'type' => 'checkbox',
							'label' => '年月別一覧',
						]
					);
					echo $this->BcForm->input(
						'CuStaticConfig.blog_author',
						[
							'type' => 'checkbox',
							'label' => '作者一覧',
						]
					);
					echo $this->BcForm->input(
						'CuStaticConfig.blog_single',
						[
							'type' => 'checkbox',
							'label' => '記事詳細',
						]
					);
				?>
				<?php echo $this->BcForm->error('CuStaticConfig.blog_index'); ?>
				<?php echo $this->BcForm->error('CuStaticConfig.blog_category'); ?>
				<?php echo $this->BcForm->error('CuStaticConfig.blog_tag'); ?>
				<?php echo $this->BcForm->error('CuStaticConfig.blog_date_year'); ?>
				<?php echo $this->BcForm->error('CuStaticConfig.blog_date_month'); ?>
				<?php echo $this->BcForm->error('CuStaticConfig.blog_date_day'); ?>
				<?php echo $this->BcForm->error('CuStaticConfig.blog_author'); ?>
				<?php echo $this->BcForm->error('CuStaticConfig.blog_single'); ?>
			</td>
		</tr>
-->
		<?php echo $this->BcForm->dispatchAfterForm('option') ?>

	</table>

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
$(function(){
});
</script>

<style>
</style>
