
# ExtractContent

[![CircleCI](https://circleci.com/gh/sters/extract-content/tree/master.svg?style=svg)](https://circleci.com/gh/sters/extract-content/tree/master)

ExtractContent for PHP7.

## Installation

Install Plugin using composer.

```
composer require "sters/extract-content:dev-master"
```

## Usage

```
use ExtractContent\ExtractContent;

$content = file_get_contents('https://en.wikipedia.org/wiki/PHPUnit');
$extractor = new ExtractContent($content);
$result = $extractor->analyse();
```

Output example.
```
PHPUnit is based on the idea that developers should be able to find mistakes in their newly committed code quickly and assert...
```

## Original versions

[http://labs.cybozu.co.jp/blog/nakatani/2007/09/web_1.html](http://labs.cybozu.co.jp/blog/nakatani/2007/09/web_1.html)

