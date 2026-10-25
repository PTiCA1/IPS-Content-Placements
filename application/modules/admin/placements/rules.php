<?php
/**
 * Content Placements — rule administration.
 * SPDX-License-Identifier: MIT
 */

namespace IPS\rqblockplacements\modules\admin\placements;

use DomainException;
use InvalidArgumentException;
use IPS\Application as IPSApplication;
use IPS\cms\Blocks\Block;
use IPS\cms\Blocks\Container;
use IPS\Dispatcher;
use IPS\Dispatcher\Controller;
use IPS\Helpers\Form;
use IPS\Helpers\Form\CheckboxSet;
use IPS\Helpers\Form\Node;
use IPS\Helpers\Form\Number;
use IPS\Helpers\Form\Radio;
use IPS\Helpers\Form\Select;
use IPS\Helpers\Form\Text;
use IPS\Helpers\Form\TextArea;
use IPS\Helpers\Form\Url as UrlField;
use IPS\Helpers\Form\YesNo;
use IPS\Helpers\Table\Db as Table;
use IPS\Http\Url;
use IPS\Member;
use IPS\Member\Group;
use IPS\Output;
use IPS\Request;
use IPS\rqblockplacements\Application as PlacementApplication;
use IPS\rqblockplacements\HookCatalog;
use IPS\rqblockplacements\Policy;
use IPS\rqblockplacements\Rule;
use IPS\rqblockplacements\RuleAccess;
use IPS\rqblockplacements\TemplateContent;
use IPS\Session;
use IPS\Settings;
use IPS\Theme;
use OutOfRangeException;

if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

class rules extends Controller
{
    public static bool $csrfProtected = true;

    public function execute(): void
    {
        Dispatcher::i()->checkAcpPermission('rqbp_manage');
        parent::execute();
    }

