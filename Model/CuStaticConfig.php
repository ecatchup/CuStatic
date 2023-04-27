<?php

class CuStaticConfig extends AppModel {

	public $name = 'CuStaticConfig';

	public $actsAs = [
		'BcCache',
	];

	public function __construct($id = false, $table = null, $ds = null) {
		parent::__construct($id, $table, $ds);

		$this->validate = [
			'exportPath' => [
				[
					'rule' => [
						'notBlank',
					],
					'message' => __d('baser', '出力先フォルダを入力してください。'),
					'required' => true,
				],
			],
		];
	}

	/**
	 * 必要な初期値がない場合追加する
	 */
	public function setDefaultStatus() {
		$defaultStatus = [
			'status' => 0,
			'progress' => 0,
			'progress_max' => 0,
		];
		foreach($defaultStatus as $key => $value) {
			$statusData = $this->find('first', [
				'conditions' => [
					'name' => $key
				],
				'recuresive' => -1,
				'callbacks' => false,
			]);
			if (empty($statusData)) {
				$this->create([
					$this->alias => [
						'name' => $key,
						'value' => $value,
					]
				]);
				$this->save(null, false);
			}
		}
	}

}
