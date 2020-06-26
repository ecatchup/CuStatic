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
