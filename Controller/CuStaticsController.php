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

		public function beforeFilter() {
		parent::beforeFilter();
	}

	/**
	 * [ADMIN] index
	 */
	public function admin_index() {

		if ($this->request->data) {
			$command = sprintf(Configure::read('CuStatic.command'), 'main');
			$cmd = CakePlugin::path('CuStatic') . 'Shell' . DS . $command;
			$this->log($cmd, LOG_CUSTATIC);
			exec($cmd);
			$this->redirect('index');
		}

		$this->set('cuStaticConfigs', $this->CuStaticConfig->findExpanded());
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
				clearCache();
				$this->BcMessage->setSuccess(__d('baser', 'オプション設定を保存しました。'));
				$this->redirect('config');
			}
		} else {

			$this->request->data['CuStaticConfig'] = $this->CuStaticConfig->findExpanded();

			$sites = $this->Site->find('list', [
				'fields' => [
					'id',
					'display_name',
				],
				'order' => [
					'id' => 'ASC',
				],
			]);
			$sites = array_merge(
				[0 => $this->siteConfigs['main_site_display_name']],
				$sites
			);
			$this->set('sites', $sites);

			$blogContents = $this->Content->find('list', [
				'fields' => [
					'entity_id',
					'title',
					'site_id',
				],
				'conditions' => [
					'plugin' => 'Blog',
					'type' => 'BlogContent',
				],
				'order' => [
					'site_id' => 'ASC',
					'entity_id' => 'ASC',
				],
			]);
			$this->set('blogContents', $blogContents);

		}
		$this->pageTitle = 'オプション設定';

	}

	/**
	 * [ADMIN] get status
	 */
	public function admin_get_status() {

		$this->autoRender = false;

		$CuStaticConfig = $this->CuStaticConfig->findExpanded();
		$result['status'] = (int) $CuStaticConfig['status'];
		$result['progress'] = (int) $CuStaticConfig['progress'];
		$result['progress_max'] = (int) $CuStaticConfig['progress_max'];

		return json_encode($result);
	}

	/**
	 * [ADMIN] log tail
	 */
	public function admin_tail($limit = 2500) {

		$this->autoRender = false;

		$fileName = 'cu_static.log';
		$fullName = TMP . DS . 'logs' . DS . $fileName;

		if (!file_exists($fullName)) {
			new File($fullName, true);
			return;
		}

		if ($limit == -1) {
			$lines = file($fullName);
		} else {
			// $a = 250;
			// $b = 10;
			// $c = $a * $b;
			$x = file_get_contents($fullName, false, null, filesize($fullName) - $limit);
			$lines = explode("\n", $x);
		}
		if (empty($lines) || (isset($lines[0]) && empty($lines[0]))) {
			$lines[] = '[ここに処理中の詳細ログが表示されます]';
		}

		return implode('<br>', $lines);
	}

	/**
	 * [ADMIN] log download
	 */
	public function admin_log_download() {

		$fileName = 'cu_static.log';
		$fullName = TMP . DS . 'logs' . DS . $fileName;

		$File = new File($fullName, true);
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
