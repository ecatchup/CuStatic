<?php
class CuStaticsController extends AppController {

	public $uses = [
		'Content',
		'Site',
		'CuStatic.CuStaticConfig',
	];

	public $components = [
		'BcAuth',
		'Cookie',
		'BcAuthConfigure',
		'BcMessage',
	];

	/**
	 * [ADMIN] index
	 */
	public function admin_index() {

		if ($this->request->data) {
			$command = Configure::read('CuStatic.command');
			$cmd = CakePlugin::path('CuStatic') . 'Shell' . DS . $command;
			$this->log($cmd, LOG_CUSTATIC);
			exec($cmd);
			$this->BcMessage->setSuccess(__d('baser', '書き出し開始しました。'));
			$this->redirect('index');
		}
		$this->pageTitle = '静的コンテンツ書出';

	}

	/**
	 * [ADMIN] config
	 */
	public function admin_config() {

		if ($this->request->data) {
			$this->CuStaticConfig->set($this->request->data);
			if (!$this->CuStaticConfig->validates()) {
				$this->BcMessage->setError(__d('baser', '入力エラーです。内容を修正してください。'));
			} else {
				$this->CuStaticConfig->saveKeyValue($this->request->data);
				$this->BcMessage->setSuccess(__d('baser', 'オプション設定を保存しました。'));
				$this->redirect('config');
			}
		}
		$this->pageTitle = 'オプション設定';

	}

	/**
	 * [ADMIN] log tail
	 */
	public function admin_tail($limit = 2500) {

		$this->autoRender = false;

		$file = TMP. 'logs' . DS . 'cu_static.log';

		if (!file_exists($file)) return;

		if ($limit == -1) {
			$lines = file($file);
		} else {
			// $a = 250;
			// $b = 10;
			// $c = $a * $b;
			$x = file_get_contents($file, false, null, filesize($file) - $limit);
			$lines = explode("\n", $x);
		}

		return implode('<br>', $lines);
	}
}
