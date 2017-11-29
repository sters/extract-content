<?php

namespace ExtractContent;

/**
 * Class ExtractContent
 *
 * @package ExtractContent
 */
class ExtractContent
{
    /**
     * @var array
     */
    const DEFAULT_OPTIONS = [
        'threshold'          => 100,
        'min_length'         => 80,
        'decay_factor'       => 0.73,
        'continuous_factor'  => 1.62,
        'punctuation_weight' => 10,
        'punctuations'       => '/([、。，．！？]|\.[^A-Za-z0-9]|,[^0-9]|!|\?)/',
        'waste_expressions'  => '/Copyright | All Rights Reserved/i',
        'dom_separator'      => '',
        'debug'              => false,
    ];

    /**
     * @var array
     */
    const CHARREF = [
        '&nbsp;'  => ' ',
        '&lt;'    => '<',
        '&gt;'    => '>',
        '&amp;'   => '&',
        '&laquo;' => "\xc2\xab",
        '&raquo;' => "\xc2\xbb",
    ];

    /**
     * target html string
     *
     * @var string
     */
    private $html = '';

    /**
     * analyse options
     *
     * @var array
     */
    private $options = [];

    /**
     * ExtractContent constructor.
     *
     * @param string $html target html
     * @param array $options analyse options
     */
    public function __construct(string $html, array $options = [])
    {
        $this->html = $html;
        $this->options = $options + static::DEFAULT_OPTIONS;
    }

    /**
     * Update option value
     *
     * @param string $name option name
     * @param string $value option value
     */
    public function setOption(string $name, string $value)
    {
        $this->options[$name] = $value;
    }

    /**
     * @return array
     */
    public function analyse()
    {
        if ($this->isFramesetHtml() || $this->isRedirectHtml()) {
            return [
                '',
                $this->extractTitle($this->html),
            ];
        }

        $targetHtml = $this->html;

        // Title
        $title = $this->extractTitle($targetHtml);

        $targetHtml = $this->extractAdSection($targetHtml);
        $targetHtml = $this->eliminateUselessTags($targetHtml);
        $targetHtml = $this->hBlockIncludingTitle($title, $targetHtml);

        // Extract text blocks
        $factor = 1.0;
        $continuous = 1.0;
        $body = '';
        $score = 0;
        $bodyList = [];
        $list = preg_split('/<\/?(?:div|center|td)[^>]*>|<p\s*[^>]*class\s*=\s*["\']?(?:posted|plugin-\w+)[\'"]?[^>]*>/', $targetHtml);
        foreach ($list as $block) {
            if (empty($block)) {
                continue;
            }

            $block = trim($block);
            if ($this->hasOnlyTags($block)) {
                continue;
            }

            if (! empty($body) > 0) {
                $continuous /= $this->options['continuous_factor'];
            }

            // check link list
            $notLinked = $this->eliminateLink($block);
            if (strlen($notLinked) < $this->options['min_length']) {
                continue;
            }

            // calculate score
            $punctuations = preg_split($this->options['punctuations'], $notLinked);
            $c = strlen($notLinked) + count($punctuations) * $this->options['punctuation_weight'] * $factor;
            $factor *= $this->options['decay_factor'];

            $wasteBlock = preg_split($this->options['waste_expressions'], $block);
            $amazonBlock = preg_split('/amazon[a-z0-9\.\/\-\?&]+-22/i', $block);
            $notBodyRate = count($wasteBlock) + count($amazonBlock) / 2.0;

            if ($notBodyRate > 0) {
                $c *= 0.72 ** $notBodyRate;
            }

            $c1 = $c * $continuous;

            if ($this->options['debug']) {
                $notLinkedCount = count($notLinked);
                $stripTags = substr(strip_tags($block), 0, 100);
                echo "----- {$c}*{$continuous}={$c1} {$notLinkedCount} \n{$stripTags}\n";
            }

            // extract block, add score
            if ($c1 > $this->options['threshold']) {
                $body .= $block . "\n";
                $score += $c1;
                $continuous = $this->options['continuous_factor'];
            } elseif ($c > $this->options['threshold']) {
                $bodyList[] = [
                    $body,
                    $score,
                ];
                $body = $block . "\n";
                $score = $c;
                $continuous = $this->options['continuous_factor'];
            }
        }

        $bodyList[] = [
            $body,
            $score,
        ];
        $body = array_reduce($bodyList, function($a, $b) {
            if ($a[1] > $b[1]) {
                return $a;
            } else {
                return $b;
            }
        });

        return [
            strip_tags($body[0]),
            $title,
        ];
    }

    /**
     * @return bool
     */
    private function isFramesetHtml(): bool
    {
        return preg_match('/<\/frameset>/i', $this->html);
    }

