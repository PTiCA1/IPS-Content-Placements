<?php
/** ACP integration-boundary regression. Original test doubles, no platform source bundled. */
namespace { define('IPS\SUITE_UNIQUE_KEY', 'acp-context-test'); }

namespace IPS\Dispatcher {
    class Controller {
        public function execute(): void {
            $method = \IPS\Request::i()->do ?? 'manage';
            $this->$method();
        }
    }
}

namespace IPS {
    class Application {
        public static bool $pages = true;
        public static function appIsEnabled(string $key): bool { return $key !== 'cms' || self::$pages; }
        public static function applications(): array { return [(object) ['directory' => 'forums', '_title' => 'Forums']]; }
    }
    class Dispatcher {
        public string $controllerLocation = 'admin';
        public object $application;
        public array $permissions = [];
        public function __construct() { $this->application = (object) ['directory' => 'rqblockplacements']; }
        public static function i(): self { static $instance; return $instance ??= new self; }
        public function checkAcpPermission(string $permission): void { $this->permissions[] = $permission; }
    }
    #[\AllowDynamicProperties]
    class Request {
        public static function i(): self { static $instance; return $instance ??= new self; }
        public function url(): \IPS\Http\Url { return \IPS\Http\Url::internal('app=rqblockplacements&module=placements&controller=rules'); }
    }
    class Settings {
        public bool $rqbp_enabled = true;
        public string $base_url = 'https://board.example/community/';
        public static function i(): self { static $instance; return $instance ??= new self; }
    }
    class Member {
        public static function loggedIn(): self { static $instance; return $instance ??= new self; }
        public function language(): object { return new class {
            public function addToStack(string $key, ...$args): string {
                return match ($key) {
                    'rqbp_platform_mobile' => 'Translated mobile',
                    'rqbp_platform_tablet' => 'Translated tablet',
                    'rqbp_platform_desktop' => 'Translated desktop',
                    default => $key,
                };
            }
            public function parseOutputForDisplay(&$value): void {}
            public function stripVLETags($value): string { return $value; }
        }; }
    }
    class Output {
        public string $title = '';
        public string $output = '';
        public array $sidebar = [];
        public array $jsFiles = [];
        public static function i(): self { static $instance; return $instance ??= new self; }
        public function error(...$args): never { throw new \RuntimeException('Unexpected ACP error: ' . $args[0]); }
    }
    class Theme {
        public static array $calls = [];
        public static function i(): self { static $instance; return $instance ??= new self; }
        public function getTemplate(string $group, ?string $app = null, ?string $location = null): object {
            self::$calls[] = [$group, $app, $location];
            // IPS defaults a missing app to Dispatcher::application, i.e. our app here.
            if ($app !== 'core' || ($group === 'global' && $location !== 'admin')) {
                throw new \RuntimeException('template_store_missing: ' . ($app ?? Dispatcher::i()->application->directory));
            }
            return new class {
                public function message(string $message, string $type): string {
                    return '<p data-core-message="' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '"></p>';
                }
            };
        }
    }
}

namespace IPS\Http {
    class Url {
        public array $data;
        public function __construct(private string $url) { $this->data = parse_url($url); }
        public static function baseUrl(): string { return 'https://board.example/community/'; }
        public static function internal(string $query): self { return new self('https://board.example/private-acp/index.php?' . $query); }
        public static function external(string $url): self { return new self($url); }
        public function setQueryString(array|string $key, mixed $value = null): self {
            $copy = clone $this;
            $params = [];
            parse_str(parse_url($copy->url, PHP_URL_QUERY) ?: '', $params);
            $params = array_replace($params, is_array($key) ? $key : [$key => $value]);
            $copy->url = explode('?', $copy->url, 2)[0] . '?' . http_build_query($params);
            $copy->data = parse_url($copy->url);
            return $copy;
        }
        public function csrf(): self { return $this; }
        public function __toString(): string { return $this->url; }
    }
}

namespace IPS\Member { class Group { public static function groups(): array { return [2 => 'Guests', 4 => 'Members']; } } }

