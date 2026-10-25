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
    class AcpPermissionDenied extends \RuntimeException {}
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
        public function checkAcpPermission(string $permission, ?string $app = null, ?string $module = null): void {
            $this->permissions[] = $permission;
            if ($permission === 'rqbp_manage_code' && [$app, $module] !== ['rqblockplacements', 'placements']) {
                throw new \RuntimeException('Code permission must use the explicit application and module');
            }
            if ($permission === 'rqbp_manage_code' && !Member::$codePermission) {
                throw new AcpPermissionDenied('403: rqbp_manage_code');
            }
        }
    }
    #[\AllowDynamicProperties]
    class Request {
        public static function i(): self { static $instance; return $instance ??= new self; }
        public function url(): \IPS\Http\Url { return \IPS\Http\Url::internal('app=rqblockplacements&module=placements&controller=rules'); }
        public function confirmedDelete(): void { Session::i()->csrfCheck(); }
    }
    class Settings {
        public bool $rqbp_enabled = true;
        public string $base_url = 'https://board.example/community/';
        public array $writes = [];
        public static function i(): self { static $instance; return $instance ??= new self; }
        public function changeValues(array $values): void {
            $this->writes[] = $values;
            foreach ($values as $key => $value) { $this->$key = $value; }
        }
    }
    class Member {
        public static bool $codePermission = false;
        public static function loggedIn(): self { static $instance; return $instance ??= new self; }
        public function hasAcpRestriction(string $app, string $module, string $key): bool {
            if ([$app, $module, $key] !== ['rqblockplacements', 'placements', 'rqbp_manage_code']) {
                throw new \RuntimeException('Unexpected code permission context');
            }
            return self::$codePermission;
        }
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
        public function redirect(...$args): void {}
    }
    class Session {
        public static function i(): self { static $instance; return $instance ??= new self; }
        public function csrfCheck(): void {}
        public function log(...$args): void {}
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
        public static int $loads = 0;
        public int $id = 9;
        public string $key = 'test_banner';
        public string $_title = 'Test <banner>';
        public bool $active = true;
        public static function load($id, $field = null): self {
            self::$loads++;
            if (!\IPS\Application::$pages) { throw new \RuntimeException('Pages APIs accessed while unavailable'); }
            if (!$id) { throw new \OutOfRangeException; }
            return new self;
        }
        public function can(string $permission): bool { return true; }
    }
}

namespace IPS\Helpers {
    class Form {
        public static ?self $last = null;
        public static array|false $submitted = false;
        public ?string $error = null;
        public array $fields = [];
        public array $attributes = [];
        public function __construct(public string $id = 'form', ...$args) {
            $this->attributes = $args[2] ?? [];
            self::$last = $this;
            \IPS\Theme::i()->getTemplate('forms', 'core', 'global');
        }
        public function addMessage(...$args): void { \IPS\Theme::i()->getTemplate('forms', 'core', 'global'); }
        public function addHeader(...$args): void { \IPS\Theme::i()->getTemplate('forms', 'core', 'admin'); }
        public function add($field): void { $this->fields[$field->name] = $field; }
        public function values(): array|false {
            if (self::$submitted === false) { return false; }
            $submitted = self::$submitted;
            self::$submitted = false;
            $values = [];
            foreach ($this->fields as $field) {
                $value = $submitted[$field->name] ?? $field->value;
                try {
                    if ($field->validation) { ($field->validation)($value); }
                } catch (\LogicException $exception) {
                    $this->error = $exception->getMessage();
                    return false;
                }
                $values[$field->name] = $value;
            }
            return $values;
        }
        public function __toString(): string {
            \IPS\Theme::i()->getTemplate('forms', 'core', 'admin');
            return '<form data-native-form="' . $this->id . '"></form>';
        }
    }
}

namespace IPS\Helpers\Form {
    class Field {
        public ?string $label = null;
        public ?string $description = null;
        public mixed $validation;
        public function __construct(
            public string $name,
            public mixed $value = null,
            public bool $required = false,
            public array $options = [],
            ...$args
        ) { $this->validation = $args[0] ?? null; }
    }
    class Text extends Field {} class TextArea extends Field {} class Number extends Field {}
    class Radio extends Field {} class Select extends Field {} class CheckboxSet extends Field {}
    class Node extends Field {} class YesNo extends Field {} class Url extends Field {}
}

