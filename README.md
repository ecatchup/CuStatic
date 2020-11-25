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

### TODO
CuStaticの機能拡張として

* 時限公開
* 時限非公開
* 下書き時限公開
* 複数サーバー同期

などなど、別途カスタマイズにて対応可能です。


## Thanks

- [http://basercms.net](http://basercms.net/)
- [http://wiki.basercms.net/](http://wiki.basercms.net/)
- [http://cakephp.jp](http://cakephp.jp)

License
-------
Lincensed under the MIT lincense since version 2.0
