<?php
/** Original response-header boundary test; optional IPS Output source is read locally only. */
namespace {
    define('IPS\SUITE_UNIQUE_KEY', 'page-cache-test');
    foreach (['IN_DEV' => false, 'DEBUG_TEMPLATES' => false, 'QUERY_LOG' => false, 'CACHING_LOG' => false,
        'REDIS_LOG' => false, 'ENFORCE_ACCESS' => true, 'CACHE_PAGE_TIMEOUT' => 300, 'CIC' => false] as $key => $value) {
        define('IPS\\' . $key, $value);
    }
}
namespace IPS {
    class Dispatcher { public static function hasInstance(): bool { return false; } }
    class Request {
        public array $cookie = [];
        public array $clearAutoSaveCookie = [];
        public static function i(): self { static $instance; return $instance ??= new self; }
    }
    class Lang { public static function vleActive(): bool { return false; } }
    if (!getenv('RQBP_IPS_OUTPUT_SOURCE')) {
        class Output {
            protected static ?self $instance = null;
            public static bool $cacheable = true;
            public array $httpHeaders = [];
            public static function i(): self { return self::$instance ??= new self; }
            public static function setCacheTime($value): void { self::$cacheable = $value; }
            public static function getNoCacheHeaders(): array { return ['Expires' => 0, 'Cache-Control' => 'no-cache, no-store']; }
        }
    }
}
namespace IPS\core {
    class DataLayer { public static function hasInstance(): bool { return false; } }
    class Advertisement { public static function updateImpressions(): void {} }
}
namespace {
    $native = getenv('RQBP_IPS_OUTPUT_SOURCE');
    if ($native) { require rtrim($native, '/') . '/system/Output/Output.php'; }
    require __DIR__ . '/../application/sources/PageCache/PageCache.php';
    use IPS\rqblockplacements\PageCache;

    class PageCacheOutputProbe extends \IPS\Output {
        public array $captured = [];
        public function __construct() {}
        public function sendHeader(string $header): void { $this->captured[] = $header; }
    }
    $checks = 0;
    function check(bool $condition, string $message): void {
        global $checks;
        $checks++;
        if (!$condition) { throw new \RuntimeException($message); }
    }
    function probe(): PageCacheOutputProbe {
        $output = new PageCacheOutputProbe;
        $instance = new \ReflectionProperty(\IPS\Output::class, 'instance');
        $instance->setValue(null, $output);
        return $output;
    }
    $output = probe();
    $output->httpHeaders = [
        'cAcHe-CoNtRoL' => 'public, max-age=43200', 'Cache-Control' => 'public, s-maxage=43200',
        'EXPIRES' => 'future', 'pragma' => 'cache', 'surrogate-control' => 'max-age=43200',
        'cdn-cache-control' => 'public, max-age=43200', 'CLOUDFLARE-CDN-CACHE-CONTROL' => 'public, max-age=43200',
        'x-accel-expires' => '43200', 'Vary' => 'Cookie', 'X-Unrelated' => 'preserved',
    ];
    PageCache::disable();
    $headers = $output->httpHeaders;
    check(str_contains($headers['Cache-Control'], 'private') && str_contains($headers['Cache-Control'], 'no-store'), 'The response disallows private and shared storage');
    check(count(array_filter(array_keys($headers), static fn ($key) => strtolower($key) === 'cache-control')) === 1, 'Case variants cannot leave conflicting Cache-Control headers');
    check($headers['Expires'] === 0 && $headers['Pragma'] === 'no-cache', 'Legacy cache headers are replaced');
    check($headers['Surrogate-Control'] === 'no-store' && $headers['CDN-Cache-Control'] === 'no-store'
        && $headers['Cloudflare-CDN-Cache-Control'] === 'no-store', 'Present CDN-specific caching overrides are neutralized');
    check($headers['X-Accel-Expires'] === '0', 'A present nginx cache override is disabled');
    check($headers['Vary'] === 'Cookie' && $headers['X-Unrelated'] === 'preserved', 'Unrelated response headers are retained');
    PageCache::disable();
    check($output->httpHeaders === $headers, 'Repeated cache disabling is idempotent');

    $output = probe();
    PageCache::disable();
    check(!isset($output->httpHeaders['Surrogate-Control'], $output->httpHeaders['CDN-Cache-Control'],
        $output->httpHeaders['Cloudflare-CDN-Cache-Control'], $output->httpHeaders['X-Accel-Expires']), 'Vendor headers are only added when an override was present');
    if ($native) {
        $capture = static function (PageCacheOutputProbe $response): array {
            $level = ob_get_level();
            try { $response->sendOutput('', 200, 'text/html', [], true, false, false, false); }
            finally { while (ob_get_level() > $level) { ob_end_clean(); } }
            return array_values(array_filter($response->captured, static fn ($header) => str_starts_with(strtolower($header), 'cache-control:')));
        };
        $output = probe();
        $output->httpHeaders['Cache-Control'] = \IPS\Output::getPublicCacheControlHeader(43200);
        \IPS\Output::setCacheTime(false);
        check(str_contains($capture($output)[0], 'public'), 'Native Output reproduces why setCacheTime alone cannot override controller headers');
        $output = probe();
        $output->httpHeaders['cAcHe-CoNtRoL'] = \IPS\Output::getPublicCacheControlHeader(43200);
        PageCache::disable();
        $output->httpHeaders += ['Cache-Control' => \IPS\Output::getPublicCacheControlHeader(43200)];
        $sent = $capture($output);
        check(count($sent) === 1 && str_contains($sent[0], 'no-store') && str_contains($sent[0], 'private') && !str_contains($sent[0], 'public'), 'Actual native sendOutput emits one private no-store policy after the fix');
    } else {
        check(\IPS\Output::$cacheable === false, 'The native cache-time flag is also disabled');
    }
    echo 'Page cache boundary tests: ' . $checks . ' passed' . ($native ? ' (native Output)' : '') . "\n";
}
