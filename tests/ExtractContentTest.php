<?php

namespace ExtractContent\Test;

use PHPUnit\Framework\TestCase;
use ExtractContent\ExtractContent;

/**
 * ExtractContent Testcase
 */
class ExtractContentTest extends TestCase
{
    private function invokePrivateMethod($instance, string $methodName, ...$args)
    {
        $reflection = new \ReflectionClass($instance);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($instance, ...$args);
    }

    public function testIsFramesetHtml()
    {
        $extractor = new ExtractContent('</frameset>');
        $result = $this->invokePrivateMethod($extractor, 'isFramesetHtml');
        $this->assertTrue($result);

        $extractor = new ExtractContent('<p>its paragraph</p>');
        $result = $this->invokePrivateMethod($extractor, 'isFramesetHtml');
        $this->assertFalse($result);
    }

    public function testIsRedirectHtml()
    {
        $extractor = new ExtractContent('<meta http-equiv="refresh" content="5;URL=http://example.com">');
        $result = $this->invokePrivateMethod($extractor, 'isRedirectHtml');
        $this->assertTrue($result);

        $extractor = new ExtractContent('<p>its paragraph</p>');
        $result = $this->invokePrivateMethod($extractor, 'isRedirectHtml');
        $this->assertFalse($result);
    }

    public function testExtractTitle()
    {
        $content = [];
        $content[] = '<head><title>It&#039;s extract &lt;&quot;&amp;&quot;&gt;</title></head>';
        $content = implode("\n", $content);

        $extractor = new ExtractContent($content);
        $result = $this->invokePrivateMethod($extractor, 'extractTitle', $content);

        $this->assertEquals('It\'s extract <"&">', $result);
    }

    public function testExtractAdSection()
    {
        $content = [];
        $content[] = '<div>';
        $content[] = '<!-- google_ad_section_start(weight=ignore) -->';
        $content[] = '<p>dammy dammy</p>';
        $content[] = '<!-- google_ad_section_end -->';
        $content[] = '</div>';
        $content[] = '<div>';
        $content[] = '<!-- google_ad_section_start -->';
        $content[] = '<p>test1</p>';
        $content[] = '<!-- google_ad_section_end -->';
        $content[] = '</div>';
        $content[] = '<div>';
        $content[] = '<!-- google_ad_section_start -->';
        $content[] = '<p>test2</p>';
        $content[] = '<!-- google_ad_section_end -->';
        $content[] = '</div>';
        $content = implode("\n", $content);

        $extractor = new ExtractContent($content);
        $result = $this->invokePrivateMethod($extractor, 'extractAdSection', $content);

        $this->assertNotContains('<p>dammy dammy</p>', $result);
        $this->assertContains('<p>test1</p>', $result);
        $this->assertContains('<p>test2</p>', $result);
    }

    public function testEliminateUselessTags()
    {
        $content = [];
        $content[] = '<script>console.log("not contains");</script>';
        $content[] = '<p>do not delete</p>';
        $content[] = '<noscript>not contains</noscript>';
        $content[] = '<style>.not contains</style>';
        $content[] = '<select><option>not contains</option></select>';
        $content[] = '<!-- not contains -->';
        $content = implode("\n", $content);

        $extractor = new ExtractContent($content);
        $result = $this->invokePrivateMethod($extractor, 'eliminateUselessTags', $content);

        $this->assertNotContains('not contains', $result);
        $this->assertContains('do not delete', $result);
    }

    public function testHBlockIncludingTitle()
    {
        $title = 'heading is including';

        $content = [];
        $content[] = '<head><title>' . $title . '</title></head>';
        $content[] = '<h1>heading</h1>';
        $content[] = '<p>not modified</p>';
        $content = implode("\n", $content);

        $extractor = new ExtractContent($content);
        $result = $this->invokePrivateMethod($extractor, 'hBlockIncludingTitle', $title, $content);

        $this->assertNotContains('<h1>heading</h1>', $result);
        $this->assertContains('<div>heading</div>', $result);
        $this->assertContains('<p>not modified</p>', $result);
    }

    public function testHasOnlyTags()
    {
        $extractor = new ExtractContent('dammy');

        $result = $this->invokePrivateMethod($extractor, 'hasOnlyTags', '<i data-text="Im only tag item">');
        $this->assertTrue($result);

        $result = $this->invokePrivateMethod($extractor, 'hasOnlyTags', '<p>Its paragraph.</p>');
        $this->assertFalse($result);
    }

    public function testEliminateLink()
    {
        $extractor = new ExtractContent('dammy');

        $content = [];
        $content[] = '<p>not modified</p>';
        $content[] = '<ul>';
        $content[] = '<li><a href="#">link list item</a></li>';
        $content[] = '</ul>';
        $content = implode("\n", $content);
        $result = $this->invokePrivateMethod($extractor, 'eliminateLink', $content);
        $this->assertEmpty($result);

        $content = [];
        $content[] = '<p>not modified</p>';
        $content = implode("\n", $content);
        $result = $this->invokePrivateMethod($extractor, 'eliminateLink', $content);
        $this->assertNotEmpty($result);
    }

    public function testIsLinkList()
    {
        $extractor = new ExtractContent('dammy');

        $result = $this->invokePrivateMethod($extractor, 'isLinkList', '<ul><li>list item</li></ul>');
        $this->assertFalse($result);
        $result = $this->invokePrivateMethod($extractor, 'isLinkList', '<div>no list here. <ul><li>list item</li></ul></div>');
        $this->assertFalse($result);
        $result = $this->invokePrivateMethod($extractor, 'isLinkList', '<p>Its paragraph.</p>');
        $this->assertFalse($result);

        $result = $this->invokePrivateMethod($extractor, 'isLinkList', '<div>no list here. <ul><li><a href="#">link list item</a></li></ul></div>');
        $this->assertTrue($result);
    }

    public function testAnalyse()
    {
        $this->markTestSkipped('The test using Online HTML content.');

        $extractor = new ExtractContent(file_get_contents('https://en.wikipedia.org/wiki/PHPUnit'));

        $result = $extractor->analyse();
        $this->assertContains('PHPUnit is based on the idea that developers should be able to find mistakes in their newly committed code quickly and assert', $result[0]);
        $this->assertEquals('PHPUnit - Wikipedia', $result[1]);
    }
}