namespace IPS\Helpers\Table {
    #[\AllowDynamicProperties]
    class Db {
        public static ?self $last = null;
        public ?string $sortBy = null;
        public ?string $sortDirection = null;
        public function __construct(...$args) { self::$last = $this; \IPS\Theme::i()->getTemplate('tables', 'core', 'admin'); }
        public function __toString(): string { return '<table data-native-table></table>'; }
    }
}

namespace IPS\rqblockplacements {
    class Rule {
        public static array $records = [];
        public static array $saved = [];
        public static array $deleted = [];
        public static int $changes = 0;
        public int $id = 1;
        public string $name = 'Test rule';
        public int $enabled = 1;
        public int $block_id = 9;
        public string $content_type = 'block';
        public string $template_code = '';
        public string $mode = 'hook';
        public string $hook_path = 'forums/front/index/forumRow';
        public string $hook_name = 'rowItem';
        public string $selector = '.category';
        public int $sort_order = 10;
        public string $position = 'before';
        public string $occurrence = 'nth';
        public int $occurrence_index = 2;
        public array|string $platforms = ['Android', 'Windows'];
        public array|string $allow_groups = ['4'];
        public array|string $exclude_groups = ['2'];
        public array|string $apps = ['forums'];
        public array|string $include_urls = ['/forums/*'];
        public array|string $exclude_urls = ['/forums/private/*'];
        public static function all(): array { return array_values(self::$records); }
        public static function load($id): self { return self::$records[$id] ?? new self; }
        public function toConfig(): array { return Policy::normalize(get_object_vars($this)); }
        public function save(): void { self::$saved[] = clone $this; }
        public function delete(): void { self::$deleted[] = $this->id; }
        public static function changed(): void { self::$changes++; }
    }
    class TemplateContent {
        public static array $validated = [];
        public static function validate(string $code): void {
            self::$validated[] = $code;
            if (trim($code) === '') { throw new \InvalidArgumentException('rqbp_error_template_empty'); }
            if (strlen($code) > 65535) { throw new \InvalidArgumentException('rqbp_error_template_too_long'); }
            if ($code === 'invalid-template') { throw new \InvalidArgumentException('rqbp_error_template_invalid'); }
        }
    }
    class HookCatalog {
        public static function options(): array { return ['forums/front/index/forumRow' => ['rowItem' => 'rowItem (li)'], 'core/front/global/globalTemplate' => ['body' => 'body (body)', 'head' => 'head (head)']]; }
        public static function positions(string $path, string $name): array { return in_array($name, ['body', 'head'], true) ? ['inside-start', 'inside-end'] : ['before', 'inside-start', 'inside-end', 'after']; }
        public static function has(string $path, string $name, ?string $position = null): bool {
            return isset(self::options()[$path][$name]) && ($position === null || in_array($position, self::positions($path, $name), true));
        }
        public static function wrapper(...$args): string { return 'li'; }
    }
}

