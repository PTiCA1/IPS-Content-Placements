<?php
/** Pages Block Placements - MIT. */
namespace IPS\rqblockplacements;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

/** One HTTP request, one permission context. No rendered HTML is shared. */
final class Runtime
{
    private static ?array $rules = null;
    private static array $rendered = [];
    private static array $rendering = [];
    private static array $ordinals = [];
    private static array $diagnostics = [];
    private static bool $prepared = false;
    private static bool $payloadSent = false;
    private static bool $cacheChecked = false;

    public static function reset(): void
    {
        self::$rules = null;
        self::$rendered = self::$rendering = self::$ordinals = self::$diagnostics = [];
        self::$prepared = self::$payloadSent = self::$cacheChecked = false;
    }

    private static function enabled(): bool
    {
        return \IPS\Dispatcher::hasInstance()
            && \IPS\Dispatcher::i()->controllerLocation === 'front'
            && \IPS\Application::appIsEnabled('cms')
            && \IPS\Application::appIsEnabled('rqblockplacements')
            && (bool) \IPS\Settings::i()->rqbp_enabled;
    }

    private static function rules(): array
    {
        if (self::$rules === null) {
            self::$rules = [];
            foreach (Rule::all() as $rule) { $config = $rule->toConfig(); self::$rules[$config['id']] = $config; }
        }
        return self::$rules;
    }

    public static function context(): array
    {
        $url = Policy::relativeUrl((string) \IPS\Request::i()->url(), \IPS\Http\Url::baseUrl());
        // Diagnostic navigation must not itself change a rule's URL eligibility.
        if (str_contains($url, '?')) {
            [$path, $query] = explode('?', $url, 2);
            $query = trim(preg_replace('/(?:^|&)(?:rqbp_diagnostics|rqbp_rule)=[^&]*/', '', $query), '&');
            $url = $path . ($query !== '' ? '?' . $query : '');
        }
        return [
            'app' => \IPS\Dispatcher::i()->application->directory,
            'url' => $url,
            'platform' => \IPS\Session::i()->userAgent->platform,
            'groups' => \IPS\Member::loggedIn()->groups ?? [],
        ];
    }

    private static function debug(): bool
    {
        return (bool) \IPS\Request::i()->rqbp_diagnostics
            && \IPS\Member::loggedIn()->member_id
            && \IPS\Member::loggedIn()->hasAcpRestriction('rqblockplacements', 'placements', 'rqbp_manage');
    }

    /** Guard both matching and omitted platform variants against guest CDN sharing. */
    private static function guardCache(): void
    {
        if (self::$cacheChecked) { return; }
        self::$cacheChecked = true;
        $context = self::context();
        foreach (self::rules() as $rule) {
            if ($rule['platforms'] && Policy::evaluate($rule, $context, false) === null) {
                \IPS\Output::setCacheTime(false);
                break;
            }
        }
        if (self::debug()) { \IPS\Output::setCacheTime(false); }
    }

    /** Called by Loader and the early global head hook (also covers Pages dispatchers). */
    public static function prepare(): string
    {
        if (self::$prepared || !self::enabled()) { return ''; }
        self::$prepared = true;
        try {
            self::guardCache();
            $context = self::context();
            foreach (self::rules() as $rule) {
                $reason = self::reason($rule, $context);
                if ($reason !== null) { self::note($rule, $reason); continue; }
                // CSS uses the original request; global hook dependencies must precede includeCSS/JS.
                if ($rule['mode'] === 'css' || $rule['hook_path'] === 'core/front/global/globalTemplate') { self::render($rule); }
            }
        } catch (\Throwable $error) { self::log($error); }
        return '';
    }

    public static function hook(int $id): string
    {
        try {
            if (!self::enabled()) { return ''; }
            self::guardCache();
            $rule = self::rules()[$id] ?? null;
            if (!$rule || $rule['mode'] !== 'hook' || isset(self::$rendering[$id])) { return ''; }
            $reason = self::reason($rule, self::context());
            if ($reason !== null) { self::note($rule, $reason); return ''; }
            $ordinal = self::$ordinals[$id] = (self::$ordinals[$id] ?? 0) + 1;
            if (!Policy::selectedOccurrence($rule['occurrence'], $rule['occurrence_index'], $ordinal)) { return ''; }
            $html = self::render($rule);
            if ($html === '') { return ''; }
            $wrapper = HookCatalog::wrapper($rule['hook_path'], $rule['hook_name']);
            self::note($rule, 'inserted');
            return '<' . $wrapper . ' data-rqbp-placement="' . $id . '" data-rqbp-rule="' . $id . '">' . $html . '</' . $wrapper . '>';
        } catch (\Throwable $error) { self::log($error); return ''; }
    }

