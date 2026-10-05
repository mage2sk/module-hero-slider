<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Model;

use Panth\HeroSlider\Model\LinkUrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LinkUrlValidatorTest extends TestCase
{
    public static function allowedUrls(): array
    {
        return [
            'relative path'      => ['/women/tops.html'],
            'relative no slash'  => ['sale.html'],
            'query only'         => ['?page=2'],
            'fragment'           => ['#section'],
            'http'               => ['http://example.com/'],
            'https upper scheme' => ['HTTPS://example.com/a'],
            'mailto'             => ['mailto:shop@example.com'],
            'tel'                => ['tel:+441234567'],
            'protocol relative'  => ['//cdn.example.com/x'],
        ];
    }

    #[DataProvider('allowedUrls')]
    public function testAllowedUrls(string $url): void
    {
        $this->assertTrue(LinkUrlValidator::isAllowed($url));
    }

    public static function rejectedUrls(): array
    {
        return [
            'empty'                    => [''],
            'whitespace only'          => ["  \t\n"],
            'javascript'               => ['javascript:alert(1)'],
            'javascript mixed case'    => ['JaVaScRiPt:alert(1)'],
            'javascript with tab'      => ["java\tscript:alert(1)"],
            'javascript leading space' => ['  javascript:alert(1)'],
            'entity encoded colon'     => ['javascript&colon;alert(1)'],
            'numeric entity'           => ['javascript&#58;alert(1)'],
            'percent encoded'          => ['javascript%3Aalert(1)'],
            'percent encoded newline'  => ['java%0Ascript:alert(1)'],
            'data uri'                 => ['data:text/html;base64,PHNjcmlwdD4='],
            'vbscript'                 => ['vbscript:msgbox(1)'],
            'ftp not allowlisted'      => ['ftp://example.com/file'],
            'backslash'                => ['\\\\evil.example.com'],
            'encoded backslash'        => ['/%5C%5Cevil.example.com'],
        ];
    }

    #[DataProvider('rejectedUrls')]
    public function testRejectedUrls(string $url): void
    {
        $this->assertFalse(LinkUrlValidator::isAllowed($url));
    }

    public static function allowedColors(): array
    {
        return [
            ['#fff'], ['#09090C'], ['#09090Caa'], ['red'], ['RebeccaPurple'],
            ['rgb(0, 0, 0)'], ['rgba(10,20,30,0.5)'], ['hsl(120, 50%, 50%)'], ['hsla(120 50% 50% / 0.3)'],
        ];
    }

    #[DataProvider('allowedColors')]
    public function testAllowedColors(string $color): void
    {
        $this->assertTrue(LinkUrlValidator::isAllowedColor($color));
    }

    public static function rejectedColors(): array
    {
        return [
            'empty'            => [''],
            'too short hex'    => ['#ff'],
            'too long hex'     => ['#123456789'],
            'non hex'          => ['#ggg'],
            'css injection'    => ['red;background:url(x)'],
            'expression'       => ['expression(alert(1))'],
            'url function'     => ['url(javascript:alert(1))'],
            'quote breakout'   => ['red" onmouseover="x'],
            'two letters'      => ['re'],
            'rgb with letters' => ['rgb(a,b,c)'],
        ];
    }

    #[DataProvider('rejectedColors')]
    public function testRejectedColors(string $color): void
    {
        $this->assertFalse(LinkUrlValidator::isAllowedColor($color));
    }
}
