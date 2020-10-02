<?php
class CuStaticModelEventListener extends BcModelEventListener {

	/**
	 * 登録イベント
	 *
	 * @var array
	 */
	public $events = array(
		'Blog.BlogPost.beforeDelete',
		'Content.afterSave',
	);

	/**
	 * ブログ記事：削除
	 */
	public function blogBlogPostBeforeDelete(CakeEvent $event) {
		if (!BcUtil::isAdminSystem()) {
			return;
		}
		$Model = $event->subject();
		$modelData = $Model->data;
		$params = Router::getParams();
		$modelData['Content'] = $params['Content'];
		$data = CuStaticUtil::getContentsData($modelData);
		$data['type'] = 'BlogPost';
		$data['content_id'] = $modelData['BlogPost']['blog_content_id'];
		$data['entity_id'] = $modelData['BlogPost']['id'];
		$data['url'] .= 'arcives/' . $modelData['BlogPost']['no'];
		CuStaticUtil::setContentsData($data);
		return true;
	}

	/**
	 * コンテンツ 更新時（Controllerのイベントでカバーできない分）
	 *
	 * @param CakeEvent $event
	 * @return type
	 */
	public function contentAfterSave(CakeEvent $event) {

		if (!BcUtil::isAdminSystem()) {
			return;
		}

		$targetActions = ['admin_add', 'admin_edit', 'admin_ajax_copy'];
		$params = Router::getParams();
		if (!in_array($params['action'], $targetActions)) {
			return;
		}

		$Model = $event->subject();
		$types = Configure::read('CuStatic.types');
		if (!in_array($Model->data[$Model->alias]['type'], $types)) {
			return;
		}

		$modelData = $Model->data;
		$id = $Model->id;
		$data = CuStaticUtil::getContentsData($modelData);
		$data['url'] = ''; // ここでは取得できないので書き出し時に再度URLを組み立てる
		CuStaticUtil::setContentsData($data);

		// フォルダの場合に下層にコンテンツが有る場合はすべてチェック
		if ($data['type'] == 'ContentFolder') {
			$children = $Model->children($id);
			foreach($children as $content) {
				$data = CuStaticUtil::getContentsData($content);
				$data['url'] = ''; // ここでは取得できないので書き出し時に再度URLを組み立てる
				CuStaticUtil::setContentsData($data);
			}
		}

	}
}