namespace {
    require __DIR__ . '/../application/sources/Policy/Policy.php';
    require __DIR__ . '/../application/sources/RuleAccess/RuleAccess.php';
    require __DIR__ . '/../application/sources/ServerSelector/ServerSelector.php';
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
    check(str_contains($html, 'data-native-table') && str_contains($html, 'rqbp_rules_intro_v5'), 'The rule list renders its core notice and table');
    \IPS\Application::$pages = false;
    check(str_contains(runAcp('rules', 'form'), 'rqbp_pages_unavailable'), 'Missing Pages renders a core admin warning for a block-only administrator');
    \IPS\Application::$pages = true;
    check(str_contains(runAcp('rules', 'form'), 'rqbp_rule_form'), 'The add-rule form renders in our application context');
    $fields = \IPS\Helpers\Form::$last->fields;
    check(isset($fields['rqbp_block_picker'], $fields['rqbp_block_reference'], $fields['rqbp_hook'], $fields['rqbp_selector']), 'The native form contains both block sources and placement modes');
    check($fields['rqbp_block_reference']->description === 'rqbp_block_reference_help_v10', 'The block reference uses the new explicit help instead of a previously saved field description');
    check($fields['rqbp_mode']->options['options'] === \IPS\rqblockplacements\Policy::modeOptions()
        && $fields['rqbp_mode']->options['toggles']['server'] === ['rqbp_selector'], 'The server CSS mode is distinct and displays the selector field');
    check($fields['rqbp_selector']->description === 'rqbp_selector_help_v14' && $fields['rqbp_occurrence']->description === 'rqbp_occurrence_help_v14', 'Fresh explicit help explains server scope and comma-list occurrences');
    check(isset($fields['rqbp_hook_app'], $fields['rqbp_hook_template']), 'Native-style selectors separate application, template and target hook');
    check($fields['rqbp_position'] instanceof \IPS\Helpers\Form\Select && $fields['rqbp_position']->options['options'] === \IPS\rqblockplacements\Policy::positionOptions(), 'The type dropdown uses all four native hook types');
    check($fields['rqbp_position']->label === 'template_hookpoint_type', 'Hook Type uses the native field label');
    $hookPositions = json_decode(\IPS\Helpers\Form::$last->attributes['data-rqbp-hook-positions'], true, 512, JSON_THROW_ON_ERROR);
    check($hookPositions['core/front/global/globalTemplate|body'] === ['inside-start', 'inside-end'], 'The browser receives only safe body positions');
    check($hookPositions['core/front/global/globalTemplate|head'] === ['inside-start', 'inside-end'], 'The native head target is offered with only its inside positions');
    check($fields['rqbp_hook']->description === 'rqbp_hook_help_v13' && $fields['rqbp_position']->description === 'rqbp_position_help_v13', 'Fresh head-aware help is used on existing installations');
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
    check(str_contains(runAcp('rules', 'diagnostics', 1), 'rqbp_diag_content_scope'), 'Diagnostics renders its core scope notice');
    check(str_contains(runAcp('settings'), 'rqbp_settings_form'), 'The settings form renders');
    check(count(\IPS\Dispatcher::i()->permissions) === 6 && array_unique(\IPS\Dispatcher::i()->permissions) === ['rqbp_manage'], 'Every ACP route checks its management permission');

    // Template editing is a separate capability; rendering and executing code are out of scope here.
    check(array_keys($existingFields['rqbp_block_source']->options['options']) === ['picker', 'manual'], 'A block-only administrator sees the two existing block sources');
    check(!isset($existingFields['rqbp_template_code']), 'A block-only administrator never receives the code input');
    check($existingFields['rqbp_block_source']->label === 'rqbp_content_source', 'The source field uses the new generic content label without changing its POST name');
    runAcp('rules');
    $table = \IPS\Helpers\Table\Db::$last;
    check(in_array('rule_content', $table->include, true) && !in_array('rule_template_code', $table->include, true), 'The table displays a content summary and never a template-code column');
    $template = new \IPS\rqblockplacements\Rule;
    $template->id = 2;
    $template->content_type = 'template';
    $template->block_id = 0;
    $template->template_code = '{expression="(new \\IPS\\ExampleContent())->render(\'example\')" raw="true"}';
    \IPS\rqblockplacements\Rule::$records[2] = $template;
    $row = ['rule_id' => 2, 'rule_content_type' => 'template', 'rule_template_code' => $template->template_code];
    check(($table->parsers['rule_content'])(null, $row) === 'rqbp_source_template', 'The table identifies template contents without exposing its source code');
    check(($table->rowButtons)($row) === [], 'An administrator without code permission receives no template-rule action links');
    $validations = count(\IPS\rqblockplacements\TemplateContent::$validated);
    foreach (['form', 'toggle', 'duplicate', 'delete', 'diagnostics', 'move'] as $action) {
        $denied = false;
        try { runAcp('rules', $action, 2); }
        catch (\IPS\AcpPermissionDenied $exception) { $denied = str_contains($exception->getMessage(), '403'); }
        check($denied, "The {$action} endpoint requires code permission for existing template rules");
    }
    check(count(\IPS\rqblockplacements\TemplateContent::$validated) === $validations, 'Denied template routes do not invoke the compiler');
    check(\IPS\rqblockplacements\Rule::$saved === [] && \IPS\rqblockplacements\Rule::$deleted === [], 'Denied template routes do not mutate rules');
    foreach ([null, 1] as $id) {
        \IPS\Request::i()->rqbp_block_source = 'template';
        $denied = false;
        try { runAcp('rules', 'form', $id); }
        catch (\IPS\AcpPermissionDenied) { $denied = true; }
        check($denied, 'Forging the template source is denied before form validation for new and existing block rules');
    }
    unset(\IPS\Request::i()->rqbp_block_source);
    check(count(\IPS\rqblockplacements\TemplateContent::$validated) === $validations, 'Forged template source does not reach compilation');

