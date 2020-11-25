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
		'CuStatic.CuStaticContent',
	);

	/**
	 * Welcome to CakePHP vx.x.x Console
	 */
	// public function _welcome() {
	// 	// none
	// }

	/**
	 * 動的コンテンツ出力 全件対象
	 */
	public function main() {

		$this->log('[exportHtml] main Start ===================================================', LOG_CUSTATIC);
		$options = [];
		$options['all'] = true;
		$this->exportHtml($options);
		$this->log('[exportHtml] main End   ===================================================', LOG_CUSTATIC);

	}

	/**
	 * 動的コンテンツ出力 差分対象（CRON同期などで利用する想定）
	 */
	public function diff() {

		$this->log('[exportHtml] diff Start ===================================================', LOG_CUSTATIC);
		$options = [];
		$options['all'] = false;
		$this->exportHtml($options);
		$this->log('[exportHtml] diff End   ===================================================', LOG_CUSTATIC);

	}

	/**
	 *  HTML生成のみの処理
	 *
	 * （※ 主に内部で利用）
	 */
	public function html() {

		// args[0] $url  : URL
		// args[1] $path : 書き出しファイル名（フルパス）

		// requestAction時に ViewでのURL生成を正しくする為、下記を明示的に設定
		Configure::write('App.baseUrl', '/');
		Configure::write('App.dir', '');
		Configure::write('App.webroot', '');

		$this->saveHtml(h($this->args[0]), h($this->args[1]));

	}

	/**
	 * HTML出力メイン処理
	 */
	private function exportHtml($options = []) {

		// 各種設定を読込
		$siteConfig = Configure::read('BcSite');
		$CuStaticConfig = $this->CuStaticConfig->findExpanded();

		// 既に実行中の場合は強制終了
		if (isset($CuStaticConfig['status']) && $CuStaticConfig['status']) {
			$this->log('[exportHtml] Currently being processed. Suspend.', LOG_CUSTATIC);
			return;
		}

		$now = date('Y-m-d H:i:s');

		$options = array_merge([
			'all' => true,
			'siteIds' => null,
		], $options);
		$this->log('[exportHtml] $options', LOG_CUSTATIC);
		$this->log($options, LOG_CUSTATIC);

		// 有効化しているプラグイン一覧
		// $enablePlugins = getEnablePlugins();
		// $enablePlugins = Hash::extract($enablePlugins, '{n}.Plugin.name');
		$enablePlugins = Configure::read('CuStatic.plugins');

		// 対象コンテンツの種類
		$enableTypes = Configure::read('CuStatic.types');

		// Progressbar Max計算: Page * 1 + Folder * 1 + Blog * 6 + (css,js,img,files) * 2
		$progress = 0;
		$progressMax = 0;
		if (empty($options['siteIds'])) {
			// すべてのサイトが対象
			$siteIds = $this->Site->find('list', [
				'fields' => [
					'id',
				],
				'conditions' => [
					'status' => true,
				],
				'recursive' => -1,
			]);
			$siteIds[] = 0; // メインサイトのIDを追加
		} else {
			// 指定したサイトIDのみ対象
			$siteIds = $options['siteIds'];
		}

		if ($options['all']) {
			// 全ページ対象
				$conditions = $this->Content->getConditionAllowPublish();
				$conditions['site_id'] = $siteIds;
				$conditions['type'] = $enableTypes;
				$contents = $this->Content->find('list', [
				'fields' => [
					'id',
					'type',
				],
				'conditions' => $conditions,
				'recursive' => -1,
			]);
		} else {
			// 差分でのページを対象
			$contents = $this->CuStaticContent->find('list', [
				'fields' => [
					'id',
					'type',
				],
				'conditions' => [
					'site_id' => $siteIds,
				],
				'recursive' => -1,
			]);
		}

		foreach($contents as $content) {
			if ($content == 'BlogContent') {
				$progressMax = $progressMax + 6;
			} else {
				$progressMax = $progressMax + 1;
			}
		}
		$progressMax = $progressMax + 2;

		$this->setProgressBarStatus(1);

		// 書き出し先のフォルダ
		$exportPath = $CuStaticConfig['exportPath'];
		$exportPath = rtrim($exportPath, DS) . DS;

		if (empty($exportPath)) {
			$exportPath = Configure::read('CuStatic.exportPath');
		}

		$exportFolder = new Folder($exportPath);
		if ($options['all']) {
			// 全件対象の時は書き出し先のフォルダを一旦初期化
			if (file_exists($exportPath)) {
				$exportFolder->delete();
			}
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

		$baseDir = WWW_ROOT;
		if ($baseDir == DS) {
			$baseDir = ROOT;
		}
		$baseDir = rtrim($baseDir, DS) . DS;

		// ===================================================
		// Plugin内webrootファイル対応
		// ===================================================

		// インストールされているプラグインフォルダ
		$pluginFolders = [
			BASER_PLUGINS,
			APP . 'Plugin' . DS,
			$baseDir . 'theme' . DS . $siteConfig['theme'] . DS . 'Plugin' . DS,
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
						$this->log('Copy From: ' . $path, LOG_CUSTATIC);
						$this->log('Copy To  : ' . $exportPath . $pluginPath, LOG_CUSTATIC);
					}
				}
			}
		}
		$this->setProgressBar(++$progress, $progressMax);

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
			$path = $baseDir . $staticFolder . DS;
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
			$this->log('Copy From: ' . $path, LOG_CUSTATIC);
			$this->log('Copy To  : ' . $exportPath . $staticFolder, LOG_CUSTATIC);
		}

		$this->setProgressBar(++$progress, $progressMax);

		// ===================================================
		// コンテンツ管理テーブル
		// ===================================================
		foreach($siteIds as $siteId) {

			if ($options['all']) {
				// 全ページ対象
				$conditions = $this->Content->getConditionAllowPublish();
				$conditions['site_id'] = $siteId;
				$conditions['type'] = $enableTypes;
				$contents = $this->Content->find('all', [
					'conditions' => $conditions,
					'order' => [
						'site_id' => 'ASC',
						'lft' => 'ASC',
						'rght' => 'ASC',
					],
					'recursive' => -1,
				]);
			} else {
				// 差分でのページを対象
				$contents = $this->CuStaticContent->find('all', [
					'conditions' => [
						'site_id' => $siteId,
					],
					'order' => [
						'site_id' => 'ASC',
						'id' => 'ASC',
					],
					'recursive' => -1,
				]);
			}

			foreach ($contents as $content) {
				if (isset($content['Content'])) {
					$content = $content['Content'];
					$status = true;
				} elseif (isset($content['CuStaticContent'])) {
					$content = $content['CuStaticContent'];
					if ($content['type'] == 'BlogPost') {
						$status = false; // データを取得後判別
					} else {
						if (empty($content['url'])) {
							$content['url'] = $this->Content->createUrl($content['content_id']);
							$this->CuStaticContent->id = $content['id'];
							$this->CuStaticContent->saveField('url', $content['url']);
						}
						$status = $this->Content->findByUrl($content['url']);
					}
				}

				$pageUrl = ltrim($content['url'], '/');
				$pagePath = str_replace('/', DS, $pageUrl);

				switch ($content['type']):
					case 'ContentFolder':
						$preifx = '_' . $siteId;
						if ($CuStaticConfig['folder' . $preifx]) {
							$url = '/' . $pageUrl;
							$path = $exportPath . $pagePath ;
							$this->makeHtml($url, $path . 'index.html', $status);
						}
						$this->setProgressBar(++$progress, $progressMax);
						break;

					case 'Page':
						$preifx = '_' . $siteId;
						if ($CuStaticConfig['page' . $preifx]) {
							$url = '/' . $pageUrl;
							$path = $exportPath . $pagePath;
							$this->makeHtml($url, $path . '.html', $status);
						}
						$this->setProgressBar(++$progress, $progressMax);
						break;

					case 'BlogContent':
						$preifx = '_' . $siteId  . '_' . $content['entity_id'];
						$blogContent = $this->BlogContent->find('first', [
							'conditions' => [
								'BlogContent.id' => $content['entity_id']
							],
							'recursive' => -1
						]);
						$listCount = $blogContent['BlogContent']['list_count'];
						$conditionAllowPublish = $this->BlogPost->getConditionAllowPublish();

						$blogPosts = $this->BlogPost->find('all', [
							'conditions' => [
								'BlogPost.blog_content_id' => $content['entity_id'],
								$conditionAllowPublish,
							],
						 ]);

						// index
						if ($CuStaticConfig['blog_index' . $preifx]) {
							$targetUrl = 'index';
							$targetPath = str_replace('/', DS, $targetUrl);
							$url = '/' . $pageUrl . $targetUrl;
							$path = $exportPath . $pagePath . $targetPath;

							$dir = new Folder($exportPath . $pagePath, 0777);
							$dir->delete();

							$this->makeHtml($url, $path . '.html', $status);

							$blogPostsCount = count($blogPosts);
							$this->makePagingHtml($blogPostsCount, $listCount, $url, $path);

							// rss対応
							$this->makeHtml($url . '.rss', $path . '.rss', $status);

						}
						$this->setProgressBar(++$progress, $progressMax);

						// category
						if ($CuStaticConfig['blog_category' . $preifx]) {
							$this->BlogCategory->reduceAssociations(['BlogCategory', 'BlogPost']);
							$this->BlogCategory->hasMany['BlogPost']['conditions'] = $conditionAllowPublish;
							$blogCategories = $this->BlogCategory->find('all', [
								'conditions' => [
									'BlogCategory.blog_content_id' => $content['entity_id'],
								],
								'recursive' => -1,
							]);
							foreach ($blogCategories as $blogCategory) {
								$targetUrl = 'archives/category/' . $blogCategory['BlogCategory']['name'];
								$targetPath = str_replace('/', DS, $targetUrl);
								$url = '/' . $pageUrl . $targetUrl;
								$path = $exportPath . $pagePath . $targetPath;
								$this->makeHtml($url, $path . '.html', $status);

								// category paging
								$blogPostsCount = count(Hash::extract($blogPosts, '{n}.BlogPost[blog_content_id=' . $content['entity_id'] . '][blog_category_id=' . $blogCategory['BlogCategory']['id'] . ']'));
								$this->makePagingHtml($blogPostsCount, $listCount, $url, $path);
							}
						}
						$this->setProgressBar(++$progress, $progressMax);

						// tags
						if ($CuStaticConfig['blog_tag' . $preifx]) {
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
									$url = '/' . $pageUrl . $targetUrl;
									$path = $exportPath . $pagePath . $targetPath;
									$this->makeHtml($url, $path . '.html', $status);

									// tags paging
									$blogPostsCount = count(Hash::extract($blogTag['BlogPost'], '{n}[blog_content_id=' . $content['entity_id'] . ']'));
									$this->makePagingHtml($blogPostsCount, $listCount, $url, $path);
								}
							}
						}
						$this->setProgressBar(++$progress, $progressMax);

						// date
						$dateFormats = [];
						if ($CuStaticConfig['blog_date_year' . $preifx]) $dateFormats[] = 'Y';
						if ($CuStaticConfig['blog_date_month' . $preifx]) $dateFormats[] = 'Y/m';
						if ($CuStaticConfig['blog_date_day' . $preifx]) $dateFormats[] = 'Y/m/d';
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
									$url = '/' . $pageUrl . $targetUrl;
									$path = $exportPath . $pagePath . $targetPath;
									$this->makeHtml($url, $path . '.html', $status);

									// date paging
									$this->makePagingHtml($blogPostsCount, $listCount, $url, $path);
								}
							}
						}
						$this->setProgressBar(++$progress, $progressMax);

						// author
						if ($CuStaticConfig['blog_author' . $preifx]) {
							$users = $this->User->find('all');
							foreach ($users as $user) {
								$targetUrl = 'archives/author/' . $user['User']['name'];
								$targetPath = str_replace('/', DS, $targetUrl);
								$url = '/' . $pageUrl . $targetUrl;
								$path = $exportPath . $pagePath . $targetPath;
								$this->makeHtml($url, $path . '.html', $status);

								// author paging
								$blogPostsCount = count(Hash::extract($blogPosts, '{n}.BlogPost[blog_content_id=' . $content['entity_id'] . '][user_id=' . $user['User']['id'] . ']'));
								$this->makePagingHtml($blogPostsCount, $listCount, $url, $path);
							}
						}
						$this->setProgressBar(++$progress, $progressMax);

						// single
						if ($options['all'] && $CuStaticConfig['blog_single' . $preifx]) {
							$blogPosts = $this->BlogPost->find('all', [
								'conditions' => [
									'BlogPost.blog_content_id' => $content['entity_id'],
									$conditionAllowPublish,
								],
							]);
							foreach ($blogPosts as $blogPost) {
								$targetUrl = 'archives/' . $blogPost['BlogPost']['no'];
								$targetPath = str_replace('/', DS, $targetUrl);
								$url = '/' . $pageUrl . $targetUrl;
								$path = $exportPath . $pagePath . $targetPath;
								$this->makeHtml($url, $path . '.html', $status);
							}
						}
						$this->setProgressBar(++$progress, $progressMax);

						break;

					case 'BlogPost':
						$preifx = '_' . $siteId  . '_' . $content['content_id'];
						if ($CuStaticConfig['blog_single'. $preifx]) {
							$blogPost = $this->BlogPost->find('first', [
								'conditions' => [
									'BlogPost.blog_content_id' => $content['content_id'],
									'BlogPost.id' => $content['entity_id'],
								],
							]);
							$status = $this->BlogPost->allowPublish($blogPost);
							$targetUrl = '';
							$targetPath = str_replace('/', DS, $targetUrl);
							$url = '/' . $pageUrl . $targetUrl;
							$path = $exportPath . $pagePath . $targetPath;
							$this->makeHtml($url, $path . '.html', $status);
						}
						break;

					default:
						break;

				endswitch;

				if (!$options['all']) {
					// CuStaticContent のデータを整理
					$publishBegin = '0000-00-00 00:00:00';
					$publishEnd = '0000-00-00 00:00:00';

					switch ($content['type']):
						case 'BlogPost':
							$data = $this->BlogPost->find('first', [
								'conditions' => [
									'blog_content_id' => $content['content_id'],
									'id' => $content['entity_id'],
								],
								'recursive' => -1,
							]);
							if (!empty($data['BlogPost']['publish_begin'])) {
								$publishBegin = date('Y-m-d H:i:s', strtotime($data['BlogPost']['publish_begin']));
							}
							if (!empty($data['BlogPost']['publish_end'])) {
								$publishEnd = date('Y-m-d H:i:s', strtotime($data['BlogPost']['publish_end']));
							}
							break;
						default:
							$data = $this->Content->find('first', [
								'conditions' => [
									'name' => $content['name'],
									'plugin' => $content['plugin'],
									'type' => $content['type'],
									'entity_id' => $content['entity_id'],
								],
								'recursive' => -1,
							]);
							if (!empty($data['Content']['publish_begin'])) {
								$publishBegin = date('Y-m-d H:i:s', strtotime($data['Content']['publish_begin']));
							}
							if (!empty($data['Content']['publish_end'])) {
								$publishEnd = date('Y-m-d H:i:s', strtotime($data['Content']['publish_end']));
							}
							break;
					endswitch;

					// 処理した時間より未来の日時で公開期間が設定されている時はデータを残す（次回差分実行で処理）
					if ($publishBegin < $now && $publishEnd < $now) {
						$deleteFlag = true;
					} elseif ($publishBegin < $now && $publishEnd == '0000-00-00 00:00:00') {
						$deleteFlag = true;
					} elseif ($publishBegin == '0000-00-00 00:00:00' && $publishEnd < $now ) {
						$deleteFlag = true;
					} else {
						$deleteFlag = false;
					}
					if ($deleteFlag) {
						$this->CuStaticContent->delete($content['id']);
					}
				}

			}

			$this->setProgressBarStatus(0);
		}

	}

	private function makePagingHtml($blogPostsCount, $listCount, $targetUrl, $targetPath) {

		if ($blogPostsCount > $listCount) {

			// 一旦フォルダを削除して再作成
			$folder = new Folder($targetPath);
			if (file_exists($targetPath)) {
				$folder->delete();
			}
			$folder->create($targetPath, 0777);

			$pageMax = ceil($blogPostsCount / $listCount);
			for ($i = 2; $i <= $pageMax; $i++) {
				$url = $targetUrl . '/page:' . $i;
				$path = $targetPath . DS . $i . '.html';
				$this->makeHtml($url, $path, true);
			}
		}

	}

	/**
	 * ファイル書き出し
	 */
	private function makeHtml($url, $path, $create)  {
		if ($create) {
			// $this->saveHtml($url, $path);

			// requestActionにてメモリ消費が多いので別プロセス化する
			$command = sprintf(Configure::read('CuStatic.command2'), 'html', $url, $path);
			$cmd = CakePlugin::path('CuStatic') . 'Shell' . DS . $command;
			exec($cmd);

		} else {
			$this->deleteHtml($url, $path);
		}
	}

	/**
	 * saveHtml
	 *
	 * @param type $url
	 * @param type $path
	 */
	private function saveHtml($url, $path) {

		$this->log('[saveHtml] url: ' . $url, LOG_CUSTATIC);
		$this->log('[saveHtml] path: ' . $path, LOG_CUSTATIC);

		App::uses('CakeObject', 'Core');
		$CakeObject = new CakeObject();
		try {
			$getData = $CakeObject->requestAction($url, ['return' => true, 'bare' => false]);

		} catch (Exception $e) {
			$this->log('[saveHtml] RequestAction error: ' . $url, LOG_CUSTATIC);
			return;
		}
		unset($CakeObject);

		// http://www.mikame.net/pr/archives/781
		//echo sprintf( '%8s %8dk : %s', ($getData) ? 'Success' : 'Failed', memory_get_usage() / 1024, $url)."\n";

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
	 * deleteHtml
	 *
	 * @param type $url
	 * @param type $path
	 */
	private function deleteHtml($url, $path) {

		// HTML削除
		$file = new File($path);
		$file->delete();

		$this->log('[deleteHtml] url: ' . $url, LOG_CUSTATIC);
		$this->log('[deleteHtml] path: ' . $path, LOG_CUSTATIC);
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

	private function setProgressBarStatus($status) {
		$config = [];
		$config['status'] = $status;
		$this->CuStaticConfig->saveKeyValue($config);
	}

	private function setProgressBar($progress, $progressMax = null) {
		$config = [];
		$config['progress'] = $progress;
		if ($progressMax != null) {
			$config['progress_max'] = $progressMax;
			$this->log('progress: ' . $progress . '/ ' . $progressMax, LOG_CUSTATIC);
		} else {
			$this->log('progress: ' . $progress, LOG_CUSTATIC);
		}
		$this->CuStaticConfig->saveKeyValue($config);
	}

}
