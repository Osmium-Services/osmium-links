<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumLinks\Models;

/**
 * Rules for the text a link is saved with, kept apart from the controller so they can be tested on their own.
 */
class LinkText
{
    /** Logo folder, as a site-relative path */
    public const LOGO_DIR = '/assets/images/links/';

    /** Only web addresses: anything else (javascript:, data:) would be an XSS risk in a theme's href */
    public static function isValidUrl(string $url): bool
    {
        $isTooLong = \strlen($url) > 255;
        if ($isTooLong) return false;

        $scheme = \parse_url($url, PHP_URL_SCHEME);
        $host = \parse_url($url, PHP_URL_HOST);
        $isWebAddress = \in_array($scheme, ['http', 'https'], true) && !empty($host);

        return $isWebAddress;
    }

    /** Empty after trimming means "none" */
    public static function nullIfEmpty(string $value): ?string
    {
        $trimmed = \trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
