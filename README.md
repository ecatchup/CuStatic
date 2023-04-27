CuStatic（静的HTML出力）プラグイン
==========

CuStatic（静的HTML出力）プラグインは、管理画面で作成したWebサイト内のページを静的なHTMLファイルとして出力することができるプラグインです。

HTMLの出力対象は、固定ページ、ブログ（記事一覧、記事詳細、各種一覧）、フォルダになります。
また、HTML内で利用される画像やCSS，JSなどのファイルもあわせて出力します。

## Installation

1. 圧縮ファイルを解凍後、BASERCMS/app/Plugin/CuStatic に配置します。
2. 管理システムのプラグイン管理にアクセスし、表示されている CuStatic プラグイン をインストール（有効化）して下さい。
3. オプション設定画面にアクセスし、出力先のフォルダ、及び、利用する出力対象をサイト、コンテンツ毎に有無を設定し保存します。
4. 静的HTML出力画面より静的HTML出力ボタンを押すと、HTMLが生成されます。
5. シェルプログラムにて書出を行うためシェルプログラムが動作する環境準備および  Shell/exec.sh の実行権限を付与してください。

### TODO
CuStaticの機能拡張として

* 時限公開
* 時限非公開
* 下書き時限公開
* 複数サーバー同期

などなど、別途カスタマイズにて対応可能です。

## 別サーバへ配信する方法
1. 管理画面の静的書き出しプラグインの設定にて一時書き出し先を指定します
1. Console/Command/CuStticShell.php execOptionsProcess() にて完了後生成ファイルパスを指定します
1. Shell/sync.sh にて完了後生成ファイルパスと一時書き出し先と転送先を指定します
1. cronなどの定期実行でShell/sync.shを定期実行させます

以上で書き出されたファイルが定期的に別サーバへrsyncにて配信されます。


## Thanks

- [http://basercms.net](http://basercms.net/)
- [http://wiki.basercms.net/](http://wiki.basercms.net/)
- [http://cakephp.jp](http://cakephp.jp)

License
-------
Lincensed under the MIT lincense since version 2.0
