<?php
/** Original hook-context and registry boundary fixtures; no platform source is bundled. */
namespace { define('IPS\SUITE_UNIQUE_KEY', 'hook-catalog-test'); }

namespace IPS {
    class Application
    {
        public static array $enabled = [];
        public static function enabledApplications(): array { return self::$enabled; }
    }
    class HookRows implements \IteratorAggregate
    {
        public function __construct(private array $rows) {}
        public function getIterator(): \Traversable { yield from $this->rows; }
        public function first(): array
        {
            if (!$this->rows) { throw new \UnderflowException; }
            return $this->rows[0];
        }
    }
    class Db
    {
        public static array $templates = [];
        public static array $custom = [];
        public static int $catalogReads = 0;
        public static function i(): self { static $instance; return $instance ??= new self; }
        public function checkForTable(string $table): bool { return $table === 'rqbp_rules'; }
        public function select(string $columns, string $table, array $where): HookRows
        {
            if ($table === 'core_theme_templates') {
                self::$catalogReads++;
                if ($where !== [['template_location=? AND template_has_hookpoints=?', 'front', 1]]) {
                    throw new \RuntimeException('Hook discovery must stay in frontend templates with native hook points');
                }
                return new HookRows(self::$templates);
            }
            if ($table !== 'core_theme_templates_custom' || ($where[1] ?? null) !== 'rqblockplacements') {
                throw new \RuntimeException('Unexpected or unscoped hook-registry query');
            }
            return new HookRows(array_values(array_filter(self::$custom, static fn (array $row): bool =>
                $row['template_app'] === $where[1] && (!isset($where[2]) || $row['template_name'] === $where[2]))));
        }
    }
}

namespace IPS\Data {
    #[\AllowDynamicProperties]
    class Store
    {
        public static function i(): self { static $instance; return $instance ??= new self; }
    }
}

namespace IPS\Theme {
    #[\AllowDynamicProperties]
    class CustomTemplate
    {
        public static array $saved = [];
        public static array $deleted = [];
        public static function constructFromData(array $row): self
        {
            $template = new self;
            $template->name = $row['template_name'];
            return $template;
        }
        public function save(): void { self::$saved[$this->name] = get_object_vars($this); }
        public function delete(): void { self::$deleted[] = $this->name; }
    }
}

namespace IPS\rqblockplacements {
    class Rule
    {
        public static array $records = [];
        public static function all(): array { return self::$records; }
        public function __construct(private array $config) {}
        public function toConfig(): array { return $this->config; }
    }
}

namespace {
    require __DIR__ . '/../application/Application.php';
    require __DIR__ . '/../application/sources/HookCatalog/HookCatalog.php';
    require __DIR__ . '/../application/sources/HookRegistry/HookRegistry.php';
    use IPS\Db;
    use IPS\Data\Store;
    use IPS\rqblockplacements\Application;
    use IPS\rqblockplacements\HookCatalog;
    use IPS\rqblockplacements\HookRegistry;
    use IPS\rqblockplacements\Rule;

