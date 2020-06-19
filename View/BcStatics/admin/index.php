<?php echo $this->BcForm->create('BcStatic', ['type' => 'file']) ?>
	<?php echo $this->BcForm->hidden('mode', ['value' => 'export']) ?>

	<input type="button" id="btnTail" value="TAIL">
	<input type="button" id="btnStop" value="STOP">
	<span id="status"></span><br />
	<pre id="console"></pre>

	<section class="bca-actions">
		<div class="bca-actions__main">
			<?php echo $this->BcForm->submit('書き出し', array(
				'id' => 'BtnSave',
				'div' => false,
				'class' => 'button bca-btn bca-actions__item',
				'data-bca-btn-type' => 'save',
				'data-bca-btn-size' => 'lg',
				'data-bca-btn-width' => 'lg',
			)) ?>
		</div>
	</section>

<?php echo $this->BcForm->end() ?>
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
	}
});

</script>
<style>
#console {
	width: 100%;
	height: 200px;
	overflow: auto;
	border: 1px solid #999999;
	font-size: 12px;
	font-family: consolas;
}
</style>
