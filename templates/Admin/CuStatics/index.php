<?php

/**
 * CuStatic Plugin - 静的HTML出力画面
 *
 * @var \BaserCore\View\BcAdminAppView $this
 * @var \CuStatic\Model\Entity\CuStaticConfig|null $config
 */
$this->BcAdmin->setTitle('静的HTML出力');
?>
<?php if (!$config || empty($config->export_path)): ?>
	<div class="section">
		<p>利用する前に <?= $this->BcHtml->link('オプション設定', ['action' => 'config'], ['class' => 'bca-btn', 'data-bca-btn-type' => 'settings']) ?> を行ってください。</p>
	</div>
<?php else: ?>

	<div class="section">
		<p class="bca-main__text">
			CuStatic プラグインは、指定したフォルダにHTMLを出力することができます。<br>
			出力先のフォルダや、出力対象については <?= $this->BcHtml->link('オプション設定', ['action' => 'config']) ?> にて事前に設定を行ってください。
		</p>
	</div>

	<!-- form -->
	<?= $this->BcAdminForm->create(null, ['type' => 'post', 'url' => ['action' => 'index']]) ?>

	<div id="cu-static-status" style="<?= $config->status ? '' : 'display:none' ?>">
		<progress id="cu-static-progress" max="<?= h($config->progress_max) ?>" value="<?= h($config->progress) ?>"></progress>
		<div id="cu-static-status-message"></div>
		<dl id="cu-static-times" class="cu-static-times">
			<div><dt>開始時刻</dt><dd id="cu-static-started">-</dd></div>
			<div><dt>終了時刻</dt><dd id="cu-static-finished">-</dd></div>
			<div><dt>経過時間</dt><dd id="cu-static-elapsed">-</dd></div>
		</dl>
	</div>

	<!-- button -->
	<div class="submit bca-actions">
		<div class="bca-actions__main">
			<?php
				if (Cake\Core\Configure::read('CuStatic.cronEnabled')):
					$btnExportTitle = '静的HTML出力（全件）';
				else:
					$btnExportTitle = '静的HTML出力';
				endif;
			?>
			<?= $this->BcAdminForm->button($btnExportTitle, [
				'id' => 'BtnExport',
				'name' => 'mode',
				'value' => 'main',
				'div' => false,
				'class' => 'button bca-btn bca-actions__item bca-loading',
				'data-bca-btn-type' => 'save',
				'data-bca-btn-size' => 'lg',
				'data-bca-btn-width' => 'lg',
			]) ?>
			<?php if (Cake\Core\Configure::read('CuStatic.cronEnabled')): ?>
				<?= $this->BcAdminForm->button('差分出力', [
					'id' => 'BtnExportDiff',
					'name' => 'mode',
					'value' => 'diff',
					'div' => false,
					'class' => 'button bca-btn bca-actions__item bca-loading',
					'data-bca-btn-type' => 'update',
					'data-bca-btn-size' => 'lg',
					'data-bca-btn-width' => 'lg',
				]) ?>
			<?php endif; ?>
		</div>
	</div>

	<div class="section" id="cu-static-log">
		<div class="bca-collapse__action">
			<button type="button" class="bca-collapse__btn" data-bca-collapse="collapse" data-bca-target="#cu-static-log-body" aria-expanded="false" aria-controls="cu-static-log-body">
				最新ログ表示&nbsp;&nbsp;<i class="bca-icon--chevron-down bca-collapse__btn-icon"></i>
			</button>
		</div>
		<div class="bca-collapse" id="cu-static-log-body" data-bca-state="">
			<div id="cu-static-console-wrapper">
				<pre id="cu-static-console"></pre>
				<?= $this->BcHtml->link('ログファイルをダウンロード', ['action' => 'log_download'], ['class' => 'bca-btn']) ?>
			</div>
		</div>
	</div>

	<?= $this->BcAdminForm->end() ?>

	<?php
	// アドオン差し込みスロット（Helper.BcFormTable.after）。
	// アドオンのリスナーがボタン・結果表示等を追加できる。リスナー不在時は空出力。
	// メインフォームの外に置くこと（アドオンが自前の form を描画するため）。
	?>
	<?= $this->BcFormTable->dispatchAfter() ?>

	<script>
		(function() {
			var POLL_INTERVAL = 2000;
			var statusUrl = '<?= $this->Url->build(['action' => 'get_status']) ?>';
			var offset = 0;
			var consoleEl = document.getElementById('cu-static-console');
			var statusEl = document.getElementById('cu-static-status');
			var progressEl = document.getElementById('cu-static-progress');
			var msgEl = document.getElementById('cu-static-status-message');
			var startedEl = document.getElementById('cu-static-started');
			var finishedEl = document.getElementById('cu-static-finished');
			var elapsedEl = document.getElementById('cu-static-elapsed');

			// 経過時間のライブ表示用。サーバ集計の経過秒を基準（baseElapsed）とし、
			// 受信後の経過（クライアント時計）を加算することで、ポーリング間隔より滑らかに更新する。
			var baseElapsed = null;
			var baseAtMs = 0;
			var running = false;

			// 秒数を「H時間M分S秒」形式へ整形（0の位は省略。0秒台は「0秒」）
			function formatElapsed(sec) {
				sec = Math.max(0, Math.floor(sec));
				var h = Math.floor(sec / 3600);
				var m = Math.floor((sec % 3600) / 60);
				var s = sec % 60;
				var out = '';
				if (h > 0) out += h + '時間';
				if (h > 0 || m > 0) out += m + '分';
				out += s + '秒';
				return out;
			}

			// 1秒ごとに経過時間を再描画（実行中のみ加算、完了後は固定値のまま）
			function tickElapsed() {
				if (baseElapsed === null || !elapsedEl) return;
				var sec = baseElapsed;
				if (running) sec += (Date.now() - baseAtMs) / 1000;
				elapsedEl.textContent = formatElapsed(sec);
			}
			setInterval(tickElapsed, 1000);

			// 状態＋差分ログを1エンドポイントから取得し、ログは追記（textContent で自動エスケープ）
			function render(data) {
				if (data.log) {
					consoleEl.textContent += data.log;
					consoleEl.scrollTop = consoleEl.scrollHeight;
				}
				if (typeof data.offset === 'number') offset = data.offset;

				var status = Number(data.status);
				var progress = Number(data.progress);
				var max = Number(data.progress_max);
				running = !!status;

				// 実行中、または過去実行の記録（開始時刻あり）がある場合に表示する
				if (statusEl) statusEl.style.display = (status || data.started) ? '' : 'none';
				// プログレスバーは実行中のみ表示
				if (progressEl) {
					progressEl.style.display = status ? '' : 'none';
					progressEl.value = progress;
					progressEl.max = max || 1;
				}
				if (msgEl) {
					if (status) {
						msgEl.textContent = '処理中 (' + (max > 0 ? Math.round(progress / max * 100) : 0) + ' %)';
					} else if (max > 0 && progress >= max) {
						msgEl.textContent = '完了';
					} else {
						msgEl.textContent = '';
					}
				}

				if (startedEl) startedEl.textContent = data.started || '-';
				if (finishedEl) finishedEl.textContent = data.finished || '-';
				// 経過時間の基準を更新（サーバ集計値＋受信時刻）
				if (typeof data.elapsed === 'number') {
					baseElapsed = data.elapsed;
					baseAtMs = Date.now();
					tickElapsed();
				} else {
					baseElapsed = null;
					if (elapsedEl) elapsedEl.textContent = '-';
				}

				return status;
			}

			// 実行中(status=1)のみポーリングを継続。完了(status=0)で停止（差分は当該回で取得済み）。
			function poll() {
				fetch(statusUrl + '?offset=' + offset)
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (render(data)) {
							setTimeout(poll, POLL_INTERVAL);
						}
					})
					.catch(function() {
						setTimeout(poll, POLL_INTERVAL * 2); // エラー時はバックオフして再試行
					});
			}

			poll();
		})();
	</script>

	<style>
		#cu-static-console {
			width: 100%;
			height: 450px;
			overflow-y: auto;
			border: 1px solid #999;
			font-size: 12px;
			font-family: consolas, monospace;
			color: #fff;
			background: #000;
			padding: 8px;
			box-sizing: border-box;
		}

		#cu-static-status {
			margin: 12px 0;
		}

		.cu-static-times {
			display: flex;
			flex-wrap: wrap;
			gap: 8px 24px;
			margin: 8px 0 0;
		}

		.cu-static-times > div {
			display: flex;
			align-items: baseline;
			gap: 6px;
		}

		.cu-static-times dt {
			font-weight: bold;
			color: #555;
		}

		.cu-static-times dd {
			margin: 0;
			font-variant-numeric: tabular-nums;
		}

		#cu-static-status progress {
			appearance: none;
			border: none;
			width: 100%;
			height: 16px;
			background: #eee;
		}

		#cu-static-status progress::-webkit-progress-value {
			background: #6fa83d;
		}

		#cu-static-status progress::-webkit-progress-bar {
			background: #eee;
		}

		#cu-static-status progress::-moz-progress-bar {
			background: #6fa83d;
		}
	</style>

<?php endif; ?>
