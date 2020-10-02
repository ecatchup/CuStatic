<?php
class CuStaticControllerEventListener extends BcControllerEventListener {

	/**
	 * 登録イベント
	 *
	 * @var array
	 */
	public $events = array(
		'Pages.afterAdd',
		'Pages.afterEdit',
		'Blog.BlogPosts.afterAdd',
		'Blog.BlogPosts.afterEdit',

		// 'Contents.afterAdd',
		// 'Contents.afterEdit',
		'Contents.afterMove',
		'Contents.afterChangeStatus',
		'Contents.beforeDelete',
		// 'ContentFolders.afterAdd',
		// 'ContentFolders.afterEdit',
		// 'ContentLinks.afterAdd',
		// 'ContentLinks.afterEdit',
	);

	/**
	 * 固定ページ：新規作成
	 */
	public function pagesAfterAdd(CakeEvent $event) {

		if (!BcUtil::isAdminSystem()) {
			return;
		}

		$data = CuStaticUtil::getContentsData($event->data['data']);
		CuStaticUtil::setContentsData($data);

		return true;
	}

	/**
	 * 固定ページ：編集
	 */
	public function pagesAfterEdit(CakeEvent $event) {

		if (!BcUtil::isAdminSystem()) {
			return;
		}

		$data = CuStaticUtil::getContentsData($event->data['data']);
		CuStaticUtil::setContentsData($data);

		return true;
	}

	/**
	 * ブログ記事：新規作成
	 */
	public function blogBlogPostsAfterAdd(CakeEvent $event) {

		if (!BcUtil::isAdminSystem()) {
			return;
		}

		$Controller = $event->subject();
		$modelData = $event->data['data'];
		$modelData['Content'] = $Controller->request->params['Content'];
		$data = CuStaticUtil::getContentsData($modelData);
		$data['type'] = 'BlogPost';
		$data['content_id'] = $modelData['BlogPost']['blog_content_id'];
		$data['entity_id'] = $modelData['BlogPost']['id'];
		$data['url'] .= 'arcives/' . $modelData['BlogPost']['no'];
		CuStaticUtil::setContentsData($data);

		return true;
	}

	/**
	 * ブログ記事：編集
	 */
	public function blogBlogPostsAfterEdit(CakeEvent $event) {

		if (!BcUtil::isAdminSystem()) {
			return true;
		}

		$Controller = $event->subject();
		$modelData = $event->data['data'];
		$modelData['Content'] = $Controller->request->params['Content'];
		$data = CuStaticUtil::getContentsData($modelData);
		$data['type'] = 'BlogPost';
		$data['content_id'] = $modelData['BlogPost']['blog_content_id'];
		$data['entity_id'] = $modelData['BlogPost']['id'];
		$data['url'] .= 'arcives/' . $modelData['BlogPost']['no'];
		CuStaticUtil::setContentsData($data);

		return true;
	}

	// public function contentsAfterAdd(CakeEvent $event) {
	// 	if (!BcUtil::isAdminSystem()) {
	// 		return;
	// 	}
	// 	$this->log(['contentsAfterAdd', $event->data]);
	// 	$data = CuStaticUtil::getContentsData($event->data['data']);
	// 	CuStaticUtil::setContentsData($data);
	// 	return true;
	// }

	// public function contentsAfterEdit(CakeEvent $event) {
	// 	if (!BcUtil::isAdminSystem()) {
	// 		return;
	// 	}
	// 	$this->log(['contentsAfterEdit', $event->data]);
	// 	$data = CuStaticUtil::getContentsData($event->data['data']);
	// 	CuStaticUtil::setContentsData($data);
	// 	return true;
	// }

	/**
	 * コンテンツ管理：移動
	 */
	public function contentsAfterMove(CakeEvent $event) {

		if (!BcUtil::isAdminSystem()) {
			return;
		}

		$ContentModel = ClassRegistry::init('Content');
		$id = $event->data['data']['Content']['id'];
		$modelData = $ContentModel->read(null, $id);
		$data = CuStaticUtil::getContentsData($modelData);
		CuStaticUtil::setContentsData($data);

		// フォルダの場合に下層にコンテンツが有る場合はすべてチェック
		if ($data['type'] == 'ContentFolder') {
			$children = $ContentModel->children($id);
			foreach($children as $content) {
				$data = CuStaticUtil::getContentsData($content);
				CuStaticUtil::setContentsData($data);
			}
		}

		return true;
	}

	/**
	 * コンテンツ管理：公開・非公開の切り替え
	 */
	public function contentsAfterChangeStatus(CakeEvent $event) {

		if (!BcUtil::isAdminSystem()) {
			return;
		}

		$ContentModel = ClassRegistry::init('Content');
		$id = $event->data['id'];
		$modelData = $ContentModel->read(null, $id);
		$data = CuStaticUtil::getContentsData($modelData);
		CuStaticUtil::setContentsData($data);

		// フォルダの場合に下層にコンテンツが有る場合はすべてチェック
		if ($data['type'] == 'ContentFolder') {
			$children = $ContentModel->children($id);
			foreach($children as $content) {
				$data = CuStaticUtil::getContentsData($content);
				CuStaticUtil::setContentsData($data);
			}
		}

		return true;
	}

	/**
	 * コンテンツ管理：ゴミ箱へ移動（削除）
	 */
	public function contentsBeforeDelete(CakeEvent $event) {

		if (!BcUtil::isAdminSystem()) {
			return true;
		}

		$ContentModel = ClassRegistry::init('Content');
		$id = $event->data['data'];
		$modelData = $ContentModel->read(null, $id);
		$data = CuStaticUtil::getContentsData($modelData);
		CuStaticUtil::setContentsData($data);

		// フォルダの場合に下層にコンテンツが有る場合はすべてチェック
		if ($data['type'] == 'ContentFolder') {
			$children = $ContentModel->children($id);
			foreach($children as $content) {
				$data = CuStaticUtil::getContentsData($content);
				CuStaticUtil::setContentsData($data);
			}
		}

		return true;
	}

	// public function contentFoldersAfterAdd(CakeEvent $event) {
	// 	if (!BcUtil::isAdminSystem()) {
	// 		return;
	// 	}
	// 	$this->log(['contentFoldersAfterAdd', $event->data]);
	// 	$data = CuStaticUtil::getContentsData($event->data['data']);
	// 	CuStaticUtil::setContentsData($data);
	// 	return true;
	// }

	// public function contentFoldersAfterEdit(CakeEvent $event) {
	// 	if (!BcUtil::isAdminSystem()) {
	// 		return;
	// 	}
	// 	$this->log(['contentFoldersAfterEdit', $event->data]);
	// 	$data = CuStaticUtil::getContentsData($event->data['data']);
	// 	CuStaticUtil::setContentsData($data);
	// 	return true;
	// }

}

