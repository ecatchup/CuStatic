<?php

class CuStaticUtil {

	/**
	 * CuStaticContent（書き出し用データ）作成
	 *
	 * @param array $modelData
	 * @return array $data;
	 */
	public static function getContentsData($modelData) {

		$data = [];

		if (!isset($modelData['Content'])) {
			return $data;
		}
		$params = Router::getParams();
		$data['controller'] = $params['controller'];
		$data['action'] = $params['action'];

		$data['name'] = $modelData['Content']['name'];
		$data['plugin'] = $modelData['Content']['plugin'];
		$data['type'] = $modelData['Content']['type'];
		if (isset($modelData['Content']['id'])) {
			$data['content_id'] = $modelData['Content']['id'];
		} else {
			$data['content_id'] = null;
		}
		$data['entity_id'] = $modelData['Content']['entity_id'];
		if (isset($modelData['Content']['url']) && !empty($modelData['Content']['url'])) {
			$data['url'] = $modelData['Content']['url'];
		} else {
			$data['url'] = null;
		}
		$data['site_id'] = $modelData['Content']['site_id'];
		$data['meta'] = serialize($modelData);

		return $data;
	}

	/**
	 * CuStaticContent（書き出し用データ）保存
	 *
	 * @param array $params
	 */
	public static function setContentsData($params) {

		$CuStaticContentModel = ClassRegistry::init('CuStatic.CuStaticContent');

		if (!isset($params[0])) {
			$params = [$params];
		}

		foreach ($params as $param) {
			if (empty($param['entity_id'])) {
				continue;
			}
			$CuStaticContentModel->deleteAll([
				'name' => $param['name'],
				'plugin' => $param['plugin'],
				'type' => $param['type'],
				'content_id' => $param['content_id'],
				'entity_id' => $param['entity_id'],
			]);
			// $CuStaticContentModel->updateAll([
			// 	'status' => false,
			// 	'publish_begin' => null,
			// 	'publish_end' => null,
			// ],[
			// 	'plugin' => $param['plugin'],
			// 	'type' => $param['type'],
			// 	'content_id' => $param['content_id'],
			// 	'entity_id' => $param['entity_id'],
			// ]);
			$data['CuStaticContent'] = $param;
			$CuStaticContentModel->create($data);
			if (!$CuStaticContentModel->save($data, array('callbacks' => false))) {
				CakeLog::write(1,'[setContentsData] 保存に失敗しました。');
				exit;
			}
		}
	}
}
