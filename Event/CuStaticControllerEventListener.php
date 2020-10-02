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
		'Contents.afterMove',
		'Contents.afterChangeStatus',
		'Contents.beforeDelete',
		// 'Contents.afterAdd',
		// 'Contents.afterEdit',
		// 'ContentFolders.afterAdd',
		// 'ContentFolders.afterEdit',
	);

	/**
	 * 固定ページ：新規作成
	 */
	public function pagesAfterAdd(CakeEvent $event) {
		return $this->pagesAfterProc($event);
	}

	/**
	 * 固定ページ：編集
	 */
	public function pagesAfterEdit(CakeEvent $event) {
		return $this->pagesAfterProc($event);
	}

	/**
	 * 固定ページ：共通処理
	 */
	private function pagesAfterProc(CakeEvent $event) {

		if (!BcUtil::isAdminSystem()) {
			return;
		}

		$modelData = $event->data['data'];

		$data = CuStaticUtil::getContentsData($modelData);
		CuStaticUtil::setContentsData($data);

		return true;
	}

	/**
	 * ブログ記事：新規作成
	 */
	public function blogBlogPostsAfterAdd(CakeEvent $event) {
		return $this->blogBlogPostsAfterProc($event);
	}

	/**
	 * ブログ記事：編集
	 */
	public function blogBlogPostsAfterEdit(CakeEvent $event) {
		return $this->blogBlogPostsAfterProc($event);
	}

	/**
	 * ブログ編集：共通処理
	 */
	private function blogBlogPostsAfterProc(CakeEvent $event) {

		if (!BcUtil::isAdminSystem()) {
			return;
		}

		$Controller = $event->subject();
		$modelData = $event->data['data'];
		$modelData['Content'] = $Controller->request->params['Content'];

		$data = CuStaticUtil::getContentsData($modelData);
		CuStaticUtil::setContentsData($data);

		$data['type'] = 'BlogPost';
		$data['content_id'] = $modelData['BlogPost']['blog_content_id'];
		$data['entity_id'] = $modelData['BlogPost']['id'];
		$data['url'] .= 'arcives/' . $modelData['BlogPost']['no'];
		CuStaticUtil::setContentsData($data);

		// 設定画面で指定されている追加のURLの処理
		$CuStaticConfigModel = ClassRegistry::init('CuStatic.CuStaticConfig');
		$CuStaticConfig = $CuStaticConfigModel->findExpanded();
		$prefix = sprintf('_%s_%s', $data['site_id'], $data['content_id']);
		if (isset($CuStaticConfig['blog_callback' . $prefix])) {
			$blogCallback = preg_replace("/\r\n|\r|\n/", PHP_EOL, $CuStaticConfig['blog_callback' . $prefix]);
			$urls = explode(PHP_EOL, $blogCallback);
			$ContentModel = ClassRegistry::init('Content');
			foreach($urls as $url) {
				$modelData = $ContentModel->find('first', [
					'conditions' => [
						'url' => $url,
					],
					'recursive' => -1,
				]);
				$data = CuStaticUtil::getContentsData($modelData);
				CuStaticUtil::setContentsData($data);
			}
		}
		return true;
	}

	/**
	 * コンテンツ管理：移動
	 */
	public function contentsAfterMove(CakeEvent $event) {

		if (!BcUtil::isAdminSystem()) {
			return;
		}

		$id = $event->data['data']['Content']['id'];
		$ContentModel = ClassRegistry::init('Content');
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

		$id = $event->data['id'];
		$ContentModel = ClassRegistry::init('Content');
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

		$id = $event->data['data'];
		$ContentModel = ClassRegistry::init('Content');
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

	// public function contentsAfterAdd(CakeEvent $event) {

	// 	if (!BcUtil::isAdminSystem()) {
	// 		return;
	// 	}

	// 	$modelData = $event->data['data'];

	// 	$data = CuStaticUtil::getContentsData($modelData);
	// 	CuStaticUtil::setContentsData($data);

	// 	return true;
	// }

	// public function contentsAfterEdit(CakeEvent $event) {

	// 	if (!BcUtil::isAdminSystem()) {
	// 		return;
	// 	}

	// 	$modelData = $event->data['data'];

	// 	$data = CuStaticUtil::getContentsData($modelData);
	// 	CuStaticUtil::setContentsData($data);

	// 	return true;
	// }

	// public function contentFoldersAfterAdd(CakeEvent $event) {

	// 	if (!BcUtil::isAdminSystem()) {
	// 		return;
	// 	}

	// 	$modelData = $event->data['data'];

	// 	$data = CuStaticUtil::getContentsData($modelData);
	// 	CuStaticUtil::setContentsData($data);

	// 	return true;

	// }

	// public function contentFoldersAfterEdit(CakeEvent $event) {

	// 	if (!BcUtil::isAdminSystem()) {
	// 		return;
	// 	}

	// 	$modelData = $event->data['data'];

	// 	$data = CuStaticUtil::getContentsData($modelData);
	// 	CuStaticUtil::setContentsData($data);

	// 	return true;

	// }

}

