<?php
declare(strict_types=1);
/**
 * CuStatic Plugin
 *
 * @copyright   Copyright (c) catchup (https://catchup.co.jp/)
 * @license     MIT License
 */

namespace CuStatic\Test\TestCase\Service;

use BaserCore\TestSuite\BcTestCase;
use Cake\Core\Configure;
use CuStatic\Service\CuStaticService;

/**
 * CuStaticServiceNormalizeHtmlTest
 *
 * 静的出力向けHTML正規化（揮発値の除去）を検証する。
 */
class CuStaticServiceNormalizeHtmlTest extends BcTestCase
{

    /**
     * @var CuStaticService
     */
    protected $CuStaticService;

    public function setUp(): void
    {
        parent::setUp();
        $this->CuStaticService = new CuStaticService();
    }

    public function tearDown(): void
    {
        Configure::delete('CuStatic.normalizeUploadQuery');
        Configure::delete('CuStatic.removeBlogCommentForm');
        unset($this->CuStaticService);
        parent::tearDown();
    }

    /**
     * アップロードファイルURLの乱数クエリが除去される
     */
    public function testNormalizeUploadQuery(): void
    {
        $html = '<a href="/files/blog/1/blog_posts/2023/02/eye_catch.jpg?1234098381" rel="colorbox">'
            . '<img src="/bc_front/files/blog/1/blog_posts/2023/02/eye_catch.jpg?1905545324" alt=""></a>';

        $result = $this->CuStaticService->normalizeHtml($html);

        $this->assertStringContainsString('href="/files/blog/1/blog_posts/2023/02/eye_catch.jpg"', $result);
        $this->assertStringContainsString('src="/bc_front/files/blog/1/blog_posts/2023/02/eye_catch.jpg"', $result);
        $this->assertStringNotContainsString('?1234098381', $result);
        $this->assertStringNotContainsString('?1905545324', $result);
    }

    /**
     * 数字のみのクエリだけが対象（意味のあるクエリ・/files/ 以外は残る）
     */
    public function testNormalizeUploadQueryKeepsMeaningfulQueries(): void
    {
        $html = '<img src="/files/uploads/photo.jpg?width=100">'
            . '<script src="/js/app.js?123456"></script>'
            . '<a href="/news/archives/1?page=2">next</a>';

        $result = $this->CuStaticService->normalizeHtml($html);

        $this->assertSame($html, $result, '/files/ 以外・数字以外のクエリは変更しない');
    }

    /**
     * ブログコメントフォームとコメント用スクリプトが除去される
     */
    public function testRemoveBlogCommentForm(): void
    {
        $html = '<div class="comments"><h3>コメント一覧</h3><p>既存コメント</p></div>'
            . '<script src="/bc_front/bc_blog/js/blog_comment.bundle.js" defer="defer" id="BlogCommentsScripts" data-captchaId="35747622"></script>'
            . "<form method=\"post\" id=\"BlogCommentAddForm\" action=\"/news/archives/1\">\n"
            . '<input type="hidden" name="_csrfToken" value="abc"/><input type="hidden" name="captcha_id" value="123"/>'
            . "<table class=\"bs-blog-comment__form\"><tr><td>名前</td></tr></table>\n</form>"
            . '<footer>after</footer>';

        $result = $this->CuStaticService->normalizeHtml($html);

        $this->assertStringNotContainsString('BlogCommentAddForm', $result);
        $this->assertStringNotContainsString('BlogCommentsScripts', $result);
        $this->assertStringNotContainsString('_csrfToken', $result);
        $this->assertStringContainsString('コメント一覧', $result, 'コメント一覧の表示は残る');
        $this->assertStringContainsString('<footer>after</footer>', $result, 'フォーム後のHTMLは残る');
    }

    /**
     * 設定でOFFにすると何も変更しない
     */
    public function testNormalizeDisabled(): void
    {
        Configure::write('CuStatic.normalizeUploadQuery', false);
        Configure::write('CuStatic.removeBlogCommentForm', false);

        $html = '<img src="/files/a.jpg?123"><form id="BlogCommentAddForm"></form>';

        $this->assertSame($html, $this->CuStaticService->normalizeHtml($html));
    }

    /**
     * 同一内容のHTMLは正規化後に同一バイト列になる（決定論性）
     */
    public function testDeterministicOutput(): void
    {
        $render1 = '<img src="/files/a.jpg?' . rand() . '"><form id="BlogCommentAddForm">'
            . '<input name="_csrfToken" value="' . md5((string)rand()) . '"/></form>';
        $render2 = '<img src="/files/a.jpg?' . rand() . '"><form id="BlogCommentAddForm">'
            . '<input name="_csrfToken" value="' . md5((string)rand()) . '"/></form>';

        $this->assertSame(
            $this->CuStaticService->normalizeHtml($render1),
            $this->CuStaticService->normalizeHtml($render2)
        );
    }

}
