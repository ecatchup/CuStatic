<?php

class BcStaticConfig extends BlogAppModel {

	public $name = 'BcStaticConfig';

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

}
