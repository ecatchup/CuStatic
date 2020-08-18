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

	public $progress_max = 0;

	public function beforeFilter() {
		parent::beforeFilter();
		$this->progress_max = $this->Content->find('count', [
			'conditions' => [
				'type' => ['Page', 'Folder', 'BlogContent'],
				'status' => true,
			],
			'recursive' => -1,
		]);
	}

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
		$this->pageTitle = '静的HTML出力';

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
				clearDataCache();
				$this->BcMessage->setSuccess(__d('baser', 'オプション設定を保存しました。'));
				$this->redirect('config');
			}
		} else {
			$this->request->data['CuStaticConfig'] = $this->CuStaticConfig->findExpanded();
		}
		$this->pageTitle = 'オプション設定';

	}

	/**
	 * [ADMIN] get status
	 */
	public function admin_get_status() {

		$this->autoRender = false;

		$CuStaticConfig = $this->CuStaticConfig->findExpanded();
		$result['status'] = $CuStaticConfig['status'];
		$result['progress'] = $CuStaticConfig['progress'];
		$result['progress_max'] = $this->progress_max;

		return json_encode($result);
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

	public function admin_log_download() {

		$fileName = 'cu_static.log';
		$fullName = TMP . DS . 'logs' . DS . $fileName;
		$File = new File($fullName);
		$info = $File->info();
		$mimeType = $info['mime'];
		if (empty($mimeType)) {
			$mimeType = 'application/octet-stream';
		}
		header('Content-Disposition: attachment; filename="'.$fileName.'"');
		$length = filesize($fullName);
		header('Content-Length: ' . $length);
		header('Content-type: ' . $mimeType);
		@readfile($fullName);
		exit();

	}
}
