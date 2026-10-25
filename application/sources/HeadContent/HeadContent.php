<?php
/** Content Placements - original application code, MIT licensed. */
namespace IPS\rqblockplacements;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

/** Conservative HEAD fragment grammar for trusted content, not a security sandbox. */
final class HeadContent
{
    private const SPACE = " \t\n\r\f";

    /** Validate without executing, resolving or rewriting any submitted content. */
    public static function isValid(string $html): bool
    {
        if (str_contains($html, "\0")) { return false; }
        $length = strlen($html);
        $offset = 0;
        $hasElement = false;
        while ($offset < $length) {
            self::whitespace($html, $offset, $length);
            if ($offset === $length) { break; }
            if (substr($html, $offset, 4) === '<!--') {
                $end = strpos($html, '-->', $offset + 4);
                if ($end === false) { return false; }
                $comment = substr($html, $offset + 4, $end - $offset - 4);
                // Browsers also terminate malformed comments abruptly. Reject
                // those forms rather than hiding later markup inside them.
                if (str_starts_with($comment, '>') || str_starts_with($comment, '->')
                    || str_contains($comment, '<!--') || str_contains($comment, '--!>')
                    || str_ends_with($comment, '<!-')) { return false; }
                $offset = $end + 3;
                continue;
            }
            $opening = self::opening($html, $offset, $length);
            if ($opening === null) { return false; }
            [$tag, $offset, $selfClosing] = $opening;
            if ($tag === 'script' || $tag === 'style') {
                if ($selfClosing) { return false; }
                // Find the first end-tag candidate, including malformed forms
                // which HTML parsers may nevertheless treat as a terminator.
                $prefix = '~</' . $tag . '(?=[\x09\x0A\x0C\x0D\x20/>])~i';
                if (!preg_match($prefix, $html, $candidate, PREG_OFFSET_CAPTURE, $offset)) { return false; }
                $end = $candidate[0][1];
                if ($tag === 'script' && self::doubleEscapedScript(substr($html, $offset, $end - $offset))) {
                    return false;
                }
                if (!preg_match('~\G</' . $tag . '[\x09\x0A\x0C\x0D\x20]*>~i', $html, $closing, 0, $end)) {
                    return false;
                }
                $offset = $end + strlen($closing[0]);
            }
            $hasElement = true;
        }
        return $hasElement;
    }

    private static function doubleEscapedScript(string $raw): bool
    {
        // Legacy HTML-comment escaping plus a nested script prefix can make
        // the browser treat the first </script> as text rather than a close.
        // Reject that ambiguous form instead of attempting a JS/HTML parser.
        foreach (explode('-->', $raw) as $segment) {
            $comment = strpos($segment, '<!--');
            if ($comment !== false && preg_match('~<script(?=[\x09\x0A\x0C\x0D\x20/>])~i', $segment, $match, 0, $comment + 4)) {
                return true;
            }
        }
        return false;
    }

    /** @return array{string, int, bool}|null */
    private static function opening(string $html, int $offset, int $length): ?array
    {
        if (!preg_match('~\G<(script|style|meta|link)(?=[\x09\x0A\x0C\x0D\x20/>])~i', $html, $match, 0, $offset)) {
            return null;
        }
        $tag = strtolower($match[1]);
        $offset += strlen($match[0]);
        while ($offset < $length) {
            $before = $offset;
            self::whitespace($html, $offset, $length);
            if ($offset === $length) { return null; }
            if ($html[$offset] === '>') { return [$tag, $offset + 1, false]; }
            if (substr($html, $offset, 2) === '/>') { return [$tag, $offset + 2, true]; }
            // Attributes require a separator, including after a quoted value.
            if ($offset === $before || !preg_match('~\G[^\x00-\x20\x7F"\x27<>/=`]+~', $html, $attribute, 0, $offset)) {
                return null;
            }
            $offset += strlen($attribute[0]);
            $afterName = $offset;
            self::whitespace($html, $offset, $length);
            if ($offset === $length) { return null; }
            if ($html[$offset] !== '=') {
                $offset = $afterName;
                continue;
            }
            $offset++;
            self::whitespace($html, $offset, $length);
            if ($offset === $length) { return null; }
            if ($html[$offset] === '"' || $html[$offset] === "'") {
                $end = strpos($html, $html[$offset], $offset + 1);
                if ($end === false) { return null; }
                $offset = $end + 1;
            } else {
                if (!preg_match('~\G[^\x00-\x20\x7F"\x27`=<>]+~', $html, $value, 0, $offset)) { return null; }
                $offset += strlen($value[0]);
            }
        }
        return null;
    }

    private static function whitespace(string $html, int &$offset, int $length): void
    {
        while ($offset < $length && str_contains(self::SPACE, $html[$offset])) { $offset++; }
    }
}