    protected function manage(): void
    {
        $table = new Table('rqbp_rules', $this->url());
        $table->langPrefix = 'rqbp_table_';
        $table->include = ['rule_name', 'rule_enabled', 'rule_content', 'rule_mode', 'rule_target', 'rule_sort_order'];
        $table->mainColumn = 'rule_name';
        $table->quickSearch = 'rule_name';
        $table->sortBy = $table->sortBy ?: 'rule_sort_order';
        $table->sortDirection = $table->sortDirection ?: 'asc';
        $table->noSort = ['rule_content', 'rule_target'];
        $table->filters = [
            'rqbp_filter_enabled' => ['rule_enabled=?', 1],
            'rqbp_filter_disabled' => ['rule_enabled=?', 0],
            'rqbp_filter_hook' => ['rule_mode=?', 'hook'],
            'rqbp_filter_css' => ['rule_mode=?', 'css'],
        ];
        $table->parsers = [
            'rule_name' => static fn ($value): string => self::escape($value),
            'rule_enabled' => static function ($value): string {
                return self::escape(self::word($value ? 'rqbp_status_enabled' : 'rqbp_status_disabled'));
            },
            'rule_mode' => static function ($value): string {
                return self::escape(self::word($value === 'css' ? 'rqbp_mode_css' : 'rqbp_mode_hook'));
            },
            'rule_content' => static function ($value, array $row): string {
                if (($row['rule_content_type'] ?? 'block') === 'template') {
                    return self::escape(self::word('rqbp_source_template'));
                }
                $value = $row['rule_block_id'] ?? 0;
                if (IPSApplication::appIsEnabled('cms')) {
                    try {
                        $block = Block::load((int) $value);
                        return self::escape(self::translate($block->_title) . ' (' . $block->key . ')');
                    } catch (OutOfRangeException) {
                    }
                }
                return self::escape(self::word('rqbp_block_unavailable') . ' #' . (int) $value);
            },
            'rule_target' => static function ($value, array $row): string {
                $target = $row['rule_mode'] === 'css'
                    ? $row['rule_selector']
                    : $row['rule_hook_path'] . ' · ' . $row['rule_hook_name'];
                return self::escape(self::word($row['rule_position'] === 'before' ? 'rqbp_before' : 'rqbp_after'))
                    . '<br><code>' . self::escape($target) . '</code>';
            },
        ];
        $table->rootButtons = [
            'add' => ['icon' => 'plus', 'title' => 'rqbp_add_rule', 'link' => $this->url('form')],
        ];
        $table->rowButtons = function (array $row): array {
            if (RuleAccess::requiresCodePermission($row) && !RuleAccess::canManageCode()) {
                return [];
            }
            $id = (int) $row['rule_id'];
            return [
                'edit' => ['icon' => 'pencil', 'title' => 'rqbp_edit_rule', 'link' => $this->url('form', $id)],
                'toggle' => [
                    'icon' => $row['rule_enabled'] ? 'pause' : 'play',
                    'title' => $row['rule_enabled'] ? 'rqbp_disable_rule' : 'rqbp_enable_rule',
                    'link' => $this->url('toggle', $id)->csrf(),
                ],
                'diagnostics' => ['icon' => 'stethoscope', 'title' => 'rqbp_diagnostics', 'link' => $this->url('diagnostics', $id)],
                'duplicate' => ['icon' => 'copy', 'title' => 'rqbp_duplicate_rule', 'link' => $this->url('duplicate', $id)->csrf()],
                'up' => ['icon' => 'arrow-up', 'title' => 'rqbp_move_up', 'link' => $this->url('move', $id)->setQueryString('direction', 'up')->csrf()],
                'down' => ['icon' => 'arrow-down', 'title' => 'rqbp_move_down', 'link' => $this->url('move', $id)->setQueryString('direction', 'down')->csrf()],
                'delete' => ['icon' => 'times-circle', 'title' => 'rqbp_delete_rule', 'link' => $this->url('delete', $id), 'data' => ['delete' => '']],
            ];
        };

        Output::i()->title = self::word('rqbp_rules');
        Output::i()->sidebar['actions']['settings'] = [
            'title' => 'rqbp_settings', 'icon' => 'cog',
            'link' => Url::internal('app=rqblockplacements&module=placements&controller=settings'),
        ];
        $notice = !IPSApplication::appIsEnabled('cms') ? 'rqbp_pages_unavailable' : 'rqbp_rules_intro_v5';
        Output::i()->output = Theme::i()->getTemplate('global', 'core', 'admin')->message($notice, 'info') . (string) $table;
    }