    \IPS\Member::$codePermission = true;
    runAcp('rules', 'form');
    $codeFields = \IPS\Helpers\Form::$last->fields;
    check(array_keys($codeFields['rqbp_block_source']->options['options']) === ['picker', 'manual', 'template'], 'A code administrator sees all three sources when Pages is enabled');
    check($codeFields['rqbp_template_code']->options === ['rows' => 8, 'maxLength' => 65535, 'codeMode' => false], 'Template code uses a plain eight-row text area with the native length cap');
    runAcp('rules', 'form', 2);
    $codeFields = \IPS\Helpers\Form::$last->fields;
    check($codeFields['rqbp_block_source']->value === 'template', 'Editing a template rule selects the template source');
    check($codeFields['rqbp_template_code']->value === $template->template_code, 'Editing preserves the original IPS template source exactly');
    $loads = \IPS\cms\Blocks\Block::$loads;
    \IPS\Application::$pages = false;
    check(str_contains(runAcp('rules', 'form'), 'rqbp_rule_form'), 'A code administrator can open the full rule form without Pages');
    $codeFields = \IPS\Helpers\Form::$last->fields;
    check($codeFields['rqbp_block_source']->options['options'] === ['template' => 'rqbp_source_template'], 'Only the template source is offered while Pages is unavailable');
    check(!isset($codeFields['rqbp_block_picker'], $codeFields['rqbp_block_reference']), 'The form does not initialize unavailable Pages helpers');
    check($codeFields['rqbp_block_source']->value === 'template', 'A new rule without Pages defaults to its available template source');
    $html = runAcp('rules', 'diagnostics', 2);
    check(str_contains($html, 'rqbp_diag_template') && !str_contains($html, 'rqbp_diag_pages'), 'Template diagnostics validate syntax and omit Pages requirements');
    check(\IPS\cms\Blocks\Block::$loads === $loads, 'Code forms and diagnostics do not access Pages when it is unavailable');
    check(end(\IPS\rqblockplacements\TemplateContent::$validated) === $template->template_code, 'Diagnostics pass the original template source to syntax validation only');

    \IPS\Request::i()->rqbp_block_source = 'template';
    \IPS\Request::i()->rqbp_mode = 'css';
    \IPS\Helpers\Form::$submitted = ['rqbp_name' => 'Code without Pages', 'rqbp_template_code' => $template->template_code, 'rqbp_mode' => 'css', 'rqbp_selector' => '.category'];
    runAcp('rules', 'form');
    $saved = end(\IPS\rqblockplacements\Rule::$saved);
    check($saved->content_type === 'template' && $saved->block_id === 0, 'Saving custom code persists its discriminator and clears the block ID');
    check($saved->template_code === $template->template_code && $saved->enabled === 0, 'Saving custom code preserves exact source and the disabled default');
    unset(\IPS\Request::i()->rqbp_mode);
    $savedCount = count(\IPS\rqblockplacements\Rule::$saved);
    foreach (['' => 'rqbp_error_template_empty', 'invalid-template' => 'rqbp_error_template_invalid', str_repeat('ě', 32768) => 'rqbp_error_template_too_long'] as $code => $error) {
        \IPS\Helpers\Form::$submitted = ['rqbp_name' => 'Invalid code', 'rqbp_template_code' => $code];
        runAcp('rules', 'form');
        check(\IPS\Helpers\Form::$last->error === $error, 'Template validation maps its known failure to a field language key');
    }
    check(count(\IPS\rqblockplacements\Rule::$saved) === $savedCount, 'Empty, invalid, and oversized UTF-8 code is rejected without saving');
    runAcp('rules', 'duplicate', 2);
    $copy = end(\IPS\rqblockplacements\Rule::$saved);
    check($copy->content_type === 'template' && $copy->template_code === $template->template_code && $copy->enabled === 0, 'Duplicating a code rule preserves its contents but always disables the copy');

