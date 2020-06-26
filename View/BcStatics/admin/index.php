<section class="bca-section" data-bca-section-type='form-group'>

<?php echo $this->BcForm->create('BcStatic', ['type' => 'file']) ?>

	<?php echo $this->BcFormTable->dispatchBefore() ?>

	<?php echo $this->BcForm->hidden('mode', ['value' => 'export']) ?>
	<input type="button" id="btnTail" value="TAIL">
	<input type="button" id="btnStop" value="STOP">
	<span id="status"></span><br />
	<pre id="console"></pre>

	<?php echo $this->BcForm->dispatchAfterForm('option') ?>

	<?php echo $this->BcFormTable->dispatchAfter() ?>

	<section class="bca-actions">
		<div class="bca-actions__main">
			<?php echo $this->BcForm->submit(
				__d('baser', '書き出し'),
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
	var timer = null;
	$('#btnTail').click(function() {
		if (timer) {
			clearInterval(timer);
		}
		$('#status').html('running...');
		timer = setInterval(run, 2000);
	})

	$('#btnStop').click(function() {
		clearInterval(timer); // インターバルをクリアする。
		$('#status').empty(); // id=statusの箇所をからにする。
	})

	$('#btnTail').trigger('click');

	function run(){
		$('#console').load('/admin/bc_static/bc_statics/tail');
		$('#console').animate({scrollTop: $('#console')[0].scrollHeight}, 'fast');
	}
});

</script>
<style>
#console {
	width: 100%;
	height: 400px;
	overflow: auto;
	border: 1px solid #999999;
	font-size: 12px;
	font-family: consolas;
}
</style>