    protected function form(): void
    {
        $record = isset(Request::i()->id) ? $this->record() : new Rule;
        $isNew = !isset(Request::i()->id);
        $config = $isNew ? $this->defaults() : $record->toConfig();
        $pagesEnabled = IPSApplication::appIsEnabled('cms');
        $canManageCode = RuleAccess::canManageCode();
        $source = (string) (Request::i()->rqbp_block_source
            ?? ($config['content_type'] === 'template' || (!$pagesEnabled && $canManageCode) ? 'template' : 'picker'));
        // This must precede form construction: native fields validate submitted values immediately.
        if ($source === 'template') {
            RuleAccess::assertCodePermission();
        }
        if (!$pagesEnabled && !$canManageCode) {
            Output::i()->title = self::word('rqbp_rules');
            Output::i()->output = Theme::i()->getTemplate('global', 'core', 'admin')->message('rqbp_pages_unavailable', 'warning');
            return;
        }
        $block = null;
        if ($pagesEnabled && $config['content_type'] === 'block') {
            try {
                $block = Block::load((int) ($config['block_id'] ?? 0));
            } catch (OutOfRangeException) {
            }
        }
        $mode = (string) (Request::i()->rqbp_mode ?? $config['mode']);
        $occurrence = (string) (Request::i()->rqbp_occurrence ?? $config['occurrence']);

        $form = new Form('rqbp_rule_form', 'save', null, [
            'data-rqbp-required' => self::word('rqbp_error_selector'),
            'data-rqbp-too-long' => self::word('rqbp_error_selector_too_long'),
            'data-rqbp-invalid' => self::word('rqbp_error_selector_browser'),
        ]);
        $form->addMessage('rqbp_content_intro');
        if (!$pagesEnabled) {
            $form->addMessage('rqbp_pages_unavailable', 'ipsMessage ipsMessage--warning');
        }
        $form->addHeader('rqbp_section_rule');
        $form->add(new Text('rqbp_name', $config['name'], true, ['maxLength' => 255], static function ($value): void {
            if (trim((string) $value) === '') {
                throw new DomainException('form_required');
            }
        }));
        $form->add(new YesNo('rqbp_rule_enabled', (bool) $config['enabled']));
        $form->add(new Number('rqbp_sort_order', (int) $config['sort_order'], true, ['min' => 0, 'max' => 2147483647, 'decimals' => 0]));

        $form->addHeader('rqbp_content_section');
        $sources = $pagesEnabled ? ['picker' => 'rqbp_source_picker', 'manual' => 'rqbp_source_manual'] : [];
        $sourceToggles = $pagesEnabled ? ['picker' => ['rqbp_block_picker'], 'manual' => ['rqbp_block_reference']] : [];
        if ($canManageCode) {
            $sources['template'] = 'rqbp_source_template';
            $sourceToggles['template'] = ['rqbp_template_code'];
        }
        $sourceField = new Radio('rqbp_block_source', $source, true, ['options' => $sources, 'toggles' => $sourceToggles]);
        $sourceField->label = self::word('rqbp_content_source');
        $form->add($sourceField);
        if ($pagesEnabled) {
            $form->add(new Node('rqbp_block_picker', $block, false, [
                'class' => Container::class, 'showAllNodes' => true,
                'permissionCheck' => static fn ($node): bool => $node instanceof Block,
            ], static function ($value) use ($source): void {
                if ($source === 'picker' && !$value instanceof Block) {
                    throw new DomainException('rqbp_error_block');
                }
            }, null, null, 'rqbp_block_picker'));
            $referenceField = new Text('rqbp_block_reference', $block ? '{block="' . $block->key . '"}' : '', false, ['maxLength' => 255], static function ($value) use ($source): void {
                if ($source !== 'manual') {
                    return;
                }
                try {
                    Block::load(Policy::parseBlockReference((string) $value), 'block_key');
                } catch (InvalidArgumentException | OutOfRangeException) {
                    throw new DomainException('rqbp_error_block_reference');
                }
            }, null, null, 'rqbp_block_reference');
            $referenceField->description = self::word('rqbp_block_reference_help_v10');
            $form->add($referenceField);
        }
        if ($canManageCode) {
            $codeField = new TextArea('rqbp_template_code', $config['template_code'], false,
                ['rows' => 8, 'maxLength' => 65535, 'codeMode' => false],
                static function ($value) use ($source): void {
                    if ($source === 'template') {
                        self::validateTemplate((string) $value);
                    }
                }, null, null, 'rqbp_template_code');
            $codeField->description = self::word('rqbp_template_code_help_v7');
            $form->add($codeField);
        }

        $form->addHeader('rqbp_section_placement');
        $form->add(new Radio('rqbp_mode', $config['mode'], true, [
            'options' => ['hook' => 'rqbp_mode_hook', 'css' => 'rqbp_mode_css'],
            'toggles' => ['hook' => ['rqbp_hook'], 'css' => ['rqbp_selector']],
        ]));
        $hookOptions = ['' => self::word('rqbp_choose_hook')];
        foreach (HookCatalog::options() as $path => $hooks) {
            foreach ($hooks as $name => $label) {
                $hookOptions[$path][$path . '|' . $name] = $label;
            }
        }
        $hookValue = $config['hook_path'] && $config['hook_name'] ? $config['hook_path'] . '|' . $config['hook_name'] : null;
        if ($hookValue && !HookCatalog::has($config['hook_path'], $config['hook_name'])) {
            $hookOptions[self::word('rqbp_unavailable_hook')][$hookValue] = $config['hook_path'] . ' · ' . $config['hook_name'];
        }
        $form->add(new Select('rqbp_hook', $hookValue, false, ['options' => $hookOptions, 'parse' => 'normal', 'noDefault' => true], static function ($value) use ($mode): void {
            if ($mode !== 'hook') {
                return;
            }
            [$path, $name] = self::splitHook((string) $value);
            if (!HookCatalog::has($path, $name)) {
                throw new DomainException('rqbp_error_hook');
            }
            try {
                HookCatalog::wrapper($path, $name);
            } catch (InvalidArgumentException) {
                throw new DomainException('rqbp_error_hook_context');
            }
        }, null, null, 'rqbp_hook'));
        $form->add(new Text('rqbp_selector', $config['selector'], false, ['maxLength' => 1000], static function ($value) use ($mode): void {
            if ($mode === 'css' && (trim((string) $value) === '' || str_contains((string) $value, "\0"))) {
                throw new DomainException('rqbp_error_selector');
            }
        }, null, null, 'rqbp_selector'));
        $form->add(new Radio('rqbp_position', $config['position'], true, [
            'options' => ['before' => 'rqbp_before', 'after' => 'rqbp_after'],
        ]));
        $form->add(new Radio('rqbp_occurrence', $config['occurrence'], true, [
            'options' => ['first' => 'rqbp_first', 'nth' => 'rqbp_nth', 'all' => 'rqbp_all_occurrences'],
            'toggles' => ['nth' => ['rqbp_occurrence_index']],
        ]));
        $form->add(new Number('rqbp_occurrence_index', (int) $config['occurrence_index'], false, ['min' => 1, 'max' => 10000, 'decimals' => 0], static function ($value) use ($occurrence): void {
            if ($occurrence === 'nth' && (int) $value < 1) {
                throw new DomainException('rqbp_error_occurrence');
            }
        }, null, null, 'rqbp_occurrence_index'));

        $form->addHeader('rqbp_section_audience');
        $platformOptions = array_map(static fn (string $key): string => self::word($key), Policy::platformOptions());
        $form->add(new CheckboxSet('rqbp_platforms', Policy::platforms($config['platforms']), false, [
            'options' => $platformOptions, 'parse' => 'normal',
        ]));
        $groups = Group::groups() ?? [];
        $form->add(new CheckboxSet('rqbp_allow_groups', $this->arrayValue($config['allow_groups']), false, ['options' => $groups, 'parse' => 'normal']));
        $form->add(new CheckboxSet('rqbp_exclude_groups', $this->arrayValue($config['exclude_groups']), false, ['options' => $groups, 'parse' => 'normal']));

        $form->addHeader('rqbp_section_pages');
        $apps = [];
        foreach (IPSApplication::applications() as $application) {
            $apps[$application->directory] = self::translate($application->_title);
        }
        $form->add(new CheckboxSet('rqbp_apps', $this->arrayValue($config['apps']), false, ['options' => $apps, 'parse' => 'normal']));
        $validateUrls = static function ($value): void {
            foreach (Policy::lines($value) as $pattern) {
                if (!str_starts_with($pattern, '/') || str_starts_with($pattern, '//') || str_contains($pattern, '#')
                    || strlen($pattern) > 2000 || preg_match('/[\x00-\x1F\x7F]/', $pattern)) {
                    throw new DomainException('rqbp_error_url_pattern');
                }
            }
        };
        $form->add(new TextArea('rqbp_include_urls', implode("\n", $this->arrayValue($config['include_urls'])), false, ['rows' => 4], $validateUrls));
        $form->add(new TextArea('rqbp_exclude_urls', implode("\n", $this->arrayValue($config['exclude_urls'])), false, ['rows' => 4], $validateUrls));

        if ($values = $form->values()) {
            if (($values['rqbp_block_source'] ?? '') === 'template') {
                RuleAccess::assertCodePermission();
            }
            try {
                $selectedSource = (string) ($values['rqbp_block_source'] ?? '');
                if (!array_key_exists($selectedSource, $sources)) {
                    throw new DomainException('rqbp_error_content_source');
                }
                $selectedBlock = null;
                if ($selectedSource !== 'template') {
                    $selectedBlock = $selectedSource === 'manual'
                        ? Block::load(Policy::parseBlockReference((string) $values['rqbp_block_reference']), 'block_key')
                        : $values['rqbp_block_picker'];
                    if (!$selectedBlock instanceof Block) {
                        throw new DomainException('rqbp_error_block');
                    }
                }
                [$hookPath, $hookName] = $values['rqbp_mode'] === 'hook' ? self::splitHook((string) $values['rqbp_hook']) : ['', ''];
                $record->name = trim((string) $values['rqbp_name']);
                $record->enabled = (int) $values['rqbp_rule_enabled'];
                $record->content_type = $selectedSource === 'template' ? 'template' : 'block';
                $record->template_code = $selectedSource === 'template' ? (string) $values['rqbp_template_code'] : '';
                $record->block_id = $selectedBlock ? (int) $selectedBlock->id : 0;
                $record->sort_order = (int) $values['rqbp_sort_order'];
                $record->mode = $values['rqbp_mode'];
                $record->hook_path = $hookPath;
                $record->hook_name = $hookName;
                $record->selector = $values['rqbp_mode'] === 'css' ? trim((string) $values['rqbp_selector']) : '';
                $record->position = $values['rqbp_position'];
                $record->occurrence = $values['rqbp_occurrence'];
                $record->occurrence_index = $values['rqbp_occurrence'] === 'nth' ? (int) $values['rqbp_occurrence_index'] : 1;
                $record->platforms = json_encode(Policy::platforms($values['rqbp_platforms'] ?? []), JSON_THROW_ON_ERROR);
                foreach (['allow_groups', 'exclude_groups', 'apps'] as $field) {
                    $record->$field = json_encode(array_values($values['rqbp_' . $field] ?? []), JSON_THROW_ON_ERROR);
                }
                foreach (['include_urls', 'exclude_urls'] as $field) {
                    $record->$field = json_encode(Policy::lines($values['rqbp_' . $field]), JSON_THROW_ON_ERROR);
                }
                $record->save();
                Session::i()->log('rqbp_log_saved', [$record->name => false]);
                Output::i()->redirect($this->url(), 'saved');
            } catch (DomainException | InvalidArgumentException | OutOfRangeException $exception) {
                $form->error = self::word('rqbp_error_content_save');
            }
        }

        Output::i()->jsFiles[] = PlacementApplication::asset('admin.js');
        Output::i()->title = self::word($isNew ? 'rqbp_add_rule' : 'rqbp_edit_rule');
        Output::i()->output = (string) $form;
    }