    \IPS\Application::$pages = true;
    \IPS\Request::i()->rqbp_block_source = 'picker';
    \IPS\Helpers\Form::$submitted = ['rqbp_block_picker' => new \IPS\cms\Blocks\Block];
    runAcp('rules', 'form', 2);
    $saved = end(\IPS\rqblockplacements\Rule::$saved);
    check($saved->content_type === 'block' && $saved->block_id === 9 && $saved->template_code === '', 'Switching from code to a block clears stored code');
    \IPS\Member::$codePermission = false;
    $validations = count(\IPS\rqblockplacements\TemplateContent::$validated);
    \IPS\Helpers\Form::$submitted = ['rqbp_block_picker' => new \IPS\cms\Blocks\Block, 'rqbp_template_code' => 'forged hidden code'];
    runAcp('rules', 'form', 1);
    $saved = end(\IPS\rqblockplacements\Rule::$saved);
    check($saved->content_type === 'block' && $saved->template_code === '', 'An ordinary block editor can save a block and cannot retain a forged code field');
    check(count(\IPS\rqblockplacements\TemplateContent::$validated) === $validations, 'Ordinary block saving never invokes template compilation');
    unset(\IPS\Request::i()->rqbp_block_source);

    $firstBlock = new \IPS\rqblockplacements\Rule;
    $firstBlock->id = 1;
    $firstBlock->sort_order = 10;
    $codeNeighbor = new \IPS\rqblockplacements\Rule;
    $codeNeighbor->id = 2;
    $codeNeighbor->content_type = 'template';
    $codeNeighbor->sort_order = 20;
    \IPS\rqblockplacements\Rule::$records = [1 => $firstBlock, 2 => $codeNeighbor];
    $savedCount = count(\IPS\rqblockplacements\Rule::$saved);
    \IPS\Request::i()->direction = 'down';
    $denied = false;
    try { runAcp('rules', 'move', 1); }
    catch (\IPS\AcpPermissionDenied) { $denied = true; }
    check($denied, 'Moving an ordinary block across a template rule also requires code permission');
    check(count(\IPS\rqblockplacements\Rule::$saved) === $savedCount, 'A denied reorder does not save an earlier row before discovering the protected code rule');
    check($firstBlock->sort_order === 10 && $codeNeighbor->sort_order === 20, 'A denied reorder leaves every in-memory order unchanged');
    unset(\IPS\Request::i()->direction);

    // The global switch must not bypass the per-rule permission to activate template code.
    $settings = \IPS\Settings::i();
    $settings->rqbp_enabled = false;
    $settings->writes = [];
    \IPS\rqblockplacements\Rule::$changes = 0;
    \IPS\Helpers\Form::$submitted = ['rqbp_enabled' => true];
    $savedCount = count(\IPS\rqblockplacements\Rule::$saved);
    $denied = false;
    try { runAcp('settings'); }
    catch (\IPS\AcpPermissionDenied) { $denied = true; }
    check($denied && !$settings->rqbp_enabled, 'A block administrator cannot globally reactivate an enabled template rule');
    check($settings->writes === [] && \IPS\rqblockplacements\Rule::$changes === 0, 'Denied master activation changes neither settings nor caches/hooks');
    check(count(\IPS\rqblockplacements\Rule::$saved) === $savedCount, 'Denied master activation saves no rule');

    $codeNeighbor->enabled = 0;
    \IPS\Helpers\Form::$submitted = ['rqbp_enabled' => true];
    runAcp('settings');
    check($settings->rqbp_enabled && count($settings->writes) === 1, 'A block administrator can enable the application when every template rule is disabled');
    check(\IPS\rqblockplacements\Rule::$changes === 1, 'An allowed master change performs its ordinary cache/hook invalidation');

    $settings->rqbp_enabled = false;
    \IPS\rqblockplacements\Rule::$records = [1 => $firstBlock];
    \IPS\Helpers\Form::$submitted = ['rqbp_enabled' => true];
    runAcp('settings');
    check($settings->rqbp_enabled, 'A block-only installation can be enabled without code permission');

