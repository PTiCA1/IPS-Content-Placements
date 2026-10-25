<?php
/** Content Placements - original application code, MIT licensed. */
namespace IPS\rqblockplacements;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

/** A conservative fragment index. Original markup is never serialized or repaired. */
final class ServerDocument
{
    public const MAX_FRAGMENT_BYTES = 2097152;
    public const MAX_NODES = 50000;
    public const MAX_DEPTH = 256;
    private const SPACE = " \t\n\r\f";
    private const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
    private const RAW = ['script', 'style', 'textarea', 'title', 'xmp', 'iframe', 'noembed', 'noframes', 'noscript'];
    private const TABLE_CHILDREN = [
        'table' => ['caption', 'colgroup', 'thead', 'tbody', 'tfoot', 'script', 'style', 'template'],
        'colgroup' => ['col', 'template'],
        'thead' => ['tr', 'script', 'template'], 'tbody' => ['tr', 'script', 'template'], 'tfoot' => ['tr', 'script', 'template'],
        'tr' => ['td', 'th', 'script', 'template'],
    ];
    private const P_CLOSERS = ['address', 'article', 'aside', 'blockquote', 'center', 'details', 'dialog', 'dir', 'div', 'dl', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'listing', 'main', 'menu', 'nav', 'ol', 'p', 'pre', 'search', 'section', 'table', 'ul'];
    private const FOREIGN_BREAKOUT = ['b', 'big', 'blockquote', 'body', 'br', 'center', 'code', 'dd', 'div', 'dl', 'dt', 'em', 'embed', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'hr', 'i', 'img', 'li', 'listing', 'menu', 'meta', 'nobr', 'ol', 'p', 'pre', 'ruby', 's', 'small', 'span', 'strong', 'strike', 'sub', 'sup', 'table', 'tt', 'u', 'ul', 'var', 'font', 'foreignobject', 'annotation-xml'];

    /** @var array<int, array<string, mixed>> Element IDs retain document order. */
    public array $nodes = [];

    private function __construct() {}

    /** @throws \InvalidArgumentException The entire fragment is unsupported. */
    public static function parse(string $html): self
    {
        $length = strlen($html);
        if ($length > self::MAX_FRAGMENT_BYTES) { throw new \InvalidArgumentException('server_fragment_too_large'); }
        if (str_contains($html, "\0")) { self::unsupported(); }
        $document = new self;
        $document->nodes[0] = ['tag' => 'div', 'attrs' => ['id' => 'ipsLayout__main'], 'parent' => null, 'children' => [],
            'start' => 0, 'openEnd' => 0, 'closeStart' => $length, 'end' => $length, 'synthetic' => true, 'namespace' => 'html'];
        $stack = [0];
        $offset = 0;
        $nextId = 1;
        $inert = [];
        while ($offset < $length) {
            $parent = $stack[count($stack) - 1];
            if ($html[$offset] !== '<') {
                $next = strpos($html, '<', $offset);
                $next = $next === false ? $length : $next;
                self::text(substr($html, $offset, $next - $offset), $document->nodes[$parent]['tag']);
                $offset = $next;
                continue;
            }
            // Native placeholders are text, including when final-output parsing is deferred.
            if (preg_match('~\G<(?:fileStore\.[a-z0-9_]+|___base_url___)>~i', $html, $placeholder, 0, $offset)) {
                self::text($placeholder[0], $document->nodes[$parent]['tag']);
                $offset += strlen($placeholder[0]);
                continue;
            }
            if (substr($html, $offset, 4) === '<!--') {
                $end = strpos($html, '-->', $offset + 4);
                if ($end === false) { self::unsupported(); }
                $comment = substr($html, $offset + 4, $end - $offset - 4);
                if (str_starts_with($comment, '>') || str_starts_with($comment, '->') || str_contains($comment, '<!--')
                    || str_contains($comment, '--!>') || str_ends_with($comment, '<!-')) { self::unsupported(); }
                $offset = $end + 3;
                continue;
            }
            if (substr($html, $offset, 2) === '</') {
                if (!preg_match('~\G</([a-z][a-z0-9:-]*)[\x09\x0A\x0C\x0D\x20]*>~i', $html, $closing, 0, $offset)
                    || count($stack) === 1 || $document->nodes[$parent]['tag'] !== strtolower($closing[1])) { self::unsupported(); }
                $document->nodes[$parent]['closeStart'] = $offset;
                $offset += strlen($closing[0]);
                $document->nodes[$parent]['end'] = $offset;
                array_pop($stack);
                continue;
            }
            if (!preg_match('~\G<[a-z]~i', $html, $unused, 0, $offset)) {
                if (in_array($html[$offset + 1] ?? '', ['!', '?'], true)) { self::unsupported(); }
                self::text('<', $document->nodes[$parent]['tag']);
                $offset++;
                continue;
            }
            $start = $offset;
            [$tag, $attrs, $offset, $selfClosing] = self::opening($html, $offset, $length);
            $namespace = $document->nodes[$parent]['namespace'];
            if ($namespace === 'html' && ($tag === 'svg' || $tag === 'math')) { $namespace = $tag; }
            elseif ($namespace !== 'html' && in_array($tag, self::FOREIGN_BREAKOUT, true)) { self::unsupported(); }
            if ($namespace === 'html') {
                self::structure($tag, $parent, $stack, $document->nodes);
                if ($selfClosing && !in_array($tag, self::VOID, true)) { self::unsupported(); }
            }
            if ($nextId >= self::MAX_NODES || count($stack) > self::MAX_DEPTH) {
                throw new \InvalidArgumentException('server_fragment_too_large');
            }
            $id = $nextId++;
            $document->nodes[$id] = ['tag' => $tag, 'attrs' => $attrs, 'parent' => $parent, 'children' => [],
                'start' => $start, 'openEnd' => $offset, 'closeStart' => $offset, 'end' => $offset, 'synthetic' => false, 'namespace' => $namespace];
            $document->nodes[$parent]['children'][] = $id;
            if (isset($inert[$parent]) || $document->nodes[$parent]['tag'] === 'template') { $inert[$id] = true; }
            if (($namespace === 'html' && in_array($tag, self::VOID, true)) || $selfClosing) { continue; }
            if ($namespace === 'html' && in_array($tag, self::RAW, true)) {
                [$closeStart, $end] = self::rawEnd($html, $tag, $offset);
                $document->nodes[$id]['closeStart'] = $closeStart;
                $document->nodes[$id]['end'] = $end;
                $offset = $end;
                continue;
            }
            $stack[] = $id;
        }
        if (count($stack) !== 1) { self::unsupported(); }
        // Template contents belong to a separate document fragment, not its DOM children.
        foreach ($inert as $id => $unused) { unset($document->nodes[$id]); }
        foreach ($document->nodes as &$node) {
            if ($node['tag'] === 'template') { $node['children'] = []; }
        }
        unset($node);
        return $document;
    }