    private static function reason(array $rule, array $context): ?string
    {
        $reason = Policy::evaluate($rule, $context);
        if ($reason !== null) { return $reason; }
        if ($rule['mode'] === 'hook' && !HookCatalog::has($rule['hook_path'], $rule['hook_name'])) { return 'hook_missing'; }
        if ($rule['mode'] === 'css' && trim($rule['selector']) === '') { return 'selector_empty'; }
        return null;
    }

    private static function render(array $rule): string
    {
        $id = $rule['id'];
        if (array_key_exists($id, self::$rendered)) { return self::$rendered[$id]; }
        if (isset(self::$rendering[$id])) { return ''; }
        self::$rendering[$id] = true;
        try {
            try { $block = \IPS\cms\Blocks\Block::load($rule['block_id']); }
            catch (\OutOfRangeException $error) { self::note($rule, 'block_missing'); return self::$rendered[$id] = ''; }
            if (!$block->active) { self::note($rule, 'block_inactive'); return self::$rendered[$id] = ''; }
            if (!$block->can('view')) { self::note($rule, 'block_permission'); return self::$rendered[$id] = ''; }
            $html = \IPS\cms\Blocks\Block::display($block) ?? '';
            self::note($rule, $html === '' ? 'block_empty' : 'ready');
            return self::$rendered[$id] = $html;
        } catch (\Throwable $error) {
            self::note($rule, 'block_error');
            self::log($error);
            return self::$rendered[$id] = '';
        } finally { unset(self::$rendering[$id]); }
    }

    public static function payload(): string
    {
        if (self::$payloadSent || !self::enabled()) { return ''; }
        self::$payloadSent = true;
        self::prepare();
        try {
            $debug = self::debug();
            $rules = [];
            $context = self::context();
            foreach (self::rules() as $rule) {
                if ($rule['mode'] !== 'css' || self::reason($rule, $context) !== null) { continue; }
                $html = self::$rendered[$rule['id']] ?? '';
                if ($html === '' && !$debug) { continue; }
                // The normal final-output pass cannot see placeholders after JSON escaping.
                \IPS\Member::loggedIn()->language()->parseOutputForDisplay($html);
                \IPS\Output::i()->parseFileObjectUrls($html);
                $rules[] = array_intersect_key($rule, array_flip(['id', 'name', 'selector', 'position', 'occurrence', 'occurrence_index', 'sort_order'])) + ['html' => $html];
            }
            if (!$rules && !$debug) { return ''; }
            $url = parse_url((string) \IPS\Request::i()->url());
            $payload = ['page_url' => ($url['path'] ?? '/') . (isset($url['query']) ? '?' . $url['query'] : ''), 'rules' => $rules];
            if ($debug) {
                foreach (self::rules() as $rule) {
                    if (!isset(self::$diagnostics[$rule['id']])) { self::note($rule, $rule['mode'] === 'hook' ? 'hook_not_reached' : 'ready'); }
                }
                $payload['diagnostics'] = array_values(self::$diagnostics);
                $payload['debug'] = true;
                $language = \IPS\Member::loggedIn()->language();
                $payload['strings'] = [
                    'diagnostics' => $language->addToStack('rqbp_front_diagnostics'),
                    'matches' => $language->addToStack('rqbp_front_matches'),
                    'inserted' => $language->addToStack('rqbp_front_inserted'),
                ];
                foreach ($payload['strings'] as &$label) {
                    $language->parseOutputForDisplay($label);
                    $label = $language->stripVLETags($label);
                }
                unset($label);
            }
            $json = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
            $asset = htmlspecialchars(Application::asset('placements.js'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<script type="application/json" data-rqbp-payload>' . $json . '</script><script src="' . $asset . '" defer></script>';
        } catch (\Throwable $error) { self::log($error); return ''; }
    }

    private static function note(array $rule, string $reason): void
    {
        if (!self::debug()) { return; }
        self::$diagnostics[$rule['id']] = ['id' => $rule['id'], 'name' => $rule['name'], 'mode' => $rule['mode'], 'selector' => $rule['selector'], 'status' => $reason, 'reason' => $reason];
    }

    public static function diagnose(Rule $model, ?array $context = null): array
    {
        $rule = $model->toConfig();
        $reason = self::reason($rule, $context ?? self::context());
        return ['id' => $rule['id'], 'name' => $rule['name'], 'status' => $reason ?? 'eligible', 'reason' => $reason];
    }

    private static function log(\Throwable $error): void
    {
        try { \IPS\Log::log($error, 'rqblockplacements'); } catch (\Throwable $ignored) { }
    }
}