    $codeNeighbor->enabled = 1;
    \IPS\rqblockplacements\Rule::$records[2] = $codeNeighbor;
    \IPS\Helpers\Form::$submitted = ['rqbp_enabled' => false];
    runAcp('settings');
    check(!$settings->rqbp_enabled, 'A block administrator can stop enabled code through the global panic stop');

    \IPS\Member::$codePermission = true;
    \IPS\Helpers\Form::$submitted = ['rqbp_enabled' => true];
    runAcp('settings');
    check($settings->rqbp_enabled, 'A code administrator can globally activate enabled template rules');
    \IPS\Member::$codePermission = false;
    \IPS\Helpers\Form::$submitted = ['rqbp_enabled' => true];
    runAcp('settings');
    check($settings->rqbp_enabled, 'Saving an already enabled global switch does not require code permission');

    check(!\IPS\rqblockplacements\RuleAccess::requiresCodePermission([]), 'Legacy records with no content type remain ordinary blocks');
    check(\IPS\rqblockplacements\RuleAccess::requiresCodePermission(['rule_content_type' => 'unexpected']), 'An unknown stored source fails closed for ordinary administrators');
    $unknown = new \IPS\rqblockplacements\Rule;
    $unknown->id = 3;
    $unknown->content_type = 'unexpected';
    \IPS\rqblockplacements\Rule::$records[3] = $unknown;
    $denied = false;
    try { runAcp('rules', 'form', 3); }
    catch (\IPS\AcpPermissionDenied) { $denied = true; }
    check($denied, 'Direct requests cannot treat an unknown stored source as an ordinary block');

    \IPS\Application::$pages = true;
    \IPS\Request::i()->rqbp_block_source = 'picker';
    \IPS\Request::i()->rqbp_mode = 'hook';
    \IPS\Request::i()->rqbp_hook_app = 'core';
    \IPS\Request::i()->rqbp_hook_template = 'core/front/global/globalTemplate';
    \IPS\Request::i()->rqbp_hook = 'core/front/global/globalTemplate|body';
    $bodySubmission = [
        'rqbp_name' => 'Body placement', 'rqbp_block_source' => 'picker',
        'rqbp_block_picker' => new \IPS\cms\Blocks\Block, 'rqbp_mode' => 'hook',
        'rqbp_hook_app' => 'core', 'rqbp_hook_template' => 'core/front/global/globalTemplate',
        'rqbp_hook' => 'core/front/global/globalTemplate|body',
    ];
    foreach (['inside-start', 'inside-end'] as $position) {
        $savedCount = count(\IPS\rqblockplacements\Rule::$saved);
        \IPS\Helpers\Form::$submitted = $bodySubmission + ['rqbp_position' => $position];
        runAcp('rules', 'form');
        $saved = end(\IPS\rqblockplacements\Rule::$saved);
        check(count(\IPS\rqblockplacements\Rule::$saved) === $savedCount + 1
            && $saved->position === $position && $saved->hook_name === 'body'
            && $saved->hook_path === 'core/front/global/globalTemplate', 'Native body positions are saved without truncation or loss of the target');
    }
    foreach (['before', 'after', 'inside', 'unknown'] as $position) {
        $savedCount = count(\IPS\rqblockplacements\Rule::$saved);
        \IPS\Helpers\Form::$submitted = $bodySubmission + ['rqbp_position' => $position];
        runAcp('rules', 'form');
        check(count(\IPS\rqblockplacements\Rule::$saved) === $savedCount
            && \IPS\Helpers\Form::$last->error === 'rqbp_error_position', 'Unsafe or unsupported body positions cannot be saved through a forged request');
    }
    \IPS\Request::i()->rqbp_hook = 'core/front/global/globalTemplate|head';
    $headSubmission = array_replace($bodySubmission, [
        'rqbp_name' => 'Head placement', 'rqbp_hook' => 'core/front/global/globalTemplate|head',
    ]);
    foreach (['inside-start', 'inside-end'] as $position) {
        $savedCount = count(\IPS\rqblockplacements\Rule::$saved);
        \IPS\Helpers\Form::$submitted = $headSubmission + ['rqbp_position' => $position];
        runAcp('rules', 'form');
        $saved = end(\IPS\rqblockplacements\Rule::$saved);
        check(count(\IPS\rqblockplacements\Rule::$saved) === $savedCount + 1
            && $saved->position === $position && $saved->hook_name === 'head'
            && $saved->hook_path === 'core/front/global/globalTemplate', 'Native head positions preserve the metadata target and complete position');
    }
    foreach (['before', 'after'] as $position) {
        $savedCount = count(\IPS\rqblockplacements\Rule::$saved);
        \IPS\Helpers\Form::$submitted = $headSubmission + ['rqbp_position' => $position];
        runAcp('rules', 'form');
        check(count(\IPS\rqblockplacements\Rule::$saved) === $savedCount
            && \IPS\Helpers\Form::$last->error === 'rqbp_error_position', 'Forged requests cannot place metadata outside the head element');
    }
    \IPS\Request::i()->rqbp_hook = 'core/front/global/globalTemplate|body';
    \IPS\Helpers\Form::$submitted = array_replace($bodySubmission, ['rqbp_hook_app' => 'forums', 'rqbp_position' => 'inside-end']);
    $savedCount = count(\IPS\rqblockplacements\Rule::$saved);
    runAcp('rules', 'form');
    check(count(\IPS\rqblockplacements\Rule::$saved) === $savedCount, 'Mismatched application/template/target selectors are rejected before saving');
    foreach (['rqbp_hook_app', 'rqbp_hook_template', 'rqbp_hook', 'rqbp_mode'] as $field) { unset(\IPS\Request::i()->$field); }