    /** @return array{string, array<string, string>, int, bool} */
    private static function opening(string $html, int $offset, int $length): array
    {
        if (!preg_match('~\G<([a-z][a-z0-9:-]*)(?=[\x09\x0A\x0C\x0D\x20/>])~i', $html, $match, 0, $offset)) { self::unsupported(); }
        $tag = strtolower($match[1]);
        $offset += strlen($match[0]);
        $attrs = [];
        while ($offset < $length) {
            $before = $offset;
            self::whitespace($html, $offset, $length);
            if ($offset === $length) { self::unsupported(); }
            if ($html[$offset] === '>') { return [$tag, $attrs, $offset + 1, false]; }
            if (substr($html, $offset, 2) === '/>') { return [$tag, $attrs, $offset + 2, true]; }
            if ($offset === $before || !preg_match('~\G[^\x00-\x20\x7F"\x27<>/=`]+~', $html, $attribute, 0, $offset)) { self::unsupported(); }
            $name = strtolower($attribute[0]);
            if (array_key_exists($name, $attrs)) { self::unsupported(); }
            $offset += strlen($attribute[0]);
            $afterName = $offset;
            self::whitespace($html, $offset, $length);
            if ($offset === $length) { self::unsupported(); }
            if ($html[$offset] !== '=') {
                $attrs[$name] = '';
                $offset = $afterName;
                continue;
            }
            $offset++;
            self::whitespace($html, $offset, $length);
            if ($offset === $length) { self::unsupported(); }
            if ($html[$offset] === '"' || $html[$offset] === "'") {
                $end = strpos($html, $html[$offset], $offset + 1);
                if ($end === false) { self::unsupported(); }
                $value = substr($html, $offset + 1, $end - $offset - 1);
                $offset = $end + 1;
            } else {
                if (!preg_match('~\G[^\x00-\x20\x7F"\x27`=<>]+~', $html, $valueMatch, 0, $offset)) { self::unsupported(); }
                $value = $valueMatch[0];
                $offset += strlen($value);
            }
            $attrs[$name] = self::attributeValue($value);
        }
        self::unsupported();
    }

