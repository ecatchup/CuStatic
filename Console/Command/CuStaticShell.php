<?php
class CuStaticShell extends Shell {

	/**
	 * モデル
	 *
	 * @var array
	 * @access public
	 */
	public $uses = array(
		'Content',
		'Site',
		'User',
		'Blog.BlogContent',
		'Blog.BlogPost',
		'Blog.BlogCategory',
		'Blog.BlogTag',
		'CuStatic.CuStaticConfig',
	);

	/**
	 * Welcome to CakePHP vx.x.x Console
	 */
	// public function _welcome() {
	// 	// none
	// }

	/**
	 * 動的コンテンツ出力 CRON同期
	 */
	public function main() {

		$this->log('[exportHtml] Start ===================================================', LOG_CUSTATIC);
		$this->exportHtml();
		$this->log('[exportHtml] End   ===================================================', LOG_CUSTATIC);

	}

	/**
	 *
	 */
	private function exportHtml() {

		$siteConfig = Configure::read('BcSite');
		$CuStaticConfig = $this->CuStaticConfig->findExpanded();

		if ($CuStaticConfig['status']) {
			$this->log('[exportHtml] Currently being processed. Suspend.', LOG_CUSTATIC);
			return;
		}

		$CuStaticConfig['status'] = 1;
		$CuStaticConfig['progress'] = 1;
		$this->CuStaticConfig->saveKeyValue($CuStaticConfig);

		// 書き出し先のフォルダ
		$exportPath = $CuStaticConfig['exportPath'];
		$exportPath = rtrim($exportPath, DS) . DS;

		if (empty($exportPath)) {
			$exportPath = Configure::read('CuStatic.exportPath');
		}

		$exportFolder = new Folder($exportPath);
		if (file_exists($exportPath)) {
			$exportFolder->delete();
		}
		$exportFolder->create($exportPath, 0777);

		$this->log('exportPath: ' . $exportPath, LOG_CUSTATIC);

		// ベースとなるURL作成
		$baseUrl = Configure::read('CuStatic.baseUrl');
		if (empty($baseUrl)) {
			$baseUrl = Configure::read('BcEnv.siteUrl');
		}
		$baseUrl = rtrim($baseUrl, '/');
		$this->log('baseUrl: ' . $baseUrl, LOG_CUSTATIC);

		// ===================================================
		// Plugin内webrootファイル対応
		// ===================================================

		// 有効化しているプラグイン一覧
		// $enablePlugins = getEnablePlugins();
		// $enablePlugins = Hash::extract($enablePlugins, '{n}.Plugin.name');
		$enablePlugins = Configure::read('CuStatic.plugins');

		// インストールされているプラグインフォルダ
		$pluginFolders = [
			BASER_PLUGINS,
			APP . 'Plugin' . DS,
			WWW_ROOT . 'theme' . DS . $siteConfig['theme'] . DS . 'Plugin' . DS,
		];

		foreach ($pluginFolders as $pluginFolder) {
			$folder = new Folder($pluginFolder);
			$plugins = $folder->read();
			foreach ($plugins[0] as $pluginName) {
				if (in_array($pluginName, $enablePlugins, true)) {
					$pluginPath = Inflector::underscore($pluginName);
					$path = $pluginFolder . $pluginName . DS . 'webroot' . DS;
					if (file_exists($path)) {
						$webrootFolder = new Folder($path);
						$webrootFolder->copy([
							'mode' => 0755,
							'to' => $exportPath . $pluginPath,
							'skip' => [
								'admin',	// adminフォルダ内は不要
							],
							'scheme' => Folder::OVERWRITE,
							'recursive' => true,
						]);
						$this->log('copy: ' . $exportPath . $pluginPath, LOG_CUSTATIC);
					}
				}
			}
		}

		// ===================================================
		// 静的コンテンツ(css,js,img,files)
		// ===================================================
		$staticFolders = [
			'css',
			'js',
			'img',
			'files',
			'theme' . DS . $siteConfig['theme'] . DS . 'css',
			'theme' . DS . $siteConfig['theme'] . DS . 'js',
			'theme' . DS . $siteConfig['theme'] . DS . 'img',
			'theme' . DS . $siteConfig['theme'] . DS . 'files',
		];
		foreach ($staticFolders as $staticFolder) {
			$path = WWW_ROOT . $staticFolder . DS;
			$folder = new Folder($path);
			$folder->copy([
				'mode' => 0755,
				'to' => $exportPath . $staticFolder,
				'skip' => [
					'admin',	// adminフォルダ内は不要
				],
				'scheme' => Folder::OVERWRITE,
				'recursive' => true,
			]);
			$this->log('copy: ' . $exportPath . $staticFolder, LOG_CUSTATIC);
		}

		$CuStaticConfig['progress']++;
		$this->CuStaticConfig->saveKeyValue($CuStaticConfig);

		// ===================================================
		// コンテンツ管理テーブル
		// ===================================================

		// メインサイト、サブサイトの順に書き出し
		$siteIds = $this->Site->find('list', [
			'fields' => [
				'id',
			],
			'conditions' => [
				'status' => true,
			],
			'recursive' => -1,
		]);

		// コンテンツテーブルをチェック
		array_unshift($siteIds, 0);
		foreach($siteIds as $siteId) {
			$contents = $this->Content->find('all', [
				'conditions' => [
					'site_id' => $siteId,
					'status' => true,
				],
				'order' => [
					'lft' => 'ASC',
					'rght' => 'ASC',
				],
				'recursive' => -1,
			]);

			foreach ($contents as $content) {
				$pageUrl = ltrim($content['Content']['url'], '/');
				$pagePath = str_replace('/', DS, $pageUrl);

				switch ($content['Content']['type']):
					case 'ContentFolder':
						$CuStaticConfig['progress']++;
						$this->CuStaticConfig->saveKeyValue($CuStaticConfig);

						$url = $baseUrl . '/' . $pageUrl;
						$path = $exportPath . $pagePath ;
						$this->makeHtml($url, $path . 'index.html');
						break;

					case 'Page':
						$CuStaticConfig['progress']++;
						$this->CuStaticConfig->saveKeyValue($CuStaticConfig);

						if ($CuStaticConfig['page']) {
							$url = $baseUrl . '/' . $pageUrl;
							$path = $exportPath . $pagePath;
							$this->makeHtml($url, $path . '.html');
						}
						break;

					case 'BlogContent':
						$CuStaticConfig['progress']++;
						$this->CuStaticConfig->saveKeyValue($CuStaticConfig);

						$blogContent = $this->BlogContent->find('first', [
							'conditions' => [
								'BlogContent.id' => $content['Content']['entity_id']
							],
							'recursive' => -1
						]);
						$listCount = $blogContent['BlogContent']['list_count'];
						$conditionAllowPublish = $this->BlogPost->getConditionAllowPublish();

						$blogPosts = $this->BlogPost->find('all', [
							'conditions' => [
								'BlogPost.blog_content_id' => $content['Content']['entity_id'],
								$conditionAllowPublish,
							],
 						]);

						// index
						if ($CuStaticConfig['blog_index']) {
							$targetUrl = 'index';
							$targetPath = str_replace('/', DS, $targetUrl);
							$url = $baseUrl . '/' . $pageUrl . $targetUrl;
							$path = $exportPath . $pagePath . $targetPath;

							$dir = new Folder($exportPath . $pagePath, 0777);
							$dir->delete();

							$this->makeHtml($url, $path . '.html');

							$blogPostsCount = count($blogPosts);
							$this->makePagingHtml($blogPostsCount, $listCount, $url, $path);

							// rss対応
							$this->makeHtml($url . '.rss', $path . '.rss');
						}

						// category
						if ($CuStaticConfig['blog_category']) {
							$this->BlogCategory->reduceAssociations(['BlogCategory', 'BlogPost']);
							$this->BlogCategory->hasMany['BlogPost']['conditions'] = $conditionAllowPublish;
							$blogCategories = $this->BlogCategory->find('all', [
								'conditions' => [
									'BlogCategory.blog_content_id' => $content['Content']['entity_id'],
								],
								'recursive' => -1,
							]);
							foreach ($blogCategories as $blogCategory) {
								$targetUrl = 'archives/category/' . $blogCategory['BlogCategory']['name'];
								$targetPath = str_replace('/', DS, $targetUrl);
								$url = $baseUrl . '/' . $pageUrl . $targetUrl;
								$path = $exportPath . $pagePath . $targetPath;
								$this->makeHtml($url, $path . '.html');

								// category paging
								$blogPostsCount = count(Hash::extract($blogPosts, '{n}.BlogPost[blog_content_id=' . $content['Content']['entity_id'] . '][blog_category_id=' . $blogCategory['BlogCategory']['id'] . ']'));
								$this->makePagingHtml($blogPostsCount, $listCount, $url, $path);
							}
						}

						// tags
						if ($CuStaticConfig['blog_tag']) {
							if ($blogContent['BlogContent']['tag_use']) {
								$this->BlogTag->reduceAssociations(['BlogTag', 'BlogPost']);
								$this->BlogTag->hasAndBelongsToMany['BlogPost']['conditions'] = $conditionAllowPublish;
								$blogTags = $this->BlogTag->find('all', [
									'conditions' => [
									],
									'recursive' => 2,
								]);
								foreach ($blogTags as $blogTag) {
									$targetUrl = 'archives/tag/' . $blogTag['BlogTag']['name'];
									$targetPath = str_replace('/', DS, $targetUrl);
									$url = $baseUrl . '/' . $pageUrl . $targetUrl;
									$path = $exportPath . $pagePath . $targetPath;
									$this->makeHtml($url, $path . '.html');

									// tags paging
									$blogPostsCount = count(Hash::extract($blogTag['BlogPost'], '{n}[blog_content_id=' . $content['Content']['entity_id'] . ']'));
									$this->makePagingHtml($blogPostsCount, $listCount, $url, $path);
								}
							}
						}

						// date
						$dateFormats = [];
						if ($CuStaticConfig['blog_date_year']) $dateFormats[] = 'Y';
						if ($CuStaticConfig['blog_date_month']) $dateFormats[] = 'Y/m';
						if ($CuStaticConfig['blog_date_day']) $dateFormats[] = 'Y/m/d';
						if ($dateFormats) {
							foreach ($dateFormats as $dateFormat) {
								$dateCount = array();
								foreach ($blogPosts as $blogPost) {
									$date = date($dateFormat, strtotime($blogPost['BlogPost']['posts_date']));
									if (array_key_exists($date, $dateCount)) {
										$dateCount[$date]++;
									} else {
										$dateCount[$date] = 1;
									}
								}
								foreach ($dateCount as $date => $blogPostsCount) {
									$targetUrl = 'archives/date/' . $date;
									$targetPath = str_replace('/', DS, $targetUrl);
									$url = $baseUrl . '/' . $pageUrl . $targetUrl;
									$path = $exportPath . $pagePath . $targetPath;
									$this->makeHtml($url, $path . '.html');

									// date paging
									$this->makePagingHtml($blogPostsCount, $listCount, $url, $path);
								}
							}
						}

						// author
						if ($CuStaticConfig['blog_author']) {
							$users = $this->User->find('all');
							foreach ($users as $user) {
								$targetUrl = 'archives/author/' . $user['User']['name'];
								$targetPath = str_replace('/', DS, $targetUrl);
								$url = $baseUrl . '/' . $pageUrl . $targetUrl;
								$path = $exportPath . $pagePath . $targetPath;
								$this->makeHtml($url, $path . '.html');

								// author paging
								$blogPostsCount = count(Hash::extract($blogPosts, '{n}.BlogPost[blog_content_id=' . $content['Content']['entity_id'] . '][user_id=' . $user['User']['id'] . ']'));
								$this->makePagingHtml($blogPostsCount, $listCount, $url, $path);
							}
						}

						// single
						if ($CuStaticConfig['blog_single']) {
							$blogPosts = $this->BlogPost->find('all', [
								'conditions' => [
									'BlogPost.blog_content_id' => $content['Content']['entity_id'],
									$conditionAllowPublish,
								],
							]);
							foreach ($blogPosts as $blogPost) {
								$targetUrl = 'archives/' . $blogPost['BlogPost']['no'];
								$targetPath = str_replace('/', DS, $targetUrl);
								$url = $baseUrl . '/' . $pageUrl . $targetUrl;
								$path = $exportPath . $pagePath . $targetPath;
								$this->makeHtml($url, $path . '.html');
							}
						}
						break;

					default:
						break;

				endswitch;

			}

			$CuStaticConfig['status'] = 0;
			$CuStaticConfig['progress']++;
			$this->CuStaticConfig->saveKeyValue($CuStaticConfig);
		}

	}