    $checks = 0;
    function same(mixed $expected, mixed $actual, string $message): void
    {
        global $checks;
        $checks++;
        if ($expected !== $actual) { throw new \RuntimeException($message . ': ' . var_export($actual, true)); }
    }
    function rejects(callable $operation, string $message): void
    {
        global $checks;
        $checks++;
        try { $operation(); } catch (\InvalidArgumentException) { return; }
        throw new \RuntimeException($message);
    }
    function forgetRequestCatalog(): void
    {
        (new \ReflectionProperty(HookCatalog::class, 'catalog'))->setValue(null, null);
    }
    function source(string $name, string $content, string $app = 'core'): array
    {
        return ['template_app' => $app, 'template_group' => 'example', 'template_name' => $name, 'template_content' => $content];
    }
    $all = ['before', 'inside-start', 'inside-end', 'after'];
    \IPS\Application::$enabled = ['core' => (object) ['long_version' => 5000020], 'forums' => (object) ['long_version' => 5000020]];
    Db::$templates = [
        source('global', '<html data-ips-hook="html"><head data-ips-hook="head"><title data-ips-hook="title">Title</title><div data-ips-hook="headChild"></div></head><body data-ips-hook="body"><div data-ips-hook="layout"></div></body></html>'),
        source('lists', '<div><ul data-ips-hook="unordered"><li data-ips-hook="item"><div data-ips-hook="itemContent"></div></li></ul><ol data-ips-hook="ordered"></ol><menu data-ips-hook="menu"></menu></div>'),
        source('fragment', '<li data-ips-hook="rootItem">Item</li>'),
        source('headFragment', '<head data-ips-hook="fragmentHead"><meta name="example" content="value"></head>'),
        source('invalidHead', '<div><head data-ips-hook="nestedHead"></head></div>'),
        source('text', '<div><p data-ips-hook="paragraph">Text</p><h2 data-ips-hook="heading">Heading</h2></div>'),
        source('forbidden', '<div><script data-ips-hook="script">"<div data-ips-hook=\'scriptChild\'></div>"</script><style data-ips-hook="style"></style><table data-ips-hook="table"><tbody><tr><td><div data-ips-hook="tableChild"></div></td></tr></tbody></table><select data-ips-hook="select"><option data-ips-hook="option">Option</option></select><svg data-ips-hook="svg"><foreignObject><div data-ips-hook="svgChild"></div></foreignObject></svg><a><div data-ips-hook="linkChild"></div></a><p><div data-ips-hook="paragraphChild"></div></p><span><section><div data-ips-hook="inlineAncestor"></div></section></span></div>'),
        source('attributes', '<div><!-- <div data-ips-hook="comment"></div> --><div title=\'data-ips-hook="quotedFake"\'></div><div title="x > y" data-ips-hook="quotedGreater"></div><div data-ips-hook="dynamic{{if true}}suffix{{endif}}"></div><div class="{{if $value > 1}}wide{{endif}}" data-ips-hook="condition"></div></div>'),
        source('duplicates', '<div><div data-ips-hook="mixed"></div><p data-ips-hook="mixed"></p><div data-ips-hook="blocked"></div><script data-ips-hook="blocked"></script></div>'),
        source('disabled', '<div data-ips-hook="disabledApp"></div>', 'disabledapp'),
    ];
    Store::i()->rqbp_hook_catalog = ['fingerprint' => hash('sha256', json_encode(['core' => '5000020', 'forums' => '5000020'])),
        'hooks' => ['core/front/example/global' => ['stale' => ['tag' => 'div', 'wrapper' => 'div']]]];

