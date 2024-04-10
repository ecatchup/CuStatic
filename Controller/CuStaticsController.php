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

		$this->pageTitle = '静的HTML出力';

		if ($this->request->data) {

			// 同期処理実行中の場合は強制終了
			// if (file_exists('/PATH/TO/custatic-output-procfile')) {
			// 	$this->BcMessage->setError(__d('baser', '公開サーバ同期中です。しばらくお待ち下さい。'));
			// 	$this->redirect('index');
			// }

			$command = sprintf(Configure::read('CuStatic.command'), 'main');
			$cmd = CakePlugin::path('CuStatic') . 'Shell' . DS . $command;
			$this->log($cmd, LOG_CUSTATIC);
			exec($cmd, $output, $resultCode);
			if ($output || $resultCode) {
				$this->log($cmd);
				$this->log($output);
				$this->log($resultCode);
			}
			$this->redirect(['action' => 'index']);
		}

		$this->set('cuStaticConfigs', $this->CuStaticConfig->findExpanded());
	}

	/**
	 * [ADMIN] config
	 */
	public function admin_config() {

		$this->pageTitle = '[静的HTML出力] オプション設定';

		if ($this->request->data) {
			if (!$this->request->data('CuStaticConfig.status_change')) {
				unset($this->request->data['CuStaticConfig']['status']);
			}
			$user = BcUtil::loginUser();
			$this->request->data['CuStaticConfig']['user_id'] = $user['id'];
			$this->CuStaticConfig->set($this->request->data);
			if (!$this->CuStaticConfig->validates()) {
				$this->BcMessage->setError(__d('baser', '入力エラーです。内容を修正してください。'));
			} else {
				$this->CuStaticConfig->saveKeyValue($this->request->data);
				$this->CuStaticConfig->setDefaultStatus();
				clearCache();
				$this->BcMessage->setSuccess(__d('baser', $this->pageTitle . 'を保存しました。'));
				$this->redirect(['action' => 'config']);
			}
		} else {

			$this->request->data['CuStaticConfig'] = $this->CuStaticConfig->findExpanded();

			$sites = $this->Site->find('list', [
				'fields' => [
					'id',
					'display_name',
				],
				'conditions' => [
					'status' => true,
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

			$contents = $this->Content->find('all', [
				'conditions' => [
					'plugin' => 'Blog',
					'type' => 'BlogContent',
				],
				'order' => [
					'site_id' => 'ASC',
					'entity_id' => 'ASC',
				],
				'recursive' => -1,
			]);

			$blogContents = [];
			foreach ($contents as $content) {
				$blogContents[$content['Content']['site_id']][] = $content['Content'];
			}
			$this->set('blogContents', $blogContents);
		}
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
