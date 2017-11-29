<?php
namespace ExtractContent\Test;

use PHPUnit\Framework\TestCase;
use ExtractContent\ExtractContent;

/**
 * ExtractContent Testcase
 */
class ExtractContentTest extends TestCase
{
    public function testExtractTitle()
    {
        $extractor = new ExtractContent('<head><title>It&#039;s extract &lt;&quot;&amp;&quot;&gt;</title></head>');

        $this->assertEquals('It\'s extract <"&">', $extractor->extractTitle());
    }
}