    $global = 'core/front/example/global';
    same(['inside-start', 'inside-end'], HookCatalog::positions($global, 'body'), 'Body is only available for insertion inside the element');
    same(false, HookCatalog::has($global, 'body', 'before'), 'A block cannot be inserted before body');
    same(false, HookCatalog::has($global, 'body', 'after'), 'A block cannot be inserted after body');
    foreach (['inside-start', 'inside-end'] as $position) {
        same('div', HookCatalog::wrapper($global, 'body', $position), 'Body inside positions use a flow wrapper');
    }
    same($all, HookCatalog::positions($global, 'layout'), 'A normal flow container offers all four native positions');
    foreach ($all as $position) { same('div', HookCatalog::wrapper($global, 'layout', $position), 'Layout position uses a flow wrapper'); }
    same(false, HookCatalog::has($global, 'html'), 'The html element cannot receive blocks');
    same(['inside-start', 'inside-end'], HookCatalog::positions($global, 'head'), 'Head is only available for insertion inside the element');
    same(true, HookCatalog::has($global, 'head'), 'Head remains discoverable with an empty raw wrapper');
    foreach (['inside-start', 'inside-end'] as $position) {
        same(true, HookCatalog::has($global, 'head', $position), 'An empty raw wrapper is a valid head position');
        same('', HookCatalog::wrapper($global, 'head', $position), 'Head inserts metadata without a div wrapper');
    }
    foreach (['before', 'after'] as $position) {
        same(false, HookCatalog::has($global, 'head', $position), 'Head does not allow insertion outside the element');
        rejects(static fn () => HookCatalog::wrapper($global, 'head', $position), 'An outer head position must not get a wrapper');
    }
    same(false, HookCatalog::has($global, 'headChild'), 'Head children remain forbidden as separate insertion targets');
    same(false, HookCatalog::has($global, 'title'), 'The title element cannot receive blocks');
    rejects(static fn () => HookCatalog::wrapper($global, 'body'), 'The default after position must also reject body');
    rejects(static fn () => HookCatalog::wrapper($global, 'layout', 'unknown'), 'Unknown placement types must not get a wrapper');
    same(1, Db::$catalogReads, 'An old catalog cache is rebuilt once');
    same(3, Store::i()->rqbp_hook_catalog['version'], 'The stored catalog identifies its head-aware format');
    forgetRequestCatalog();
    same($all, HookCatalog::positions($global, 'layout'), 'The position-aware catalog can be restored from its store');
    same('', HookCatalog::wrapper($global, 'head', 'inside-start'), 'A cached empty head wrapper stays valid');
    same(1, Db::$catalogReads, 'A current stored catalog avoids another source scan');
    same(['inside-start', 'inside-end'], HookCatalog::positions('core/front/example/headFragment', 'fragmentHead'), 'A root head fragment allows only inside positions');
    same('', HookCatalog::wrapper('core/front/example/headFragment', 'fragmentHead', 'inside-end'), 'A root head fragment also uses raw metadata');
    same(false, HookCatalog::has('core/front/example/invalidHead', 'nestedHead'), 'Head nested in a non-html parent remains unsupported');

    $lists = 'core/front/example/lists';
    foreach (['unordered', 'ordered', 'menu'] as $hook) {
        same($all, HookCatalog::positions($lists, $hook), 'List containers offer all four positions');
        foreach (['inside-start', 'inside-end'] as $position) { same('li', HookCatalog::wrapper($lists, $hook, $position), 'List children must be li elements'); }
        foreach (['before', 'after'] as $position) { same('div', HookCatalog::wrapper($lists, $hook, $position), 'List-container siblings in a flow parent use div'); }
    }
    same($all, HookCatalog::positions($lists, 'item'), 'List items offer all four positions');
    foreach (['before', 'after'] as $position) { same('li', HookCatalog::wrapper($lists, 'item', $position), 'List-item siblings remain list items'); }
    foreach (['inside-start', 'inside-end'] as $position) { same('div', HookCatalog::wrapper($lists, 'item', $position), 'List-item children use a flow wrapper'); }
    same('li', HookCatalog::wrapper('core/front/example/fragment', 'rootItem', 'before'), 'A root list-item template retains its list sibling context');
    foreach (['paragraph', 'heading'] as $hook) {
        same(['before', 'after'], HookCatalog::positions('core/front/example/text', $hook), 'Text containers support siblings but not nested block children');
    }
    foreach (['script', 'scriptChild', 'style', 'table', 'tableChild', 'select', 'option', 'svg', 'svgChild', 'linkChild', 'paragraphChild', 'inlineAncestor'] as $hook) {
        same(false, HookCatalog::has('core/front/example/forbidden', $hook), 'Forbidden element or ancestor cannot gain any placement: ' . $hook);
    }
    foreach (['comment', 'quotedFake', 'dynamic'] as $hook) {
        same(false, HookCatalog::has('core/front/example/attributes', $hook), 'Only literal real hook attributes enter the catalog');
    }
    same($all, HookCatalog::positions('core/front/example/attributes', 'quotedGreater'), 'A greater-than sign inside a quoted attribute does not break hook parsing');
    same($all, HookCatalog::positions('core/front/example/attributes', 'condition'), 'Template comparisons are masked instead of being parsed as HTML');
    same(['before', 'after'], HookCatalog::positions('core/front/example/duplicates', 'mixed'), 'A repeated hook offers only positions safe for all occurrences');
    same(false, HookCatalog::has('core/front/example/duplicates', 'blocked'), 'A repeated hook with a forbidden occurrence is completely omitted');
    same(false, HookCatalog::has('disabledapp/front/example/disabled', 'disabledApp'), 'Disabled applications cannot supply hook targets');
    same('body (body)', HookCatalog::options()[$global]['body'], 'The existing options API keeps its path/name/label shape');

