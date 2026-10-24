<?php
/** Pages Block Placements - original application code, MIT licensed. */
namespace IPS\rqblockplacements;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

/** Pure rule evaluation, deliberately independent of the IPS database. */
final class Policy
{
    public static function parseBlockReference(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\{block\s*=\s*([\'"])([^\'"{}\r\n]+)\1\s*\}$/D', $value, $match)) {
            return trim($match[2]);
        }
        if (preg_match('/^[a-zA-Z0-9_\-]+$/D', $value)) { return $value; }
        throw new \InvalidArgumentException('rqbp_invalid_block_reference');
    }

    public static function lines(mixed $value): array
    {
        if (is_array($value)) { $items = $value; }
        elseif ($value === null || $value === '') { return []; }
        else {
            $decoded = json_decode((string) $value, true);
            $items = is_array($decoded) ? $decoded : preg_split('/\r\n|\r|\n/', (string) $value);
        }
        return array_values(array_unique(array_filter(array_map(static fn($v) => trim((string) $v), $items), static fn($v) => $v !== '')));
    }

    public static function normalize(array $row): array
    {
        $rule = [];
        foreach ($row as $key => $value) { $rule[str_starts_with($key, 'rule_') ? substr($key, 5) : $key] = $value; }
        foreach (['id', 'enabled', 'block_id', 'occurrence_index', 'sort_order'] as $key) { $rule[$key] = (int) ($rule[$key] ?? ($key === 'occurrence_index' ? 1 : 0)); }
        foreach (['platforms', 'allow_groups', 'exclude_groups', 'apps', 'include_urls', 'exclude_urls'] as $key) { $rule[$key] = self::lines($rule[$key] ?? []); }
        foreach (['name', 'hook_path', 'hook_name', 'selector'] as $key) { $rule[$key] = (string) ($rule[$key] ?? ''); }
        $rule['mode'] = $rule['mode'] ?? 'css';
        $rule['position'] = $rule['position'] ?? 'after';
        $rule['occurrence'] = $rule['occurrence'] ?? 'first';
        return $rule;
    }

    /** Null means eligible; otherwise a stable diagnostic reason. */
    public static function evaluate(array $rule, array $context, bool $audience = true): ?string
    {
        $rule = self::normalize($rule);
        if (!$rule['enabled']) { return 'disabled'; }
        if ($rule['apps'] && !in_array((string) ($context['app'] ?? ''), $rule['apps'], true)) { return 'application'; }
        $url = (string) ($context['url'] ?? '/');
        if (self::matchesAny($url, $rule['exclude_urls'])) { return 'url_excluded'; }
        if ($rule['include_urls'] && !self::matchesAny($url, $rule['include_urls'])) { return 'url'; }
        if (!$audience) { return null; }
        $platform = $context['platform'] ?? '__unknown__';
        $platform = $platform === '' ? '__unknown__' : $platform;
        if ($rule['platforms'] && !in_array($platform, $rule['platforms'], true)) { return 'platform'; }
        $groups = array_map('strval', $context['groups'] ?? []);
        if (array_intersect($groups, $rule['exclude_groups'])) { return 'group_excluded'; }
        if ($rule['allow_groups'] && !array_intersect($groups, $rule['allow_groups'])) { return 'group'; }
        return null;
    }

    public static function matchesAny(string $url, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $regex = '~^' . str_replace('\\*', '.*', preg_quote($pattern, '~')) . '$~uD';
            if (preg_match($regex, $url) === 1) { return true; }
        }
        return false;
    }

    /** Board-relative path plus unmodified query; fragments never participate. */
    public static function relativeUrl(string $url, string $base): string
    {
        $parts = parse_url($url);
        if ($parts === false) { return '/'; }
        $path = $parts['path'] ?? '/';
        $basePath = rtrim((string) (parse_url($base, PHP_URL_PATH) ?: ''), '/');
        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) { $path = substr($path, strlen($basePath)); }
        return '/' . ltrim($path, '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    public static function selectedOccurrence(string $mode, int $nth, int $ordinal): bool
    {
        return match ($mode) { 'all' => $ordinal > 0, 'nth' => $ordinal === max(1, $nth), default => $ordinal === 1 };
    }

    public static function platformOptions(): array
    {
        $values = ['Android', 'BlackBerry', 'Chrome OS', 'FreeBSD', 'iPad', 'iPhone', 'iPod', 'Kindle', 'Kindle Fire', 'Linux', 'Macintosh', 'NetBSD', 'New Nintendo 3DS', 'Nintendo 3DS', 'Nintendo DS', 'Nintendo Switch', 'Nintendo Wii', 'Nintendo WiiU', 'OpenBSD', 'PlayBook', 'PlayStation 3', 'PlayStation 4', 'PlayStation 5', 'PlayStation Vita', 'Sailfish', 'Symbian', 'Tizen', 'Windows', 'Windows Phone', 'Xbox', 'Xbox One'];
        return array_combine($values, $values) + ['__unknown__' => 'rqbp_platform_unknown'];
    }
}