    private static function structure(string $tag, int $parent, array $stack, array $nodes): void
    {
        if (in_array($tag, ['html', 'head', 'body', 'frameset', 'frame', 'plaintext', 'image', 'isindex'], true)) { self::unsupported(); }
        $parentTag = $nodes[$parent]['tag'];
        if (isset(self::TABLE_CHILDREN[$parentTag]) && !in_array($tag, self::TABLE_CHILDREN[$parentTag], true)) { self::unsupported(); }
        if (in_array($parentTag, ['ul', 'ol', 'menu'], true) && !in_array($tag, ['li', 'script', 'template'], true)) { self::unsupported(); }
        if ($tag === 'li' && !in_array($parentTag, ['ul', 'ol', 'menu'], true)) { self::unsupported(); }
        if (in_array($tag, ['dt', 'dd'], true) && $parentTag !== 'dl') { self::unsupported(); }
        $requiredParent = ['caption' => ['table'], 'colgroup' => ['table'], 'thead' => ['table'], 'tbody' => ['table'], 'tfoot' => ['table'],
            'tr' => ['thead', 'tbody', 'tfoot'], 'td' => ['tr'], 'th' => ['tr'], 'col' => ['colgroup'], 'option' => ['select', 'optgroup', 'datalist'], 'optgroup' => ['select'],
            'rb' => ['ruby'], 'rt' => ['ruby'], 'rp' => ['ruby'], 'rtc' => ['ruby']];
        if (isset($requiredParent[$tag]) && !in_array($parentTag, $requiredParent[$tag], true)) { self::unsupported(); }
        if ($parentTag === 'select' && !in_array($tag, ['option', 'optgroup', 'hr', 'script', 'template'], true)) { self::unsupported(); }
        if ($parentTag === 'optgroup' && !in_array($tag, ['option', 'script', 'template'], true)) { self::unsupported(); }
        if ($parentTag === 'option') { self::unsupported(); }
        foreach ($stack as $ancestor) {
            if ($ancestor === 0) { continue; }
            $ancestorTag = $nodes[$ancestor]['tag'];
            if (($ancestorTag === 'p' && in_array($tag, self::P_CLOSERS, true))
                || ($tag === 'a' && $ancestorTag === 'a') || ($tag === 'form' && $ancestorTag === 'form')
                || ($tag === 'button' && $ancestorTag === 'button') || ($tag === 'nobr' && $ancestorTag === 'nobr')
                || ($tag === 'table' && $ancestorTag === 'caption')
                || (preg_match('/^h[1-6]$/', $tag) && preg_match('/^h[1-6]$/', $ancestorTag))) { self::unsupported(); }
        }
    }

    private static function attributeValue(string $value): string
    {
        // HTML input preprocessing happens before character-reference decoding.
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        preg_match_all('~&(?:\#[xX][0-9a-fA-F]+|\#[0-9]+|[a-zA-Z][a-zA-Z0-9]*);?~', $value, $references, PREG_OFFSET_CAPTURE);
        foreach ($references[0] as [$reference, $offset]) {
            $numeric = str_starts_with($reference, '&#');
            $terminated = str_ends_with($reference, ';');
            $decoded = html_entity_decode($reference . ($terminated ? '' : ';'), ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
            if (!$terminated) {
                // In an attribute, an alphanumeric/equals suffix prevents a
                // legacy named reference from being consumed by the browser.
                $after = $value[$offset + strlen($reference)] ?? '';
                if ($numeric || ($after !== '=' && $decoded !== $reference . ';')) { self::unsupported(); }
            } elseif ($numeric && $decoded === $reference) {
                // Browsers replace invalid/C1 numeric references differently
                // from PHP. Reject those rather than invent a different DOM.
                self::unsupported();
            }
        }
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @return array{int, int} */
    private static function rawEnd(string $html, string $tag, int $offset): array
    {
        if (!preg_match('~</' . $tag . '(?=[\x09\x0A\x0C\x0D\x20/>])~i', $html, $candidate, PREG_OFFSET_CAPTURE, $offset)) { self::unsupported(); }
        $start = $candidate[0][1];
        if ($tag === 'script') {
            foreach (explode('-->', substr($html, $offset, $start - $offset)) as $segment) {
                $comment = strpos($segment, '<!--');
                if ($comment !== false && preg_match('~<script(?=[\x09\x0A\x0C\x0D\x20/>])~i', $segment, $match, 0, $comment + 4)) { self::unsupported(); }
            }
        }
        if (!preg_match('~\G</' . $tag . '[\x09\x0A\x0C\x0D\x20]*>~i', $html, $closing, 0, $start)) { self::unsupported(); }
        return [$start, $start + strlen($closing[0])];
    }

    private static function text(string $text, string $parentTag): void
    {
        if (isset(self::TABLE_CHILDREN[$parentTag]) && strspn($text, self::SPACE) !== strlen($text)) { self::unsupported(); }
    }

    private static function whitespace(string $html, int &$offset, int $length): void
    {
        while ($offset < $length && str_contains(self::SPACE, $html[$offset])) { $offset++; }
    }

    private static function unsupported(): never
    {
        throw new \InvalidArgumentException('server_html_unsupported');
    }
}
