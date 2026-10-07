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
	<?php
	// 実行ボタン押下直後のリダイレクトかどうか（?exec=1）。
	// バックグラウンドの CLI が status=1 を立てるまで数秒かかるため、
	// その間も「起動中…」を即時表示して無反応に見えないようにする。
	$execPending = (bool)$this->getRequest()->getQuery('exec');
	?>
	<?= $this->BcAdminForm->create(null, ['type' => 'post', 'url' => ['action' => 'index']]) ?>

	<div id="cu-static-status" style="<?= ($config->status || $execPending) ? '' : 'display:none' ?>">
		<?php // value 属性なしの progress は不確定（インジケータ流れ）表示になる。起動待ちの間はこれを使う ?>
		<progress id="cu-static-progress" max="<?= h($config->progress_max ?: 1) ?>"<?= ($config->status && (int)$config->progress_max > 0) ? ' value="' . h($config->progress) . '"' : '' ?>></progress>
		<div id="cu-static-status-message"><?= ($execPending && !$config->status) ? '起動中…' : '' ?></div>
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

	<?php
	// 書き出し後コマンド（setting_customize.php の CuStatic.afterExportCommands）。
	// 管理画面からは登録・変更できないため、登録内容を読み取り専用で表示する。
	$afterExportCommands = \CuStatic\Service\CuStaticCommandRunner::getCommands();
	?>
	<?php if ($afterExportCommands): ?>
	<div class="section" id="cu-static-after-export-commands">
		<h2 class="bca-main__heading" data-bca-heading-size="lg">書き出し後コマンド</h2>
		<table class="list-table bca-table-listup">
			<thead class="bca-table-listup__thead">
				<tr>
					<th class="bca-table-listup__thead-th">コマンド</th>
					<th class="bca-table-listup__thead-th">実行モード</th>
					<th class="bca-table-listup__thead-th">変更なしの差分</th>
				</tr>
			</thead>
			<tbody class="bca-table-listup__tbody">
				<?php foreach ($afterExportCommands as $command): ?>
				<tr>
					<td class="bca-table-listup__tbody-td"><code><?= h($command['label']) ?></code></td>
					<td class="bca-table-listup__tbody-td"><?= h(implode(' / ', array_map(fn($m) => $m === 'diff' ? '差分' : '全件', $command['modes']))) ?></td>
					<td class="bca-table-listup__tbody-td"><?= $command['skipIfNoChange'] ? 'スキップ' : '実行' ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="bca-main__text">書き出し完了後に上から順に実行します。実行結果は最新ログで確認できます。登録・変更は <code>config/setting_customize.php</code> の <code>CuStatic.afterExportCommands</code> で行います。</p>
	</div>
	<?php endif; ?>

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
			var IDLE_INTERVAL = 5000;
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

			// 起動待ち状態（実行ボタン押下直後）。CLI が status=1 を立てるまでの間、
			// 「起動中…」＋不確定プログレスバーを表示し、ポーリングも実行中間隔で回す。
			// 起動失敗時に永久に「起動中…」とならないよう 60 秒でタイムアウトする。
			var pending = <?= $execPending ? 'true' : 'false' ?>;
			var pendingUntilMs = Date.now() + 60000;
			var initialFinished = null; // 初回応答の終了時刻。変化したら「起動→即完了」とみなす
			if (pending && window.history && history.replaceState) {
				// リロード時に再び「起動中…」と誤表示しないよう ?exec=1 を URL から除去する
				history.replaceState(null, '', window.location.pathname);
			}

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

				// 起動待ちの解除判定：実行開始を検知したら解除。
				// 初回応答より終了時刻が変化した場合は「起動→ポーリング間隔内に完了」なので同じく解除。
				// 起動失敗などで一向に始まらない場合は 60 秒で諦める。
				if (initialFinished === null) initialFinished = data.finished || '';
				if (status || (data.finished && data.finished !== initialFinished) || Date.now() > pendingUntilMs) {
					pending = false;
				}

				// 実行中・起動待ち、または過去実行の記録（開始時刻あり）がある場合に表示する
				if (statusEl) statusEl.style.display = (status || pending || data.started) ? '' : 'none';
				// プログレスバーは実行中と起動待ちのみ表示。
				// 分母が未確定（起動待ち・初期化中）の間は value を外して不確定表示にする。
				if (progressEl) {
					progressEl.style.display = (status || pending) ? '' : 'none';
					if (status && max > 0) {
						progressEl.max = max;
						progressEl.value = progress;
					} else {
						progressEl.removeAttribute('value');
						progressEl.max = 1;
					}
				}
				if (msgEl) {
					if (status) {
						msgEl.textContent = max > 0
							? '処理中 (' + Math.round(progress / max * 100) + ' %)'
							: '処理を開始しています…';
					} else if (pending) {
						msgEl.textContent = '起動中…';
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

				// 起動待ち中も実行中と同じ短い間隔でポーリングする
				return status || pending;
			}

			// ポーリングは停止しない。実行ボタン押下後のリダイレクト直後は、バックグラウンドの
			// コマンドがまだ起動中（status=0）のことがあり、status=1 を条件に停止すると
			// 実行開始を取り逃がして進捗が一切表示されなくなるため。
			// 実行中は POLL_INTERVAL、アイドル時は IDLE_INTERVAL に落として負荷を抑える。
			function poll() {
				fetch(statusUrl + '?offset=' + offset)
					.then(function(r) { return r.json(); })
					.then(function(data) {
						var isRunning = render(data);
						setTimeout(poll, isRunning ? POLL_INTERVAL : IDLE_INTERVAL);
					})
					.catch(function() {
						setTimeout(poll, IDLE_INTERVAL * 2); // エラー時はバックオフして再試行
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

		/*
		 * 不確定状態（value 属性なし＝起動中・初期化中）の表示。
		 * appearance: none のため Chrome では value 部分が全幅（100%の緑）で
		 * 描画されてしまうので、緑の塗りを消して全体をグレー（0%相当の見た目）にする。
		 * Firefox は不確定時に ::-moz-progress-bar が全幅になるため同様にグレーへ。
		 */
		#cu-static-status progress:indeterminate::-webkit-progress-value {
			background: #eee;
		}

		#cu-static-status progress:indeterminate::-moz-progress-bar {
			background: #eee;
		}
	</style>

<?php endif; ?>
