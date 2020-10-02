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

	/**
	 * コンテンツデータよりURLを生成する
	 *
	 * @param int $id コンテンツID
	 * @param string $plugin プラグイン
	 * @param string $type タイプ
	 * @return mixed URL | false
	 */
	public function createUrl($id, $plugin = null, $type = null) {
		$id = (int) $id;
		// @deprecated 5.0.0 since 4.0.2 $plugin / $type の引数は不要
		if(!$id) {
			return false;
		} elseif($id == 1) {
			$url = '/';
		} else {
			var_dump($id);
			// =========================================================================================================
			// サイト全体のURLを変更する場合、TreeBehavior::getPath() を利用するとかなりの時間がかかる為、DataSource::query() を利用する
			// 2018/02/04 ryuring
			// プリペアドステートメントを利用する為、fetchAll() を利用しようとしたが、SQLite のドライバが対応してない様子。
			// CakePHP３系に対応する際、SQLite を標準のドライバに変更してから、プリペアドステートメントに書き換えていく。
			// それまでは、SQLインジェクション対策として、値をチェックしてから利用する。
			// =========================================================================================================
			$db = $this->getDataSource();
			// $sql = "SELECT lft, rght FROM {$this->tablePrefix}contents AS Content WHERE id = {$id} AND deleted = " . $db->value(false, 'boolean');
			$sql = "SELECT lft, rght FROM {$this->tablePrefix}contents AS Content WHERE id = {$id}";
			$content = $db->query($sql, false);
			if(!$content) {
				return false;
			}
			if(isset($content[0]['Content'])) {
				$content = $content[0]['Content'];
			} else {
				$content = $content[0][0];
			}
			// $sql = "SELECT name, plugin, type FROM {$this->tablePrefix}contents AS Content " .
			// 		"WHERE lft <= {$db->value($content['lft'], 'integer')} AND rght >= {$db->value($content['rght'], 'integer')} AND deleted =  " . $db->value(false, 'boolean') . " " .
			// 		"ORDER BY lft ASC";
			$sql = "SELECT name, plugin, type FROM {$this->tablePrefix}contents AS Content " .
					"WHERE lft <= {$db->value($content['lft'], 'integer')} AND rght >= {$db->value($content['rght'], 'integer')} " .
					"ORDER BY lft ASC";
			$parents = $db->query($sql, false);
			unset($parents[0]);
			if(!$parents) {
				return false;
			}
			$names = [];
			$content = null;
			foreach($parents as $parent) {
				if(isset($parent['Content'])) {
					$parent = $parent['Content'];
				} else {
					$parent = $parent[0];
				}
				$names[] = $parent['name'];
				$content = $parent;
			}
			$plugin = $content['plugin'];
			$type = $content['type'];
			$url = '/' . implode('/', $names);
			$setting = $omitViewAction = Configure::read('BcContents.items.' . $plugin . '.' . $type);
			if($type == 'ContentFolder' || empty($setting['omitViewAction'])) {
				$url .= '/';
			}
		}
		return $url;
	}
}