    protected function toggle(): void
    {
        Session::i()->csrfCheck();
        $record = $this->record();
        $record->enabled = $record->enabled ? 0 : 1;
        $record->save();
        Session::i()->log('rqbp_log_toggled', [$record->name => false]);
        Output::i()->redirect($this->url(), 'saved');
    }

    protected function duplicate(): void
    {
        Session::i()->csrfCheck();
        $original = $this->record();
        $record = new Rule;
        foreach (array_keys($this->defaults()) as $field) {
            $record->$field = $original->$field;
        }
        $record->name = mb_substr($original->name, 0, 230) . ' — ' . self::word('rqbp_copy_suffix');
        $record->enabled = 0;
        $record->sort_order = $this->nextOrder();
        $record->save();
        Session::i()->log('rqbp_log_duplicated', [$original->name => false]);
        Output::i()->redirect($this->url('form', (int) $record->id), 'rqbp_duplicated_disabled');
    }

    protected function delete(): void
    {
        $record = $this->record();
        Request::i()->confirmedDelete();
        $name = $record->name;
        $record->delete();
        Session::i()->log('rqbp_log_deleted', [$name => false]);
        Output::i()->redirect($this->url(), 'deleted');
    }

    protected function move(): void
    {
        Session::i()->csrfCheck();
        $record = $this->record();
        $direction = (string) Request::i()->direction;
        if (!in_array($direction, ['up', 'down'], true)) {
            Output::i()->error('rqbp_error_order', '2RQBP/2', 400, '');
        }
        $records = array_values(Rule::all());
        $index = null;
        foreach ($records as $position => $candidate) {
            if ((int) $candidate->id === (int) $record->id) {
                $index = $position;
                break;
            }
        }
        $other = $index === null ? -1 : $index + ($direction === 'up' ? -1 : 1);
        if ($index !== null && isset($records[$other])) {
            [$records[$index], $records[$other]] = [$records[$other], $records[$index]];
            RuleAccess::assertReorder($records);
            foreach ($records as $position => $candidate) {
                $order = ($position + 1) * 10;
                if ((int) $candidate->sort_order !== $order) {
                    $candidate->sort_order = $order;
                    $candidate->save();
                }
            }
            Session::i()->log('rqbp_log_reordered');
        }
        Output::i()->redirect($this->url(), 'saved');
    }

