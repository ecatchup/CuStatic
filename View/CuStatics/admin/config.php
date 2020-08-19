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
				<p>※ 上記で指定したフォルダ内は、書き出し実行時にフォルダ内のフォルダやファイルをすべて削除後に書き出しを行います。</p>
			</td>
		</tr>
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
