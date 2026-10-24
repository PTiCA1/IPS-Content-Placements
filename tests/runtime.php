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
    class Application { public static function appIsEnabled($key): bool { return true; } }
    class Dispatcher { public string $controllerLocation = 'front'; public object $application; public function __construct() { $this->application = (object) ['directory' => 'forums']; } public static function hasInstance(): bool { return true; } public static function i(): self { static $s; return $s ??= new self; } }
    class Settings { public bool $rqbp_enabled = true; public static function i(): self { static $s; return $s ??= new self; } }
    class Session { public object $userAgent; public function __construct() { $this->userAgent = (object) ['platform' => 'Windows']; } public static function i(): self { static $s; return $s ??= new self; } }
    class Request { public bool $rqbp_diagnostics = false; public string $currentUrl = 'https://forum.example/community/forums/?page=2'; public static function i(): self { static $s; return $s ??= new self; } public function url(): string { return $this->currentUrl; } }
    class Member { public int $member_id = 0; public array $groups = [2]; public static function loggedIn(): self { static $s; return $s ??= new self; } public function language(): object { return new class {
        public function parseOutputForDisplay(&$html): void { $html = str_replace('<lang>', 'Translated', $html); $html = preg_replace('/__rqbp_front_[a-z]+__/', "Label with \"quotes\" </script>\nnext", $html); }
        public function addToStack($key): string { return '__' . $key . '__'; }
        public function stripVLETags($html): string { return $html; }
    }; } public function hasAcpRestriction(...$args): bool { return true; } }
    class Output { public static bool $cacheable = true; public static function setCacheTime($value = false): void { self::$cacheable = $value; } public static function i(): self { static $s; return $s ??= new self; } public function parseFileObjectUrls(&$html): void { $html = str_replace('<fileStore.test>', 'https://cdn.example', $html); } }
    class Log { public static array $errors = []; public static function log($error, $key): void { self::$errors[] = $error; } }
    class Db { public static array $rows = []; public static function i(): self { static $s; return $s ??= new self; } public function select(...$args): array { $rows = self::$rows; usort($rows, static fn($a, $b) => [$a['rule_sort_order'] ?? 0, $a['rule_id']] <=> [$b['rule_sort_order'] ?? 0, $b['rule_id']]); return $rows; } }
}
namespace IPS\Http { class Url { public static function baseUrl(): string { return 'https://forum.example/community/'; } } }
namespace IPS\cms\Blocks {
    class Block {
        public bool $active = true; public static bool $allowed = true; public static bool $exists = true; public static int $renders = 0; public static $callback = null;
        public static function load($id): self { if (!self::$exists) { throw new \OutOfRangeException; } return new self; }
        public function can($action): bool { return self::$allowed; }
        public static function display($block): string { self::$renders++; if (self::$callback) { (self::$callback)(); } return '<span>secret <lang> <fileStore.test></span>'; }
    }
}
namespace IPS\rqblockplacements {
    class HookCatalog { public static function has($path, $name): bool { return $name !== 'missing'; } public static function wrapper($path, $name): string { return 'li'; } }
}
namespace {
    require __DIR__ . '/../application/sources/Policy/Policy.php';
    require __DIR__ . '/../application/sources/Rule/Rule.php';
    require __DIR__ . '/../application/Application.php';
    require __DIR__ . '/../application/sources/Runtime/Runtime.php';
    use IPS\rqblockplacements\Runtime;
    use IPS\cms\Blocks\Block;
    $checks = 0;
    function check(bool $condition, string $message): void { global $checks; $checks++; if (!$condition) { throw new \RuntimeException($message); } }
    function fixture(array $overrides = []): void {
        Runtime::reset();
        Block::$renders = 0; Block::$allowed = Block::$exists = true; Block::$callback = null;
        \IPS\Output::$cacheable = true;
        \IPS\Session::i()->userAgent->platform = 'Windows';
        \IPS\Member::loggedIn()->groups = [2];
        \IPS\Member::loggedIn()->member_id = 0;
        \IPS\Settings::i()->rqbp_enabled = true;
        \IPS\Request::i()->rqbp_diagnostics = false;
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
    fixture(['rule_platforms' => '["Android"]']);
    Runtime::prepare();
    check(!\IPS\Output::$cacheable, 'Omitted UA variants must also disable page caching');
    check(Block::$renders === 0 && Runtime::payload() === '', 'Wrong platform must not render or serialize block');
    fixture(['rule_exclude_groups' => '["4"]']);
    \IPS\Member::loggedIn()->groups = [2, 4];
    Runtime::prepare();
    check(Block::$renders === 0 && !str_contains(Runtime::payload(), 'secret'), 'Secondary excluded group must not receive HTML');
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
    fixture(); \IPS\Settings::i()->rqbp_enabled = false;
    check(Runtime::hook(1) === '' && Runtime::payload() === '', 'Global switch gates all output');
    check(\IPS\Log::$errors === [], 'No swallowed runtime errors in successful boundary tests');
    echo "Runtime boundary tests: {$checks} passed\n";
}