	private function makePagingHtml($blogPostsCount, $listCount, $targetUrl, $targetPath) {

		if ($blogPostsCount > $listCount) {
			$pageMax = ceil($blogPostsCount / $listCount);
			for ($i = 2; $i <= $pageMax; $i++) {
				$url = $targetUrl . '/page:' . $i;
				$path = $targetPath . DS . $i . '.html';
				$this->makeHtml($url, $path);
			}
		}

	}

	/**
	 * ファイル書き出し
	 */
	private function makeHtml($url, $path)  {

		$this->log('url: ' . $url, LOG_CUSTATIC);
		$this->log('path: ' . $path, LOG_CUSTATIC);

		// $getData = file_get_contents($url);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		// curl_setopt($ch, CURLOPT_USERPWD, "id:pass");	// basic認証対策
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);	// 自己証明書対策
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);	//
		$getData = curl_exec($ch);
		if(curl_exec($ch) === false) {
			$this->log('[makeHtml] Curl error: ' . curl_error($ch), LOG_CUSTATIC);
		}
		curl_close($ch);

		// html内のURL書き換え処理
		$getData = $this->convertHtmlLink($getData);

		// フォルダがない場合は自動で作成
		$exportPath = dirname($path) . DS;
		new Folder($exportPath, true, 0775);

		// HTML書き出し
		file_put_contents($path, $getData);
		chmod($path, 0664);

	}

	/**
	 * html内のURL書き換え処理
	 */
	private function convertHtmlLink($getData) {

		libxml_use_internal_errors(TRUE);

		$encode = mb_detect_encoding($getData);
		$entities = false;

		// 文字化け対策
		if (strcasecmp($encode, 'UTF-8') === 0) {
			if (!preg_match('/Content-Type/i', $getData)) {
				// http://d.hatena.ne.jp/tohokuaiki/20120608/1339127010
				$getData = mb_convert_encoding($getData, 'HTML-ENTITIES', $encode);
				$entities = true;
			}
		} elseif (strcasecmp($encode, 'SJIS-win') === 0) {
			// http://slashdot.jp/journal/498851/php-DOM%E3%81%A7%E3%81%AEHTML%E3%83%91%E3%83%BC%E3%82%B9%E6%99%82%E3%81%AE%E6%96%87%E5%AD%97%E5%8C%96%E3%81%91%E5%AF%BE%E7%AD%96
			$getData = str_ireplace('Shift_JIS', 'CP932', $getData);
		}

		$dom = new DOMDocument();
		$dom->recover = true;
		$dom->formatOutput = true;
		$dom->validateOnParse = true;
		$dom->loadHTML($getData);

		$es = $dom->getElementsByTagName('a');
		foreach ($es as $e) {

			$href = trim($e->getAttribute('href'));

			// 外部リンク、アンカー等は書き換えない
			if (preg_match('/^(https?|ftp|tel:|mailto:|#)/', $href)) {
				continue;
			}

			// 空URLは書き換えない
			if (empty($href)) {
				continue;
			}

			// #で始まるURLは書き換えない
			if (substr($href, 0, 1) === '#') {
				continue;
			}

			// クエリパラメータ消す
			$href = (strtok($href, '?'));

			// 最後が/で終わってる場合はindexつける
			if (substr($href, -1) === '/') {
				$href .= 'index';
			}

			// URLを分解する
			$pathInfo = pathinfo($href);

			// 拡張子がある場合は書き換えない
			if (array_key_exists('extension', $pathInfo)) {
				continue;
			}

			// URLを組み立てる
			$url = $pathInfo['dirname'];
			if (substr($url, -1) !== '/') {
				$url .= '/';
			}

			if ($pathInfo['basename'] !== 'index') {
				$url .= $pathInfo['filename'];
			}

			// ページネーションの /page:2 等の対応
			$url = preg_replace('/\/page\:(\d+)$/', '/$1', $url);

			// SPサイトからPCサイトへの切替URLに対応
			// - 公開側はHTMLのためクエリーを除外する
			$parseUrl = parse_url($url);
			if ($parseUrl) {
				if (array_key_exists('query', $parseUrl)) {
					if ($parseUrl['query'] === 'smartphone=off') {
						$regexQuery = '?smartphone=off';
						$url = str_replace($regexQuery, '', $url);
					}
				}
			}

			// 最後が/でない場合は.htmlつける
			if (substr($url, -1) !== '/') {
				$url .= '.html';
			}

			$e->setAttribute('href', $url);
		}

		$getData2 = $dom->saveHTML();

		libxml_use_internal_errors(FALSE);

		// 文字化け対策
		if (strcasecmp($encode, 'UTF-8') === 0) {
			if ($entities) {
				$getData2 = mb_convert_encoding($getData2, $encode, 'HTML-ENTITIES');
			}
		} elseif (strcasecmp($encode, 'SJIS-win') === 0) {
			$getData2 = '<?php header("Content-type: application/xhtml+xml"); ?>' . "\n" .
				str_ireplace('CP932', 'Shift_JIS', $getData2);
		}

		return $getData2;
	}

	/**
	 * 相対パスから絶対URLを作成する
	 *
	 * @param string $path
	 * @param string $currentPath
	 * @param string $full
	 * @return string
	 * @access public
	 */
	private function getUrl($path, $currentPath = '', $full = false) {

		$path = trim($path);

		if (preg_match('/^https?\:\/\//', $path)) {
			return $path;
		}

		$base = Configure::read('StaticExporter.BaseUrl');
		if (empty($base)) {
			$base = Configure::read('BcEnv.siteUrl');
		}

		// URLの最後が/でなければ追加
		if (substr($base, -1) !== '/') {
			$base .= '/';
		}

		// 現在ページの処理
		if ($currentPath) {
			if (substr($base, 1) === '/') {
				$base .= substr($currentPath, 1);
			}
		}

		$parse = parse_url($base);

		// http://xxxxxx.com:8080/ の部分
		$out = '';
		if ($full) {
			$out = $parse['scheme'] . '://' . $parse['host'];
			if (isset($parse['port']) && !empty($parse['port'])) {
				$out .= ':' . $parse['port'];
			}
		}

		// baseのURLを分解して組み立てる
		$work = array();
		$baseSplit = split('/', $parse['path']);
		foreach ($baseSplit as $item) {
			if ($item) {
				array_push($work, $item);
			}
		}

		// 引数のURLを分解して組み立てる
		$pathSplit = split('/', $path);
		foreach ($pathSplit as $item) {
			if (strcmp($item, '') == 0) {
				continue;
			} elseif ($item == '.') {

			} elseif ($item == '..') {
				array_pop($work);
			} else {
				array_push($work, $item);
			}
		}

		// スマホ対応（smartphone -> sp / s）
		$smartphone = Configure::read('BcAgent.smartphone');
		if (isset($work[0]) && $work[0] === $smartphone['prefix']) {
			$work[0] = $smartphone['alias'];
		}

		// モバイル対応（mobile -> fp / m）
		$mobile = Configure::read('BcAgent.mobile');
		if (isset($work[0]) && $work[0] === $mobile['prefix']) {
			$work[0] = $mobile['alias'];
		}

		$out .= '/' . join('/', $work);

		// URLの?以降は削除
		if (preg_match('/(.*?)\?(.*?)/', $out, $matches)) {
			$out = $matches[1];
		}

		return $out;
	}
}