namespace IPS\cms\Blocks {
    class Container {}
    class Block {
        public int $id = 9;
        public string $key = 'test_banner';
        public string $_title = 'Test <banner>';
        public bool $active = true;
        public static function load($id, $field = null): self {
            if (!$id) { throw new \OutOfRangeException; }
            return new self;
        }
        public function can(string $permission): bool { return true; }
    }
}

namespace IPS\Helpers {
    class Form {
        public static ?self $last = null;
        public array $fields = [];
        public function __construct(public string $id = 'form', ...$args) {
            self::$last = $this;
            \IPS\Theme::i()->getTemplate('forms', 'core', 'global');
        }
        public function addMessage(...$args): void { \IPS\Theme::i()->getTemplate('forms', 'core', 'global'); }
        public function addHeader(...$args): void { \IPS\Theme::i()->getTemplate('forms', 'core', 'admin'); }
        public function add($field): void { $this->fields[$field->name] = $field; }
        public function values(): false { return false; }
        public function __toString(): string {
            \IPS\Theme::i()->getTemplate('forms', 'core', 'admin');
            return '<form data-native-form="' . $this->id . '"></form>';
        }
    }
}

namespace IPS\Helpers\Form {
    class Field {
        public function __construct(
            public string $name,
            public mixed $value = null,
            public bool $required = false,
            public array $options = [],
            ...$args
        ) {}
    }
    class Text extends Field {} class TextArea extends Field {} class Number extends Field {}
    class Radio extends Field {} class Select extends Field {} class CheckboxSet extends Field {}
    class Node extends Field {} class YesNo extends Field {} class Url extends Field {}
}

namespace IPS\Helpers\Table {
    #[\AllowDynamicProperties]
    class Db {
        public ?string $sortBy = null;
        public ?string $sortDirection = null;
        public function __construct(...$args) { \IPS\Theme::i()->getTemplate('tables', 'core', 'admin'); }
        public function __toString(): string { return '<table data-native-table></table>'; }
    }
}

namespace IPS\rqblockplacements {
    class Rule {
        public int $id = 1;
        public string $name = 'Test rule';
        public int $enabled = 1;
        public int $block_id = 9;
        public string $mode = 'hook';
        public string $hook_path = 'forums/front/index/forumRow';
        public string $hook_name = 'rowItem';
        public string $selector = '.category';
        public int $sort_order = 10;
        public string $position = 'before';
        public string $occurrence = 'nth';
        public int $occurrence_index = 2;
        public array $platforms = ['Android', 'Windows'];
        public array $allow_groups = ['4'];
        public array $exclude_groups = ['2'];
        public array $apps = ['forums'];
        public array $include_urls = ['/forums/*'];
        public array $exclude_urls = ['/forums/private/*'];
        public static function all(): array { return []; }
        public static function load($id): self { return new self; }
        public function toConfig(): array { return Policy::normalize(get_object_vars($this)); }
    }
    class HookCatalog {
        public static function options(): array { return ['forums/front/index/forumRow' => ['rowItem' => 'rowItem (li)']]; }
        public static function has(...$args): bool { return true; }
        public static function wrapper(...$args): string { return 'li'; }
    }
}

namespace {
    require __DIR__ . '/../application/sources/Policy/Policy.php';
    require __DIR__ . '/../application/Application.php';
    require __DIR__ . '/../application/modules/admin/placements/rules.php';
    require __DIR__ . '/../application/modules/admin/placements/settings.php';

    $checks = 0;
    function check(bool $condition, string $message): void {
        global $checks;
        $checks++;
        if (!$condition) { throw new \RuntimeException($message); }
    }
    function runAcp(string $controller, string $action = 'manage', ?int $id = null): string {
        $request = \IPS\Request::i();
        $request->do = $action;
        if ($id === null) { unset($request->id); } else { $request->id = $id; }
        \IPS\Output::i()->output = '';
        $class = 'IPS\\rqblockplacements\\modules\\admin\\placements\\' . $controller;
        (new $class)->execute();
        return \IPS\Output::i()->output;
    }

    $guardRejectedImplicitContext = false;
    try { \IPS\Theme::i()->getTemplate('global'); }
    catch (\RuntimeException) { $guardRejectedImplicitContext = true; }
    check($guardRejectedImplicitContext, 'The regression guard must reproduce the missing-template context');
    \IPS\Theme::$calls = [];

