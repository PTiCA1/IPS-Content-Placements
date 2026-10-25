<?php
/** Integration boundary tests against a small original IPS test double, not platform code. */
namespace { define('IPS\SUITE_UNIQUE_KEY', 'test'); }
namespace IPS\Patterns {
    abstract class ActiveRecord {
        protected array $_data = [];
        public static function constructFromData(array $row): static { $record = new static; foreach ($row as $k => $v) { $record->_data[str_starts_with($k, 'rule_') ? substr($k, 5) : $k] = $v; } return $record; }
        public function save(): void {} public function delete(): void {}
        public function __get($key) { return $this->_data[$key] ?? null; }
    }
}
namespace IPS {
    class Application { public static bool $pages = true; public static function appIsEnabled($key): bool { return $key !== 'cms' || self::$pages; } }
    class ExampleContent {
        public static int $calls = 0;
        public function render(string $key): string { self::$calls++; return '<strong>Content ' . $key . '</strong>'; }
    }
    class Theme {
        public static int $compiles = 0;
        public static int $executions = 0;
        public static $callback = null;
        public static function compileTemplate(string $code, string $functionName): string {
            self::$compiles++;
            if ($code === '__INVALID__') { return 'function ' . $functionName . '( {'; }
            return 'function ' . $functionName . '() { return \\IPS\\Theme::execute(' . var_export($code, true) . '); }';
        }
        public static function runProcessFunction(string $compiled, string $functionName): void {
            if (!function_exists('IPS\\Theme\\' . $functionName)) { eval('namespace IPS\\Theme;' . $compiled); }
        }
        public static function execute(string $code): string {
            self::$executions++;
            if (self::$callback) { (self::$callback)(); }
            if (str_contains($code, 'ExampleContent')) { return (new ExampleContent)->render('example'); }
            if ($code === '__THROW_AFTER_ECHO__') { echo 'SECRET_PARTIAL'; ob_start(); echo 'NESTED_SECRET'; throw new \RuntimeException('Template API failed'); }
            if ($code === '__EMPTY__') { return ''; }
            return $code;
        }
    }
    class Dispatcher { public string $controllerLocation = 'front'; public object $application; public function __construct() { $this->application = (object) ['directory' => 'forums']; } public static function hasInstance(): bool { return true; } public static function i(): self { static $s; return $s ??= new self; } }
    class Settings { public bool $rqbp_enabled = true; public static function i(): self { static $s; return $s ??= new self; } }
    class Session { public object $userAgent; public function __construct() { $this->userAgent = (object) ['platform' => 'Windows', 'useragent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0']; } public static function i(): self { static $s; return $s ??= new self; } }
    class Request { public bool $rqbp_diagnostics = false; public bool $ajax = false; public string $currentUrl = 'https://forum.example/community/forums/?page=2'; public static function i(): self { static $s; return $s ??= new self; } public function url(): string { return $this->currentUrl; } public function isAjax(): bool { return $this->ajax; } }
    class Member { public int $member_id = 0; public array $groups = [2]; public static function loggedIn(): self { static $s; return $s ??= new self; } public function language(): object { return new class {
        public function parseOutputForDisplay(&$html): void { $html = str_replace(['<lang>', '__rqbp_error_head_content__'], ['Translated', 'Only script, style, meta and link are allowed.'], $html); $html = preg_replace('/__rqbp_front_[a-z]+__/', "Label with \"quotes\" </script>\nnext", $html); }
        public function addToStack($key): string { return '__' . $key . '__'; }
        public function stripVLETags($html): string { return $html; }
    }; } public function hasAcpRestriction(...$args): bool { return true; } }
    class Output {
        public static bool $cacheable = true;
        public static int $noCacheFailures = 0;
        public array $httpHeaders = [];
        public string $output = '';
        public static function setCacheTime($value = false): void { self::$cacheable = $value; }
        public static function getNoCacheHeaders(): array {
            if (self::$noCacheFailures !== 0) {
                if (self::$noCacheFailures > 0) { self::$noCacheFailures--; }
                throw new \RuntimeException('Injected cache-header failure');
            }
            return ['Expires' => 0, 'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0, s-maxage=0'];
        }
        public static function i(): self { static $s; return $s ??= new self; }
        public function parseFileObjectUrls(&$html): void { $html = str_replace('<fileStore.test>', 'https://cdn.example', $html); }
    }
    class Log { public static array $errors = []; public static function log($error, $key): void { self::$errors[] = $error; } }
    class Db { public static array $rows = []; public static function i(): self { static $s; return $s ??= new self; } public function select(...$args): array { $rows = self::$rows; usort($rows, static fn($a, $b) => [$a['rule_sort_order'] ?? 0, $a['rule_id']] <=> [$b['rule_sort_order'] ?? 0, $b['rule_id']]); return $rows; } }
}
namespace IPS\Http { class Url { public static function baseUrl(): string { return 'https://forum.example/community/'; } } }
namespace IPS\Extensions { abstract class LoaderAbstract { public function onFinish(): void {} } }
namespace IPS\cms\Blocks {
    class Block {
        public bool $active = true; public static bool $allowed = true; public static bool $exists = true; public static int $renders = 0; public static int $loads = 0; public static $callback = null;
        public static string $html = '<span>secret <lang> <fileStore.test></span>';
        public static function load($id): self { self::$loads++; if (!self::$exists) { throw new \OutOfRangeException; } return new self; }
        public function can($action): bool { return self::$allowed; }
        public static function display($block): string { self::$renders++; if (self::$callback) { (self::$callback)(); } return self::$html; }
    }
}
namespace IPS\rqblockplacements {
    class HookCatalog {
        public static function has($path, $name, ?string $position = null): bool {
            return $name !== 'missing' && (!in_array($name, ['body', 'head'], true) || $position === null || in_array($position, ['inside-start', 'inside-end'], true));
        }
        public static function wrapper($path, $name, string $position = 'after'): string {
            return $name === 'head' ? '' : ($name === 'body' || str_starts_with($position, 'inside-') ? 'div' : 'li');
        }
    }
}
namespace {
    require __DIR__ . '/../application/sources/Policy/Policy.php';
    require __DIR__ . '/../application/sources/Rule/Rule.php';
    require __DIR__ . '/../application/Application.php';
    require __DIR__ . '/../application/sources/TemplateContent/TemplateContent.php';
    require __DIR__ . '/../application/sources/HeadContent/HeadContent.php';
    require __DIR__ . '/../application/sources/ServerSelector/ServerSelector.php';
    require __DIR__ . '/../application/sources/ServerDocument/ServerDocument.php';
    require __DIR__ . '/../application/sources/ServerPlacement/ServerPlacement.php';
    require __DIR__ . '/../application/sources/PageCache/PageCache.php';
    require __DIR__ . '/../application/sources/Runtime/Runtime.php';
    require __DIR__ . '/../application/extensions/core/Loader/Placements.php';
    use IPS\rqblockplacements\Runtime;
    use IPS\cms\Blocks\Block;
    $checks = 0;
    function check(bool $condition, string $message): void { global $checks; $checks++; if (!$condition) { throw new \RuntimeException($message); } }
    function fixture(array $overrides = []): void {
        Runtime::reset();
        Block::$renders = Block::$loads = 0; Block::$allowed = Block::$exists = true; Block::$callback = null;
        Block::$html = '<span>secret <lang> <fileStore.test></span>';
        \IPS\Theme::$compiles = \IPS\Theme::$executions = \IPS\ExampleContent::$calls = 0;
        \IPS\Theme::$callback = null;
        \IPS\Application::$pages = true;
        \IPS\Log::$errors = [];
        \IPS\Output::$cacheable = true;
        \IPS\Output::$noCacheFailures = 0;
        \IPS\Output::i()->httpHeaders = [];
        \IPS\Output::i()->output = '<section class="category">Original content</section>';
        \IPS\Session::i()->userAgent->platform = 'Windows';
        \IPS\Session::i()->userAgent->useragent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0';
        \IPS\Member::loggedIn()->groups = [2];
        \IPS\Member::loggedIn()->member_id = 0;
        \IPS\Settings::i()->rqbp_enabled = true;
        \IPS\Request::i()->rqbp_diagnostics = false;
        \IPS\Request::i()->ajax = false;
        \IPS\Request::i()->currentUrl = 'https://forum.example/community/forums/?page=2';
        \IPS\Db::$rows = [array_replace([
            'rule_id' => 1, 'rule_name' => 'Test', 'rule_enabled' => 1, 'rule_block_id' => 9,
            'rule_mode' => 'css', 'rule_selector' => '.category', 'rule_position' => 'after',
            'rule_occurrence' => 'first', 'rule_occurrence_index' => 1, 'rule_sort_order' => 10,
            'rule_hook_path' => 'forums/front/index/forumRow', 'rule_hook_name' => 'rowItem',
            'rule_platforms' => '[]', 'rule_allow_groups' => '[]', 'rule_exclude_groups' => '[]',
            'rule_apps' => '[]', 'rule_include_urls' => '[]', 'rule_exclude_urls' => '[]',
        ], $overrides)];
    }
    fixture(['rule_platforms' => '["mobile"]']);
    Runtime::prepare();
    check(!\IPS\Output::$cacheable, 'Omitted UA variants must also disable page caching');
    check(Block::$renders === 0 && Runtime::payload() === '', 'Wrong platform must not render or serialize block');
    foreach ([
        ['Android', 'Mozilla/5.0 (Linux; Android 14; Pixel 8) Chrome/130.0 Mobile Safari/537.36', 'mobile'],
        ['Android', 'Mozilla/5.0 (Linux; Android 14; SM-X710) Chrome/130.0 Safari/537.36', 'tablet'],
        ['iPad', 'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) Mobile/15E148 Safari/604.1', 'tablet'],
        ['Macintosh', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Version/18.0 Safari/605.1.15', 'desktop'],
        [null, null, 'desktop'],
    ] as [$platform, $userAgent, $device]) {
        fixture(['rule_platforms' => json_encode([$device])]);
        \IPS\Session::i()->userAgent->platform = $platform;
        \IPS\Session::i()->userAgent->useragent = $userAgent;
        $context = Runtime::context();
        check($context['platform'] === $device && $context['native_platform'] === $platform, 'Runtime derives device class and retains native platform for diagnosis');
        Runtime::prepare();
        check(Block::$renders === 1 && str_contains(Runtime::payload(), 'secret'), 'Matching device receives prepared CSS block');
        check(!\IPS\Output::$cacheable, 'Matching device route must disable shared caching');
    }
    fixture(['rule_platforms' => '["mobile"]', 'rule_mode' => 'hook']);
    \IPS\Session::i()->userAgent->platform = 'iPad';
    \IPS\Session::i()->userAgent->useragent = 'Mozilla/5.0 (iPad; CPU OS 18_0) Mobile/15E148';
    check(Runtime::hook(1) === '' && Block::$renders === 0, 'Mobile-only hook never renders for tablet');
    check(!\IPS\Output::$cacheable, 'Nonmatching hook device also disables shared caching');
    foreach ([true, false] as $mobile) {
        fixture(['rule_platforms' => '["Android"]']);
        \IPS\Session::i()->userAgent->platform = 'Android';
        \IPS\Session::i()->userAgent->useragent = 'Mozilla/5.0 (Linux; Android 14) Chrome/130.0 ' . ($mobile ? 'Mobile ' : '') . 'Safari/537.36';
        Runtime::prepare();
        check(Block::$renders === 1, 'Legacy Android rule retains both phones and tablets');
    }
    fixture(['rule_exclude_groups' => '["4"]']);
    \IPS\Member::loggedIn()->groups = [2, 4];
    Runtime::prepare();
    check(Block::$renders === 0 && !str_contains(Runtime::payload(), 'secret'), 'Secondary excluded group must not receive HTML');
    foreach ([2, 4] as $guestGroup) {
        fixture(['rule_allow_groups' => '["4"]']);
        \IPS\Member::loggedIn()->member_id = 0;
        \IPS\Member::loggedIn()->groups = [$guestGroup];
        Runtime::prepare();
        check(!\IPS\Output::$cacheable, 'Both ordinary and alternate-group guest variants bypass shared caching before the audience check');
        check(Block::$renders === ($guestGroup === 4 ? 1 : 0), 'Guest group targeting still gates rendering independently of cache policy');
    }
    fixture(); Block::$allowed = false;
    Runtime::prepare();
    check(!\IPS\Output::$cacheable && Block::$renders === 0, 'A block with native view restrictions disables shared caching even without explicit rule filters');
    fixture(); Runtime::prepare();
    check(!\IPS\Output::$cacheable && Block::$renders === 1, 'The allowed native block-permission variant also disables shared caching');
    fixture(['rule_apps' => '["cms"]']);
    \IPS\Output::i()->httpHeaders = ['Cache-Control' => 'public, max-age=180'];
    Runtime::prepare();
    check(\IPS\Output::$cacheable && \IPS\Output::i()->httpHeaders === ['Cache-Control' => 'public, max-age=180'], 'An unmatched application route leaves the original cache policy intact');
    fixture(['rule_include_urls' => '["/private/*"]']); Runtime::prepare();
    check(\IPS\Output::$cacheable && \IPS\Output::i()->httpHeaders === [], 'An unmatched URL route does not change caching');
    fixture(['rule_enabled' => 0]); Runtime::prepare();
    check(\IPS\Output::$cacheable && \IPS\Output::i()->httpHeaders === [], 'A disabled rule does not change caching');
    fixture();
    \IPS\Output::i()->httpHeaders = [
        'cache-control' => 'public, max-age=43200', 'CACHE-CONTROL' => 'public, s-maxage=43200',
        'eXpIrEs' => 'future', 'pRaGmA' => 'cache', 'X-Preserved' => 'yes',
    ];
    Runtime::prepare();
    $headers = \IPS\Output::i()->httpHeaders;
    check(str_contains($headers['Cache-Control'], 'private') && str_contains($headers['Cache-Control'], 'no-store') && !str_contains($headers['Cache-Control'], 'public'), 'An eligible rule replaces prior public headers with private no-store headers');
    check(!isset($headers['cache-control'], $headers['CACHE-CONTROL'], $headers['eXpIrEs'], $headers['pRaGmA']) && $headers['Expires'] === 0 && $headers['Pragma'] === 'no-cache', 'Conflicting cache header casing is normalized without duplicate directives');
    check($headers['X-Preserved'] === 'yes', 'The cache guard preserves unrelated response headers');
    \IPS\Output::i()->httpHeaders += ['Cache-Control' => 'public, max-age=43200', 'Expires' => 'future'];
    check(\IPS\Output::i()->httpHeaders === $headers, 'Native controller headers added after an early hook cannot overwrite the cache guard');
    foreach ([1, -1] as $failures) {
        fixture(['rule_mode' => 'hook']);
        \IPS\Output::i()->httpHeaders = ['Cache-Control' => 'public, max-age=43200'];
        \IPS\Output::$noCacheFailures = $failures;
        check(Runtime::hook(1) === '' && Block::$renders === 0, 'A failed cache-header guard does not render the first hook');
        $second = Runtime::hook(1);
        if ($failures === 1) {
            check(str_contains($second, 'secret') && Block::$renders === 1, 'A later hook retries the cache guard and renders only after it succeeds');
            check(str_contains(\IPS\Output::i()->httpHeaders['Cache-Control'], 'no-store'), 'Recovered rendering has a no-store response policy');
        } else {
            check($second === '' && Block::$renders === 0, 'A persistent cache-header failure also blocks later hooks');
        }
    }
    fixture(); Block::$allowed = false;
    Runtime::prepare();
    check(Block::$renders === 0 && Runtime::payload() === '', 'Native block permissions must gate HTML');
    fixture(); Block::$exists = false;
    check(Runtime::payload() === '', 'Deleted block is safely omitted');
    fixture(['rule_allow_groups' => '["4"]']); \IPS\Member::loggedIn()->groups = [2, 4];
    Runtime::prepare(); $payload = Runtime::payload();
    check(Block::$renders === 1 && str_contains($payload, 'secret'), 'Secondary allowed group can see block');
    check(str_contains($payload, 'Translated') && str_contains($payload, 'https:\/\/cdn.example'), 'Language and file tokens resolved before JSON');
    check(!str_contains($payload, '<span>'), 'HTML must be inert and JSON hex escaped');
    check(Runtime::payload() === '', 'Only one payload per response');
    fixture(['rule_mode' => 'hook', 'rule_occurrence' => 'nth', 'rule_occurrence_index' => 2]);
    check(Runtime::hook(1) === '', 'First hook occurrence skipped for nth=2');
    $html = Runtime::hook(1);
    check(str_starts_with($html, '<li ') && str_contains($html, 'secret'), 'Second native hook uses list-safe wrapper');
    check(Runtime::hook(1) === '' && Block::$renders === 1, 'Native hook nth is request-local');
    foreach (['inside-start', 'inside-end'] as $position) {
        fixture(['rule_mode' => 'hook', 'rule_hook_name' => 'body', 'rule_position' => $position]);
        check(str_starts_with(Runtime::hook(1), '<div ') && Block::$renders === 1, 'Body child positions use a flow wrapper: ' . $position);
    }
    foreach (['before', 'after'] as $position) {
        fixture(['rule_mode' => 'hook', 'rule_hook_name' => 'body', 'rule_position' => $position]);
        check(Runtime::hook(1) === '' && Block::$renders === 0 && Block::$loads === 0, 'Unsafe body positions are rejected before rendering: ' . $position);
        check(Runtime::diagnose(\IPS\rqblockplacements\Rule::all()[0])['status'] === 'hook_context_invalid', 'Unsafe hook position has a clear diagnostic reason');
    }
    foreach (['inside-start', 'inside-end'] as $position) {
        fixture(['rule_position' => $position]);
        $insidePayload = Runtime::payload();
        preg_match('~<script type="application/json" data-rqbp-payload>(.*?)</script>~s', $insidePayload, $insideMatch);
        $insideData = json_decode($insideMatch[1] ?? '', true, 512, JSON_THROW_ON_ERROR);
        check($insideData['rules'][0]['position'] === $position, 'CSS payload retains the exact native insertion position');
    }
    foreach (['inside-start', 'inside-end'] as $position) {
        fixture(['rule_mode' => 'hook', 'rule_hook_name' => 'head', 'rule_position' => $position]);
        Block::$html = '<meta name="example" content="<lang>"><link rel="stylesheet" href="<fileStore.test>/example.css"><script>window.example="<div></head>";</script>';
        $metadata = Runtime::hook(1);
        check($metadata === '<meta name="example" content="Translated"><link rel="stylesheet" href="https://cdn.example/example.css"><script>window.example="<div></head>";</script>', 'Native head output is finalized and emitted unchanged without a div wrapper');
        check(!str_contains($metadata, 'data-rqbp-placement') && Block::$renders === 1, 'Head metadata uses the native rendering path and its request-local cache');
    }
    foreach (['before', 'after'] as $position) {
        fixture(['rule_mode' => 'hook', 'rule_hook_name' => 'head', 'rule_position' => $position]);
        check(Runtime::hook(1) === '' && Block::$renders === 0, 'Outer head positions cannot render content');
    }
    fixture(['rule_mode' => 'hook', 'rule_hook_name' => 'head', 'rule_position' => 'inside-end']);
    Block::$html = '<script>window.example=true;</script><div>Body banner</div>';
    \IPS\Request::i()->rqbp_diagnostics = true;
    \IPS\Member::loggedIn()->member_id = 1;
    check(Runtime::hook(1) === '', 'A mixed metadata/body fragment is rejected as a whole, with no partial script output');
    $headDebug = Runtime::payload();
    preg_match('~<script type="application/json" data-rqbp-payload>(.*?)</script>~s', $headDebug, $headDebugMatch);
    $headDiagnostics = json_decode($headDebugMatch[1] ?? '', true, 512, JSON_THROW_ON_ERROR);
    check($headDiagnostics['diagnostics'][0]['status'] === 'head_content_invalid'
        && $headDiagnostics['diagnostics'][0]['reason'] === 'Only script, style, meta and link are allowed.', 'Invalid head HTML has a localized administrator diagnostic');
    fixture(['rule_mode' => 'hook', 'rule_hook_name' => 'head', 'rule_position' => 'inside-start']);
    Block::$allowed = false;
    check(Runtime::hook(1) === '' && Block::$renders === 0, 'Pages block permissions still gate head rendering');
    fixture(['rule_mode' => 'hook', 'rule_hook_name' => 'head', 'rule_position' => 'inside-start', 'rule_content_type' => 'template', 'rule_block_id' => 0, 'rule_template_code' => '<script>window.example=true;</script>']);
    check(Runtime::hook(1) === '<script>window.example=true;</script>' && \IPS\Theme::$executions === 1 && Block::$loads === 0, 'Custom template head markup uses the same metadata guard without Pages block access');
    fixture(['rule_mode' => 'hook', 'rule_hook_name' => 'head', 'rule_position' => 'inside-start', 'rule_content_type' => 'template', 'rule_block_id' => 0, 'rule_template_code' => '<script>window.example=true;</script>', 'rule_allow_groups' => '["99"]']);
    check(Runtime::hook(1) === '' && \IPS\Theme::$executions === 0, 'Disallowed audiences cannot execute a head template');
    fixture(['rule_mode' => 'hook']);
    $nested = null; Block::$callback = static function () use (&$nested) { $nested = Runtime::hook(1); };
    check(str_contains(Runtime::hook(1), 'secret') && $nested === '' && Block::$renders === 1, 'Recursive block cannot recursively invoke its rule');
    fixture(['rule_mode' => 'hook', 'rule_hook_name' => 'missing']);
    check(Runtime::hook(1) === '' && Block::$renders === 0, 'Removed hook safely omits block');
    fixture(['rule_include_urls' => '["/forums/?page=2"]']);
    \IPS\Request::i()->rqbp_diagnostics = true;
    \IPS\Request::i()->currentUrl .= '&rqbp_diagnostics=1&rqbp_rule=1';
    \IPS\Member::loggedIn()->member_id = 1;
    $debugPayload = Runtime::payload();
    preg_match('~<script type="application/json" data-rqbp-payload>(.*?)</script>~s', $debugPayload, $matched);
    $decoded = json_decode($matched[1] ?? '', true, 512, JSON_THROW_ON_ERROR);
    check(count($decoded['rules']) === 1, 'Diagnostic control parameters do not alter rule URL eligibility');
    check(str_contains($decoded['strings']['diagnostics'], '"quotes" </script>'), 'Translated labels finalized before JSON escaping');
    check(!str_contains($matched[1], '</script>'), 'Localized labels cannot close JSON script element');
    check(\IPS\Log::$errors === [], 'No swallowed runtime errors in successful block boundary tests');

    $example = <<<'IPS'
{expression="(new \IPS\ExampleContent())->render('example')" raw="true"}
IPS;
    $templateRule = ['rule_content_type' => 'template', 'rule_template_code' => $example];
    fixture($templateRule);
    Runtime::prepare();
    $templatePayload = Runtime::payload();
    check(\IPS\ExampleContent::$calls === 1 && \IPS\Theme::$executions === 1, 'The generic content expression executes once during the original frontend request');
    check(Block::$loads === 0 && Block::$renders === 0 && str_contains($templatePayload, 'Content example'), 'Template content never loads or checks a Pages block');
    check(!\IPS\Output::$cacheable, 'Arbitrary template output disables shared page caching even without device filters');
    check(!str_contains($templatePayload, '<strong>'), 'Template output is inert and JSON hex escaped in CSS mode');
    check(!str_contains($templatePayload, 'ExampleContent') && !str_contains($templatePayload, 'template_code'), 'CSS payload contains rendered HTML without exposing its server template source');
    check(Runtime::payload() === '' && \IPS\ExampleContent::$calls === 1, 'Repeated payload access does not reexecute the template');

    foreach ([
        ['rule_platforms' => '["mobile"]'],
        ['rule_allow_groups' => '["4"]'],
        ['rule_exclude_groups' => '["2"]'],
        ['rule_include_urls' => '["/private/*"]'],
        ['rule_exclude_urls' => '["/forums/*"]'],
        ['rule_apps' => '["cms"]'],
        ['rule_enabled' => 0],
    ] as $filter) {
        fixture(array_replace($templateRule, $filter));
        Runtime::prepare();
        check(\IPS\Theme::$compiles === 0 && \IPS\ExampleContent::$calls === 0 && Runtime::payload() === '', 'Audience and route restrictions run before template compilation and execution');
    }
    fixture(array_replace($templateRule, ['rule_allow_groups' => '["4"]']));
    Runtime::prepare();
    check(!\IPS\Output::$cacheable && \IPS\Theme::$executions === 0, 'A denied audience variant of a template route also disables shared caching');
    fixture($templateRule);
    \IPS\Application::$pages = false;
    check(str_contains(Runtime::payload(), 'Content example') && \IPS\ExampleContent::$calls === 1 && Block::$loads === 0, 'Standalone templates render with Pages disabled');
    fixture();
    \IPS\Application::$pages = false;
    check(Runtime::payload() === '' && Block::$loads === 0, 'Pages block content fails closed before loading when Pages is disabled');

    fixture(array_replace($templateRule, ['rule_mode' => 'hook', 'rule_occurrence' => 'all']));
    $nested = null;
    \IPS\Theme::$callback = static function () use (&$nested) { $nested = Runtime::hook(1); };
    check(str_contains(Runtime::hook(1), 'Content example') && $nested === '', 'Templates cannot recursively invoke their own hook');
    check(str_contains(Runtime::hook(1), 'Content example') && \IPS\ExampleContent::$calls === 1, 'All hook occurrences reuse request-local template output without content reexecution');
    fixture($templateRule);
    $model = \IPS\rqblockplacements\Rule::all()[0];
    check(Runtime::diagnose($model)['status'] === 'eligible' && \IPS\Theme::$compiles === 0 && \IPS\ExampleContent::$calls === 0, 'ACP diagnostics check eligibility without executing submitted template code');
    check(\IPS\Log::$errors === [], 'Successful template boundary tests do not log runtime errors');

    foreach ([
        ['rule_content_type' => 'not-a-source', 'content_type_invalid'],
        ['rule_content_type' => 'block', 'pages_missing'],
        ['rule_template_code' => '   ', 'template_empty'],
        ['rule_template_code' => '__EMPTY__', 'template_empty'],
        ['rule_template_code' => '__INVALID__', 'template_error'],
        ['rule_template_code' => '__THROW_AFTER_ECHO__', 'template_error'],
    ] as $case) {
        $expected = array_pop($case);
        fixture(array_replace($templateRule, $case));
        if ($expected === 'pages_missing') { \IPS\Application::$pages = false; }
        \IPS\Request::i()->rqbp_diagnostics = true;
        \IPS\Member::loggedIn()->member_id = 1;
        $level = ob_get_level();
        ob_start();
        $result = Runtime::payload();
        $unexpected = ob_get_clean();
        check(ob_get_level() === $level && $unexpected === '' && !str_contains($result, 'SECRET'), 'Failed templates discard all partial output and restore the caller buffer');
        preg_match('~<script type="application/json" data-rqbp-payload>(.*?)</script>~s', $result, $match);
        $debug = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        check($debug['diagnostics'][0]['status'] === $expected, 'Template/source failure has the expected safe administrator status');
        check(!array_filter($debug['rules'], static fn ($row) => $row['html'] !== ''), 'Failed templates never serialize partial HTML');
        check(count(\IPS\Log::$errors) === ($expected === 'template_error' ? 1 : 0), 'Template exceptions are logged once and ordinary omissions are not errors');
    }
    fixture(['rule_mode' => 'server', 'rule_selector' => '#ipsLayout__main > .category']);
    Runtime::prepare();
    check(Block::$renders === 0 && Block::$loads === 0, 'Preparing assets does not eagerly execute a server-only placement');
    (new \IPS\rqblockplacements\extensions\core\Loader\Placements)->onFinish();
    $serverHtml = \IPS\Output::i()->output;
    check(str_contains($serverHtml, 'data-rqbp-placement="1"') && str_contains($serverHtml, 'secret Translated https://cdn.example'), 'The Loader inserts finalized block HTML into the controller output before global-template rendering');
    check(!str_contains($serverHtml, 'data-rqbp-payload') && Runtime::payload() === '', 'Server placements do not create a browser insertion payload');
    check(Block::$renders === 1 && !\IPS\Output::$cacheable, 'Server placement rendering is cached only for this request and bypasses shared guest caching');
    (new \IPS\rqblockplacements\extensions\core\Loader\Placements)->onFinish();
    check(\IPS\Output::i()->output === $serverHtml && Block::$renders === 1, 'Repeated Loader calls cannot duplicate server output or run content again');

    foreach ([
        ['rule_allow_groups' => '["99"]'], ['rule_exclude_groups' => '["2"]'],
        ['rule_platforms' => '["mobile"]'], ['rule_apps' => '["cms"]'],
        ['rule_include_urls' => '["/other/*"]'], ['rule_exclude_urls' => '["/forums/*"]'],
        ['rule_enabled' => 0], ['rule_selector' => '.missing'], ['rule_selector' => '.category:has(p)'],
        ['rule_occurrence' => 'nth', 'rule_occurrence_index' => 2],
    ] as $restrictions) {
        fixture(array_replace(['rule_mode' => 'server'], $restrictions));
        $original = \IPS\Output::i()->output;
        Runtime::server();
        check(\IPS\Output::i()->output === $original && Block::$loads === 0 && Block::$renders === 0, 'Ineligible, absent, unsupported or unselected server targets cannot load or execute a block');
    }
    foreach ([false, true] as $allowed) {
        fixture(['rule_mode' => 'server']);
        Block::$allowed = $allowed;
        Runtime::server();
        check(!\IPS\Output::$cacheable && Block::$renders === (int) $allowed, 'Server block view permissions still run after route-level cache protection for both visitor variants');
    }
    fixture(['rule_mode' => 'server', 'rule_content_type' => 'template', 'rule_template_code' => '<b>Server template</b><script>window.serverExample=true;</script>']);
    \IPS\Application::$pages = false;
    Runtime::server();
    check(str_contains(\IPS\Output::i()->output, 'Server template') && \IPS\Theme::$executions === 1 && Block::$loads === 0, 'Server template content uses code permissions and eligibility without requiring Pages APIs');
    fixture(['rule_mode' => 'server', 'rule_content_type' => 'template', 'rule_template_code' => '<b>Never executed</b>', 'rule_allow_groups' => '["99"]']);
    Runtime::server();
    check(\IPS\Theme::$executions === 0, 'Denied audiences never execute server template expressions');

    foreach (['ajax', 'missing-loader', 'bad-markup', 'bad-selector'] as $case) {
        fixture(['rule_mode' => 'server']);
        \IPS\Request::i()->rqbp_diagnostics = true;
        \IPS\Member::loggedIn()->member_id = 1;
        if ($case === 'ajax') { \IPS\Request::i()->ajax = true; }
        if ($case === 'bad-markup') { \IPS\Output::i()->output = '<section class="category">'; }
        if ($case === 'bad-selector') { \IPS\Db::$rows[0]['rule_selector'] = '.category:hover'; }
        $original = \IPS\Output::i()->output;
        if ($case !== 'missing-loader') { Runtime::server(); }
        $debugPayload = Runtime::payload();
        preg_match('~<script type="application/json" data-rqbp-payload>(.*?)</script>~s', $debugPayload, $match);
        $data = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        $expected = match ($case) { 'ajax' => 'server_ajax_unsupported', 'missing-loader' => 'server_not_processed', 'bad-markup' => 'server_html_unsupported', default => 'server_selector_invalid' };
        check($data['diagnostics'][0]['status'] === $expected && str_contains($data['diagnostics'][0]['reason'], 'rqbp_' . $expected), 'Server omissions expose a localized administrator diagnostic without a JavaScript fallback');
        check($data['rules'] === [] && \IPS\Output::i()->output === $original && Block::$renders === 0, 'Unsupported server responses keep their original HTML and never execute deferred content');
    }
    fixture(['rule_mode' => 'server']);
    $secondRule = \IPS\Db::$rows[0];
    $secondRule['rule_id'] = 2;
    $secondRule['rule_mode'] = 'css';
    \IPS\Db::$rows[] = $secondRule;
    (new \IPS\rqblockplacements\extensions\core\Loader\Placements)->onFinish();
    $mixedPayload = Runtime::payload();
    preg_match('~<script type="application/json" data-rqbp-payload>(.*?)</script>~s', $mixedPayload, $match);
    $mixed = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    check(array_column($mixed['rules'], 'id') === [2] && str_contains(\IPS\Output::i()->output, 'data-rqbp-placement="1"'), 'Server and legacy JavaScript rules coexist without serializing or duplicating the server rule');
    fixture(['rule_mode' => 'server']); \IPS\Settings::i()->rqbp_enabled = false;
    $original = \IPS\Output::i()->output;
    Runtime::server();
    check(\IPS\Output::i()->output === $original && Block::$loads === 0, 'The global switch also disables server insertion');
    fixture(); \IPS\Settings::i()->rqbp_enabled = false;
    check(Runtime::hook(1) === '' && Runtime::payload() === '', 'Global switch gates all output');
    check(\IPS\Log::$errors === [], 'No swallowed runtime errors in successful boundary tests');
    echo "Runtime boundary tests: {$checks} passed\n";
}