    /** Configuration checks only. Rendering is tested on an actual front-end page. */
    protected function diagnostics(): void
    {
        $record = $this->record();
        $config = $record->toConfig();
        $checks = [];
        $checks[] = [self::word('rqbp_diag_application'), (bool) Settings::i()->rqbp_enabled];
        $checks[] = [self::word('rqbp_diag_rule'), (bool) $record->enabled];
        if ($config['content_type'] === 'template') {
            $valid = true;
            try {
                TemplateContent::validate($config['template_code']);
            } catch (InvalidArgumentException) {
                $valid = false;
            }
            $checks[] = [self::word('rqbp_diag_template'), $valid];
        } else {
            $pagesEnabled = IPSApplication::appIsEnabled('cms');
            $checks[] = [self::word('rqbp_diag_pages'), $pagesEnabled];
            $block = null;
            if ($pagesEnabled) {
                try {
                    $block = Block::load((int) $record->block_id);
                } catch (OutOfRangeException) {
                }
            }
            $checks[] = [self::word('rqbp_diag_block'), $block !== null];
            if ($block) {
                $checks[] = [self::word('rqbp_diag_block_active'), (bool) $block->active];
                $checks[] = [self::word('rqbp_diag_block_permission'), (bool) $block->can('view')];
            }
        }
        if ($record->mode === 'hook') {
            $valid = HookCatalog::has($record->hook_path, $record->hook_name);
            if ($valid) {
                try {
                    HookCatalog::wrapper($record->hook_path, $record->hook_name);
                } catch (InvalidArgumentException) {
                    $valid = false;
                }
            }
            $checks[] = [self::word('rqbp_diag_hook'), $valid];
        } else {
            $checks[] = [self::word('rqbp_diag_selector'), trim((string) $record->selector) !== ''];
        }
        $html = '<ul class="ipsList ipsList--reset i-padding_3">';
        foreach ($checks as [$label, $passed]) {
            $html .= '<li class="i-margin-bottom_1"><strong>' . self::escape($label) . ':</strong> '
                . self::escape(self::word($passed ? 'rqbp_diag_pass' : 'rqbp_diag_fail')) . '</li>';
        }
        $html .= '</ul>';
        $html .= Theme::i()->getTemplate('global', 'core', 'admin')->message('rqbp_diag_content_scope', 'info');
        $html .= '<pre class="i-padding_3">' . self::escape(json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) . '</pre>';

        $target = Url::external(rtrim((string) Settings::i()->base_url, '/') . '/');
        $form = new Form('rqbp_diagnostic_form', 'rqbp_prepare_diagnostics');
        $form->add(new UrlField('rqbp_diagnostic_url', $target, true, [], static function ($value): void {
            $base = Url::external((string) Settings::i()->base_url);
            $data = $value instanceof Url ? $value->data : [];
            $basePath = rtrim($base->data['path'] ?? '/', '/');
            $path = $data['path'] ?? '/';
            if (strtolower((string) ($data['host'] ?? '')) !== strtolower((string) ($base->data['host'] ?? ''))
                || strtolower((string) ($data['scheme'] ?? '')) !== strtolower((string) ($base->data['scheme'] ?? ''))
                || ($data['port'] ?? null) !== ($base->data['port'] ?? null)
                || ($basePath !== '' && $path !== $basePath && !str_starts_with($path, $basePath . '/'))) {
                throw new DomainException('rqbp_error_diagnostic_url');
            }
        }));
        if ($values = $form->values()) {
            $target = $values['rqbp_diagnostic_url'];
        }
        $target = $target->setQueryString(['rqbp_diagnostics' => 1, 'rqbp_rule' => (int) $record->id]);
        $html .= (string) $form;
        $html .= '<p class="i-padding_3"><a class="ipsButton ipsButton--primary" target="_blank" rel="noopener" href="'
            . self::escape((string) $target) . '">' . self::escape(self::word('rqbp_open_diagnostics')) . '</a></p>';

        Output::i()->title = self::word('rqbp_diagnostics') . ': ' . $record->name;
        Output::i()->output = $html;
    }