    $html = runAcp('rules');
    check(str_contains($html, 'data-native-table') && str_contains($html, 'rqbp_rules_intro'), 'The rule list renders its core notice and table');
    \IPS\Application::$pages = false;
    check(str_contains(runAcp('rules', 'form'), 'rqbp_pages_required'), 'Missing Pages renders a core admin warning');
    \IPS\Application::$pages = true;
    check(str_contains(runAcp('rules', 'form'), 'rqbp_rule_form'), 'The add-rule form renders in our application context');
    $fields = \IPS\Helpers\Form::$last->fields;
    check(isset($fields['rqbp_block_picker'], $fields['rqbp_block_reference'], $fields['rqbp_hook'], $fields['rqbp_selector']), 'The native form contains both block sources and placement modes');
    check($fields['rqbp_rule_enabled']->value === false, 'New rules remain disabled by default');
    $deviceOptions = [
        'mobile' => 'rqbp_platform_mobile',
        'tablet' => 'rqbp_platform_tablet',
        'desktop' => 'rqbp_platform_desktop',
    ];
    $deviceLabels = ['mobile' => 'Translated mobile', 'tablet' => 'Translated tablet', 'desktop' => 'Translated desktop'];
    check(\IPS\rqblockplacements\Policy::platformOptions() === $deviceOptions, 'Policy exposes exactly the three device choices and their language keys');
    check(array_keys($fields['rqbp_platforms']->options['options']) === array_keys($deviceOptions), 'The new-rule form exposes mobile, tablet and desktop without OS or unknown options');
    check($fields['rqbp_platforms']->options['options'] === $deviceLabels, 'Each device choice uses its translated label through the ACP language boundary');
    check($fields['rqbp_platforms']->value === [], 'An empty device selection defaults to all devices');
    check(str_contains(runAcp('rules', 'form', 1), 'rqbp_rule_form'), 'The existing-rule form renders');
    $existingFields = \IPS\Helpers\Form::$last->fields;
    check($existingFields['rqbp_platforms']->value === ['mobile', 'tablet', 'desktop'], 'Legacy Android and Windows selections preselect the three corresponding device classes');
    check($existingFields['rqbp_platforms']->options['options'] === $deviceLabels, 'Editing an existing rule also translates labels and excludes legacy OS and unknown choices');
    $preserved = [
        'rqbp_name' => 'Test rule',
        'rqbp_rule_enabled' => true,
        'rqbp_sort_order' => 10,
        'rqbp_mode' => 'hook',
        'rqbp_hook' => 'forums/front/index/forumRow|rowItem',
        'rqbp_selector' => '.category',
        'rqbp_position' => 'before',
        'rqbp_occurrence' => 'nth',
        'rqbp_occurrence_index' => 2,
        'rqbp_allow_groups' => ['4'],
        'rqbp_exclude_groups' => ['2'],
        'rqbp_apps' => ['forums'],
        'rqbp_include_urls' => '/forums/*',
        'rqbp_exclude_urls' => '/forums/private/*',
    ];
    foreach ($preserved as $name => $value) {
        check($existingFields[$name]->value === $value, "Device normalization preserves {$name}");
    }
    check($existingFields['rqbp_block_picker']->value instanceof \IPS\cms\Blocks\Block && $existingFields['rqbp_block_picker']->value->id === 9, 'Device normalization preserves the selected Pages block');
    check(str_contains(runAcp('rules', 'diagnostics', 1), 'rqbp_diag_scope'), 'Diagnostics renders its core scope notice');
    check(str_contains(runAcp('settings'), 'rqbp_settings_form'), 'The settings form renders');
    check(count(\IPS\Dispatcher::i()->permissions) === 6 && array_unique(\IPS\Dispatcher::i()->permissions) === ['rqbp_manage'], 'Every ACP route checks its management permission');
    foreach (\IPS\Theme::$calls as [$group, $app, $location]) {
        check($app === 'core' && ($group !== 'global' || $location === 'admin'), 'Every shared template uses its explicit core context');
    }
    echo "ACP context boundary tests: {$checks} passed\n";
}
