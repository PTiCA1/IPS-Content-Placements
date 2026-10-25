<?php
/** Content Placements - MIT. */
namespace IPS\rqblockplacements;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

/** Frontend hooks with an explicit wrapper policy for every safe insertion position. */
final class HookCatalog
{
    private const CACHE_VERSION = 3;
    private const FLOW_CONTAINERS = ['div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav', 'li', 'form', 'figure', 'blockquote'];
    private const LIST_CONTAINERS = ['ul', 'ol', 'menu'];
    private const SIBLING_ANCHORS = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    private const FORBIDDEN_CONTEXTS = [
        'head', 'title', 'script', 'style', 'select', 'option', 'optgroup', 'datalist',
        'table', 'caption', 'colgroup', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
        'svg', 'math', 'a', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'button', 'label', 'legend', 'summary', 'pre', 'code', 'strong', 'em', 'small',
        'b', 'i', 'u', 's', 'sub', 'sup', 'textarea', 'iframe', 'picture',
    ];
    private const TAG_PATTERN = '~</?([a-z][a-z0-9-]*)\b(?:[^<>"\x27]|"[^"]*"|\x27[^\x27]*\x27)*>~i';
    private static ?array $catalog = null;

    public static function options(): array
    {
        $options = [];
        foreach (self::catalog() as $path => $hooks) {
            foreach ($hooks as $name => $meta) { $options[$path][$name] = $name . ' (' . $meta['tag'] . ')'; }
        }
        return $options;
    }

    public static function positions(string $path, string $name): array
    {
        return array_keys(self::catalog()[$path][$name]['positions'] ?? []);
    }

    public static function has(string $path, string $name, ?string $position = null): bool
    {
        $positions = self::catalog()[$path][$name]['positions'] ?? [];
        return $position === null ? (bool) $positions : isset($positions[$position]);
    }

    /** An empty wrapper is valid raw head metadata; only null means unsupported. */
    public static function wrapper(string $path, string $name, string $position = 'after'): string
    {
        $wrapper = self::catalog()[$path][$name]['positions'][$position] ?? null;
        if ($wrapper === null) { throw new \InvalidArgumentException('rqbp_invalid_hook'); }
        return $wrapper;
    }

    public static function reset(): void
    {
        self::$catalog = null;
        unset(\IPS\Data\Store::i()->rqbp_hook_catalog);
    }

    private static function catalog(): array
    {
        if (self::$catalog !== null) { return self::$catalog; }
        $enabled = \IPS\Application::enabledApplications();
        $versions = [];
        foreach ($enabled as $key => $app) { $versions[$key] = (string) $app->long_version; }
        ksort($versions);
        $fingerprint = hash('sha256', self::CACHE_VERSION . ':' . json_encode($versions));
        $store = \IPS\Data\Store::i();
        if (isset($store->rqbp_hook_catalog)) {
            $cached = $store->rqbp_hook_catalog;
            if (is_array($cached) && ($cached['version'] ?? null) === self::CACHE_VERSION
                && ($cached['fingerprint'] ?? '') === $fingerprint && is_array($cached['hooks'] ?? null)) {
                return self::$catalog = $cached['hooks'];
            }
        }
        self::$catalog = [];
        foreach (\IPS\Db::i()->select('*', 'core_theme_templates', [['template_location=? AND template_has_hookpoints=?', 'front', 1]]) as $row) {
            if (!isset($enabled[$row['template_app']])) { continue; }
            $path = $row['template_app'] . '/front/' . $row['template_group'] . '/' . $row['template_name'];
            // Mask comments and template expressions without evaluating source code.
            $content = preg_replace_callback('/<!--.*?(?:-->|$)|\{\{.*?\}\}|\{[^{}]*\}/s',
                static fn (array $match): string => str_repeat(' ', strlen($match[0])), $row['template_content']);
            preg_match_all(self::TAG_PATTERN, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($matches as $match) {
                if (str_starts_with($match[0][0], '</')) { continue; }
                $tag = strtolower($match[1][0]);
                $name = self::hookName($match[0][0]);
                if ($name === null) { continue; }
                $positions = self::safePositions($tag, self::ancestors(substr($content, 0, $match[0][1])));
                if (isset(self::$catalog[$path][$name])) {
                    // A native hook can occur more than once. Every occurrence must
                    // accept both the position and the same wrapper element.
                    $positions = array_intersect_assoc(self::$catalog[$path][$name]['positions'], $positions);
                }
                self::$catalog[$path][$name] = ['tag' => $tag, 'positions' => $positions];
            }
        }
        foreach (self::$catalog as $path => &$hooks) {
            $hooks = array_filter($hooks, static fn (array $meta): bool => (bool) $meta['positions']);
            if (!$hooks) { unset(self::$catalog[$path]); }
        }
        unset($hooks);
        ksort(self::$catalog);
        $store->rqbp_hook_catalog = ['version' => self::CACHE_VERSION, 'fingerprint' => $fingerprint, 'hooks' => self::$catalog];
        return self::$catalog;
    }

    private static function hookName(string $tag): ?string
    {
        // Consume complete attribute values so a quoted example of an attribute
        // cannot masquerade as a real data-ips-hook on the surrounding element.
        preg_match_all('~\s+([a-z_:][a-z0-9_.:-]*)(?:\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\s>"\x27`=]+)))?~i',
            $tag, $attributes, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        foreach ($attributes as $attribute) {
            if (strtolower($attribute[1]) !== 'data-ips-hook') { continue; }
            $name = $attribute[2] ?? $attribute[3] ?? null;
            return $name !== null && $name !== '' && !preg_match('/\s/', $name) ? $name : null;
        }
        return null;
    }

    private static function safePositions(string $tag, array $ancestors): array
    {
        if (array_intersect($ancestors, self::FORBIDDEN_CONTEXTS)) { return []; }
        $parent = $ancestors ? end($ancestors) : null;
        if ($tag === 'head') {
            return $parent === null || $parent === 'html' ? ['inside-start' => '', 'inside-end' => ''] : [];
        }
        if ($tag === 'body') {
            return $parent === null || $parent === 'html' ? ['inside-start' => 'div', 'inside-end' => 'div'] : [];
        }
        if ($parent === 'html') { return []; }
        if (!in_array($tag, array_merge(self::FLOW_CONTAINERS, self::LIST_CONTAINERS, self::SIBLING_ANCHORS), true)) { return []; }
        $sibling = $tag === 'li' || in_array($parent, self::LIST_CONTAINERS, true) ? 'li' : 'div';
        $positions = ['before' => $sibling];
        if (in_array($tag, array_merge(self::FLOW_CONTAINERS, self::LIST_CONTAINERS), true)) {
            $child = in_array($tag, self::LIST_CONTAINERS, true) ? 'li' : 'div';
            $positions['inside-start'] = $child;
            $positions['inside-end'] = $child;
        }
        $positions['after'] = $sibling;
        return $positions;
    }

    private static function ancestors(string $prefix): array
    {
        preg_match_all(self::TAG_PATTERN, $prefix, $tags, PREG_SET_ORDER);
        $stack = [];
        $void = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
        foreach ($tags as $tag) {
            $name = strtolower($tag[1]);
            if (str_starts_with($tag[0], '</')) {
                $index = array_search($name, array_reverse($stack, true), true);
                if ($index !== false) { $stack = array_slice($stack, 0, $index); }
            } elseif (!in_array($name, $void, true) && !str_ends_with($tag[0], '/>')) { $stack[] = $name; }
        }
        return $stack;
    }
}
