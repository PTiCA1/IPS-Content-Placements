<?php
/** Pages Block Placements - MIT. */
namespace IPS\rqblockplacements;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

/** Only frontend hooks whose sibling context can accept a block. */
final class HookCatalog
{
    private static ?array $catalog = null;

    public static function options(): array
    {
        $options = [];
        foreach (self::catalog() as $path => $hooks) {
            foreach ($hooks as $name => $meta) { $options[$path][$name] = $name . ' (' . $meta['tag'] . ')'; }
        }
        return $options;
    }

    public static function has(string $path, string $name): bool { return isset(self::catalog()[$path][$name]); }

    public static function wrapper(string $path, string $name): string
    {
        $meta = self::catalog()[$path][$name] ?? null;
        if (!$meta) { throw new \InvalidArgumentException('rqbp_invalid_hook'); }
        return $meta['wrapper'];
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
        $fingerprint = hash('sha256', json_encode($versions));
        $store = \IPS\Data\Store::i();
        if (isset($store->rqbp_hook_catalog)) {
            $cached = $store->rqbp_hook_catalog;
            if (is_array($cached) && ($cached['fingerprint'] ?? '') === $fingerprint) { return self::$catalog = $cached['hooks']; }
        }
        self::$catalog = [];
        foreach (\IPS\Db::i()->select('*', 'core_theme_templates', [['template_location=? AND template_has_hookpoints=?', 'front', 1]]) as $row) {
            if (!isset($enabled[$row['template_app']])) { continue; }
            $path = $row['template_app'] . '/front/' . $row['template_group'] . '/' . $row['template_name'];
            $content = $row['template_content'];
            // Native hook names are literal attributes; never compile any user supplied template.
            preg_match_all('/<([a-z][a-z0-9-]*)\b[^>]*\bdata-ips-hook\s*=\s*([\'"])([^\'"]+)\2[^>]*>/is', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($matches as $match) {
                $tag = strtolower($match[1][0]);
                $name = $match[3][0];
                if (!in_array($tag, ['div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'form', 'figure', 'blockquote'], true)) { continue; }
                $parent = self::parentTag(substr($content, 0, $match[0][1]));
                if (in_array($parent, ['head', 'title', 'script', 'style', 'select', 'option', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'svg', 'a', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) { continue; }
                $wrapper = $tag === 'li' || in_array($parent, ['ul', 'ol', 'menu'], true) ? 'li' : 'div';
                self::$catalog[$path][$name] = ['tag' => $tag, 'wrapper' => $wrapper];
            }
        }
        ksort(self::$catalog);
        $store->rqbp_hook_catalog = ['fingerprint' => $fingerprint, 'hooks' => self::$catalog];
        return self::$catalog;
    }

    private static function parentTag(string $prefix): ?string
    {
        // Strip template expressions so comparison operators are not mistaken for HTML.
        $prefix = preg_replace('/\{\{.*?\}\}|\{[^{}]*\}/s', '', $prefix);
        preg_match_all('/<\/?([a-z][a-z0-9-]*)\b[^>]*>/i', $prefix, $tags, PREG_SET_ORDER);
        $stack = [];
        $void = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
        foreach ($tags as $tag) {
            $name = strtolower($tag[1]);
            if (str_starts_with($tag[0], '</')) {
                $index = array_search($name, array_reverse($stack, true), true);
                if ($index !== false) { $stack = array_slice($stack, 0, $index); }
            } elseif (!in_array($name, $void, true) && !str_ends_with($tag[0], '/>')) { $stack[] = $name; }
        }
        return $stack ? end($stack) : null;
    }
}
