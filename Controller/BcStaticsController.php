<?php
class BcStaticsController extends AppController {

	public $uses = [
		'Content',
		'Site',
	];

	public $components = [
		'BcAuth',
		'Cookie',
		'BcAuthConfigure'
	];

	/**
	 * [ADMIN] index
	 */
	public function admin_index() {

		if ($this->request->data) {
			$command = Configure::read('BcStatic.command');
			$cmd = CakePlugin::path('BcStatic') . 'Shell' . DS . $command;
			$this->log($cmd, LOG_BCSTATIC);
			exec($cmd);
			$this->setMessage('書き出し開始しました。');
			$this->redirect('index');
		}
		$this->pageTitle = '静的コンテンツ書出';

	}

	public function admin_tail($lines = 30) {

		$this->autoRender = false;

		$file = TMP. 'logs' . DS . 'bc_static.log';

		if (!file_exists($file)) return;

		$a = 500;
		$b = 10;
		$c = $a * $b;
		$x = file_get_contents($file, false, null, filesize($file) - $c);
		$lines = explode("\n", $x);
		return implode('<br>', $lines);
	}
}