    /**
     * @return bool
     */
    private function isRedirectHtml(): bool
    {
        return preg_match('/<meta\s+http-equiv\s*=\s*["\']?refresh[\'"]?[^>]*url/i', $this->html);
    }

    /**
     * @param string $html
     *
     * @return string
     */
    private function extractTitle(string $html): string
    {
        $result = '';

        if (preg_match('/<title[^>]*>\s*(.*?)\s*<\/title\s*>/i', $html, $matches)) {
            $result = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES);
        }

        return $result;
    }

    /**
     * @param string $html
     *
     * @return string
     */
    private function extractAdSection(string $html): string
    {
        $html = preg_replace('/<!--\s*google_ad_section_start\(weight=ignore\)\s*-->.*?<!--\s*google_ad_section_end.*?-->/s', '', $html);
        if (preg_match('/<!--\s*google_ad_section_start[^>]*-->/', $html)) {
            preg_match_all('/<!--\s*google_ad_section_start[^>]*-->(.*?)<!--\s*google_ad_section_end.*?-->/s', $html, $matches);
            $html = implode("\n", $matches[1]);
        }

        return $html;
    }

    /**
     * @param string $title
     * @param string $html
     *
     * @return string
     */
    private function hBlockIncludingTitle(string $title, string $html): string
    {
        return preg_replace_callback('/(<h\d\s*>\s*(.*?)\s*<\/h\d\s*>)/i', function ($match) use ($title) {
            if (strlen($match[2]) >= 3 && strpos($title, $match[2]) !== false) {
                return '<div>' . $match[2] . '</div>';
            }

            return $match[1];
        }, $html);
    }

    /**
     * @param string $html
     *
     * @return bool
     */
    private function hasOnlyTags(string $html): bool
    {
        $html = preg_replace('/<[^>]*>/is', '', $html);
        $html = str_replace('&nbsp;', '', $html);
        $html = trim($html);

        return strlen($html) === 0;
    }

    /**
     * @param string $html
     *
     * @return string
     */
    private function eliminateUselessTags(string $html): string
    {
        // eliminate useless symbols
        $html = preg_replace('/[\342\200\230-\342\200\235]|[\342\206\220-\342\206\223]|[\342\226\240-\342\226\275]|[\342\227\206-\342\227\257]|\342\230\205|\342\230\206/', '', $html);

        // eliminate useless html tags
        $html = preg_replace('/<(script|style|select|noscript)[^>]*>.*?<\/\1\s*>/is', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $html = preg_replace('/<![A-Za-z].*?>/', '', $html);
        $html = preg_replace('/<div\s[^>]*class\s*=\s*[\'"]?alpslab-slide["\']?[^>]*>.*?<\/div\s*>/s', '', $html);
        $html = preg_replace('/<div\s[^>]*(id|class)\s*=\s*[\'"]?\S*more\S*["\']?[^>]*>/i', '', $html);

        return $html;
    }

    /**
     * @param string $html
     *
     * @return string
     */
    private function eliminateLink(string $html): string
    {
        $count = 0;
        $notLinked = preg_replace_callback('/<a\s[^>]*>.*?<\/a\s*>/is', function ($matched) use (&$count) {
            $count++;

            return '';
        }, $html);
        $notLinked = preg_replace('/<form\s[^>]*>.*?<\/form\s *>/ims', '', $notLinked);
        $notLinked = strip_tags($notLinked);

        if (strlen($notLinked) < 20 * $count || $this->isLinkList($html)) {
            return '';
        }

        return $notLinked;
    }

    /**
     * @param string $html
     *
     * @return bool
     */
    private function isLinkList(string $html): bool
    {
        if (preg_match('/<(?:ul|dl|ol)(.+?)<\/(?:ul|dl|ol)>/is', $html, $matched)) {
            $listPart = $matched[1];
            $outside = preg_replace('/<(?:ul|dl)(.+?)<\/(?:ul|dl)>/is', '', $html);
            $outside = preg_replace('/<.+?>/s', '', $outside);
            $outside = preg_replace('/\s+/s', ' ', $outside);
            $list = preg_split('/<li[^>]*>/', $listPart);
            array_shift($list);

            $rate = $this->evaluateList($list);
            if ($rate == 1) {
                return false;
            }

            return strlen($outside) <= (strlen($html) / (45 / $rate));
        }

        return false;
    }

    /**
     * @param array $list
     *
     * @return float
     */
    private function evaluateList(array $list): float
    {
        if (empty($list)) {
            return 1;
        }

        $hit = 0;
        foreach ($list as $line) {
            if (preg_match('/<a\s+href=([\'"]?)([^"\'\s]+)\1/is', $line)) {
                $hit++;
            }
        }

        return 9 * (1.0 * $hit / count($list)) ** 2 + 1;
    }
}