    \IPS\Application::$enabled['core']->long_version++;
    forgetRequestCatalog();
    HookCatalog::positions($global, 'layout');
    same(2, Db::$catalogReads, 'An application-version change invalidates stored hook contexts');

    $rule = static fn (int $id, string $path, string $name, string $position, string $mode = 'hook'): Rule => new Rule([
        'id' => $id, 'mode' => $mode, 'hook_path' => $path, 'hook_name' => $name, 'position' => $position, 'sort_order' => $id * 10,
    ]);
    Rule::$records = [
        $rule(7, $global, 'body', 'inside-start'), $rule(8, $global, 'body', 'before'),
        $rule(9, $lists, 'unordered', 'inside-end'), $rule(10, $lists, 'item', 'after'),
        $rule(11, $global, 'layout', 'unknown'), $rule(12, $global, 'layout', 'inside-start', 'css'),
        $rule(13, $global, 'head', 'inside-end'), $rule(14, $global, 'head', 'before'),
    ];
    Db::$custom = [
        ['template_app' => 'rqblockplacements', 'template_name' => 'rqbp_rule_7'],
        ['template_app' => 'rqblockplacements', 'template_name' => 'rqbp_rule_99'],
        ['template_app' => 'rqblockplacements', 'template_name' => 'rqbp_prepare'],
        ['template_app' => 'otherapp', 'template_name' => 'rqbp_rule_100'],
    ];
    Store::i()->custom_templates = ['stale'];
    Store::i()->custom_templates_rows = ['stale'];
    HookRegistry::reconcile();
    same(['rqbp_rule_7', 'rqbp_rule_9', 'rqbp_rule_10', 'rqbp_rule_13'], array_keys(\IPS\Theme\CustomTemplate::$saved), 'Only valid hook/position pairs receive registry markers, including raw head metadata');
    same(['rqbp_rule_99'], \IPS\Theme\CustomTemplate::$deleted, 'Registry cleanup stays within this application and its dynamic markers');
    same('inside-start', \IPS\Theme\CustomTemplate::$saved['rqbp_rule_7']['hookpoint_type'], 'Inside-start is stored using its native canonical value');
    same('inside-end', \IPS\Theme\CustomTemplate::$saved['rqbp_rule_9']['hookpoint_type'], 'Inside-end is stored using its native canonical value');
    same('after', \IPS\Theme\CustomTemplate::$saved['rqbp_rule_10']['hookpoint_type'], 'Existing after placement remains canonical');
    same('inside-end', \IPS\Theme\CustomTemplate::$saved['rqbp_rule_13']['hookpoint_type'], 'Raw head insertion keeps its native canonical position');
    same($global . ':head', \IPS\Theme\CustomTemplate::$saved['rqbp_rule_13']['hookpoint'], 'The registry forwards the head target without changing its path');
    same($global . ':body', \IPS\Theme\CustomTemplate::$saved['rqbp_rule_7']['hookpoint'], 'Registry preserves the native hookpoint path shape');
    same('{expression="\\IPS\\rqblockplacements\\Runtime::hook(7)" raw="true"}', \IPS\Theme\CustomTemplate::$saved['rqbp_rule_7']['content'], 'Generated marker content remains fixed code with an integer rule identifier');
    same(Application::RQBP_BUILD, \IPS\Theme\CustomTemplate::$saved['rqbp_rule_7']['version'], 'Registry markers use the current application build');
    same(false, isset(Store::i()->custom_templates) || isset(Store::i()->custom_templates_rows), 'Registry reconciliation invalidates both native template stores');
    echo "Hook catalog and registry boundary tests: {$checks} passed\n";
}
