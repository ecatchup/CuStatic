<?php
class CuStaticContent extends AppModel {

	public $name = 'CuStaticContent';

	/**
	 * 公開済の conditions を取得
	 *
	 * @return array
	 * @access public
	 */
	public function getConditionAllowPublish() {
		$conditions[$this->alias . '.status'] = true;
		$conditions[] = ['or' => [[$this->alias . '.publish_begin <=' => date('Y-m-d H:i:s')],
				[$this->alias . '.publish_begin' => null],
				[$this->alias . '.publish_begin' => '0000-00-00 00:00:00']]];
		$conditions[] = ['or' => [[$this->alias . '.publish_end >=' => date('Y-m-d H:i:s')],
				[$this->alias . '.publish_end' => null],
				[$this->alias . '.publish_end' => '0000-00-00 00:00:00']]];
		return $conditions;
	}

	/**
	 * 公開状態を取得する
	 *
	 * @param array $data モデルデータ
	 * @return boolean 公開状態
	 */
	public function allowPublish($data) {
		if (isset($data[$this->alias])) {
			$data = $data[$this->alias];
		}

		$allowPublish = (int)$data['status'];

		if ($data['publish_begin'] == '0000-00-00 00:00:00') {
			$data['publish_begin'] = null;
		}
		if ($data['publish_end'] == '0000-00-00 00:00:00') {
			$data['publish_end'] = null;
		}

		// 期限を設定している場合に条件に該当しない場合は強制的に非公開とする
		if (($data['publish_begin'] && $data['publish_begin'] >= date('Y-m-d H:i:s')) ||
			($data['publish_end'] && $data['publish_end'] <= date('Y-m-d H:i:s'))) {
			$allowPublish = false;
		}

		return $allowPublish;
	}
}
