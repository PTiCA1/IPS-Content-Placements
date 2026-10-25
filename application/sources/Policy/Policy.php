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
        $rule['platforms'] = self::platforms($rule['platforms']);
        foreach (['name', 'hook_path', 'hook_name', 'selector', 'template_code'] as $key) { $rule[$key] = (string) ($rule[$key] ?? ''); }
        $rule['content_type'] = (string) ($rule['content_type'] ?? 'block');
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
        $platform = self::deviceType($context['platform'] ?? null, $context['user_agent'] ?? null);
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
        return ['mobile' => 'rqbp_platform_mobile', 'tablet' => 'rqbp_platform_tablet', 'desktop' => 'rqbp_platform_desktop'];
    }

    /** Only three selectable classes; older OS filters are converted on read/save. */
    public static function platforms(mixed $value): array
    {
        $selected = [];
        foreach (self::lines($value) as $platform) {
            // Old Android rules covered both phones and tablets, so keep both classes.
            $types = strtolower($platform) === 'android' ? ['mobile', 'tablet'] : [self::deviceType($platform)];
            foreach ($types as $type) { $selected[$type] = true; }
        }
        return array_values(array_intersect(array_keys(self::platformOptions()), array_keys($selected)));
    }

    /** Server-side device class, using the native IPS platform and original UA. */
    public static function deviceType(?string $platform, ?string $userAgent = null): string
    {
        $platform = strtolower(trim($platform ?? ''));
        if (isset(self::platformOptions()[$platform])) { return $platform; }
        $userAgent ??= '';
        // Check phones before Android: Windows Phone UAs can also contain Android.
        if (in_array($platform, ['iphone', 'ipod', 'windows phone', 'blackberry', 'symbian', 'sailfish', 'tizen'], true)
            || preg_match('/\b(?:iPhone|iPod|Windows Phone|IEMobile|BlackBerry|BB10|Symbian|Sailfish)\b/i', $userAgent)) {
            return 'mobile';
        }
        if (in_array($platform, ['ipad', 'kindle', 'kindle fire', 'playbook'], true)
            || preg_match('/\b(?:iPad|Tablet|PlayBook|Kindle)\b|Silk\//i', $userAgent)
            || (($platform === 'macintosh' || preg_match('/\bMacintosh\b/i', $userAgent)) && preg_match('/\bMobile\b/i', $userAgent))) {
            return 'tablet';
        }
        if ($platform === 'android' || preg_match('/\bAndroid\b/i', $userAgent)) {
            return preg_match('/\bMobile\b/i', $userAgent) ? 'mobile' : 'tablet';
        }
        if (preg_match('/\b(?:Mobile|Mobi)\b/i', $userAgent)) { return 'mobile'; }
        // Unknown agents, bots without a device marker and desktop-mode iPads share this fallback.
        return 'desktop';
    }
}