    \IPS\Request::i()->rqbp_mode = 'server';
    $serverSubmission = [
        'rqbp_name' => 'Server placement', 'rqbp_block_source' => 'picker',
        'rqbp_block_picker' => new \IPS\cms\Blocks\Block, 'rqbp_mode' => 'server',
        'rqbp_position' => 'after', 'rqbp_occurrence' => 'first',
    ];
    foreach ([
        '#ipsLayout__main .ipsBlockSpacer > li:nth-child(3)',
        '#ipsLayout__main .ipsEntries--topic .ipsEntry[data-ips-hook="postWrapper"]:nth-of-type(5)',
        '[data-label="a,b"], .category:nth-child(2n + 1)',
    ] as $selector) {
        $savedCount = count(\IPS\rqblockplacements\Rule::$saved);
        \IPS\Helpers\Form::$submitted = $serverSubmission + ['rqbp_selector' => $selector];
        runAcp('rules', 'form');
        $saved = end(\IPS\rqblockplacements\Rule::$saved);
        check(count(\IPS\rqblockplacements\Rule::$saved) === $savedCount + 1
            && $saved->mode === 'server' && $saved->selector === $selector
            && $saved->hook_path === '' && $saved->hook_name === '' && !$saved->enabled, 'The ACP saves a disabled server placement without truncating its selector or retaining a stale hook');
    }
    foreach (['', '[', '.category:hover', '.category:has(p)', ':is(.one,.two)', '.escaped\\:class'] as $selector) {
        $savedCount = count(\IPS\rqblockplacements\Rule::$saved);
        \IPS\Helpers\Form::$submitted = $serverSubmission + ['rqbp_selector' => $selector];
        runAcp('rules', 'form');
        check(count(\IPS\rqblockplacements\Rule::$saved) === $savedCount
            && in_array(\IPS\Helpers\Form::$last->error, ['rqbp_error_selector', 'rqbp_error_server_selector'], true), 'Unsupported server syntax is rejected before mutation instead of silently changing to JavaScript');
    }
    \IPS\Request::i()->rqbp_mode = 'css';
    \IPS\Helpers\Form::$submitted = array_replace($serverSubmission, ['rqbp_mode' => 'css', 'rqbp_selector' => '.category:has(p)']);
    runAcp('rules', 'form');
    $saved = end(\IPS\rqblockplacements\Rule::$saved);
    check($saved->mode === 'css' && $saved->selector === '.category:has(p)', 'Legacy browser selectors keep their original JavaScript mode and browser syntax');
    unset(\IPS\Request::i()->rqbp_mode);

    foreach (\IPS\Theme::$calls as [$group, $app, $location]) {
        check($app === 'core' && ($group !== 'global' || $location === 'admin'), 'Every shared template uses its explicit core context');
    }
    echo "ACP context boundary tests: {$checks} passed\n";
}
