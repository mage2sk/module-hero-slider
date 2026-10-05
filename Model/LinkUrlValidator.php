<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Model;

class LinkUrlValidator
{
    public const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function isAllowed(string $url): bool
    {
        $normalized = (string)preg_replace(
            '/[\x00-\x20\x7F]+/',
            '',
            html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
        $normalized = (string)preg_replace('/[\x00-\x20\x7F]+/', '', rawurldecode($normalized));

        if ($normalized === '' || strpos($normalized, '\\') !== false) {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalized, $matches)) {
            return in_array(strtolower($matches[1]), self::ALLOWED_SCHEMES, true);
        }

        return true;
    }

    public static function isAllowedColor(string $color): bool
    {
        return (bool)preg_match(
            '/^(#[0-9a-f]{3,8}|[a-z]{3,30}|(rgb|rgba|hsl|hsla)\([0-9.,%\s\/]{1,60}\))$/i',
            $color
        );
    }
}