    protected function record(): Rule
    {
        try {
            $record = Rule::load((int) Request::i()->id);
            RuleAccess::assertForRule($record);
            return $record;
        } catch (OutOfRangeException) {
            Output::i()->error('rqbp_error_missing_rule', '2RQBP/1', 404, '');
        }
        throw new OutOfRangeException;
    }

    protected function url(string $action = '', ?int $id = null): Url
    {
        $url = Url::internal('app=rqblockplacements&module=placements&controller=rules');
        if ($action !== '') {
            $url = $url->setQueryString('do', $action);
        }
        return $id === null ? $url : $url->setQueryString('id', $id);
    }

    protected function defaults(): array
    {
        return [
            'name' => '', 'enabled' => 0, 'block_id' => 0, 'content_type' => 'block', 'template_code' => '', 'mode' => 'hook',
            'hook_path' => '', 'hook_name' => '', 'selector' => '', 'position' => 'after',
            'occurrence' => 'first', 'occurrence_index' => 1, 'platforms' => [],
            'allow_groups' => [], 'exclude_groups' => [], 'apps' => [],
            'include_urls' => [], 'exclude_urls' => [], 'sort_order' => $this->nextOrder(),
        ];
    }

    protected function nextOrder(): int
    {
        $order = 0;
        foreach (Rule::all() as $record) {
            $order = max($order, (int) $record->sort_order);
        }
        return min(2147483647, $order + 10);
    }

    protected function arrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    protected static function splitHook(string $value): array
    {
        $separator = strrpos($value, '|');
        return $separator === false ? ['', ''] : [substr($value, 0, $separator), substr($value, $separator + 1)];
    }

    protected static function validateTemplate(string $value): void
    {
        try {
            TemplateContent::validate($value);
        } catch (InvalidArgumentException $exception) {
            $key = $exception->getMessage();
            throw new DomainException(in_array($key, [
                'rqbp_error_template_empty', 'rqbp_error_template_invalid',
                'rqbp_error_template_too_long', 'rqbp_error_template_php_tags',
            ], true) ? $key : 'rqbp_error_template_invalid');
        }
    }

    protected static function word(string $key): string
    {
        return self::translate(Member::loggedIn()->language()->addToStack($key, false));
    }

    protected static function translate(string $value): string
    {
        $language = Member::loggedIn()->language();
        $language->parseOutputForDisplay($value);
        return (string) $language->stripVLETags($value);
    }

    protected static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
