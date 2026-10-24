<?php
/** Pages Block Placements - MIT. */
namespace IPS\rqblockplacements;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

final class HookRegistry
{
    public static function reconcile(): void
    {
        if (!\IPS\Db::i()->checkForTable('rqbp_rules')) { return; }
        HookCatalog::reset();
        $wanted = [];
        foreach (Rule::all() as $model) {
            $rule = $model->toConfig();
            if ($rule['mode'] !== 'hook' || !HookCatalog::has($rule['hook_path'], $rule['hook_name'])) { continue; }
            $wanted['rqbp_rule_' . $rule['id']] = [
                'hookpoint' => $rule['hook_path'] . ':' . $rule['hook_name'],
                'type' => $rule['position'],
                'content' => '{expression="\\IPS\\rqblockplacements\\Runtime::hook(' . $rule['id'] . ')" raw="true"}',
                'order' => $rule['sort_order'],
            ];
        }
        foreach (\IPS\Db::i()->select('*', 'core_theme_templates_custom', ['template_app=?', 'rqblockplacements']) as $row) {
            if (!str_starts_with($row['template_name'], 'rqbp_rule_')) { continue; }
            if (!isset($wanted[$row['template_name']])) {
                \IPS\Theme\CustomTemplate::constructFromData($row)->delete();
            }
        }
        foreach ($wanted as $name => $data) {
            try {
                $row = \IPS\Db::i()->select('*', 'core_theme_templates_custom', ['template_app=? AND template_name=?', 'rqblockplacements', $name])->first();
                $template = \IPS\Theme\CustomTemplate::constructFromData($row);
            } catch (\UnderflowException $e) { $template = new \IPS\Theme\CustomTemplate; }
            $template->app = 'rqblockplacements';
            $template->set_id = 0;
            $template->name = $name;
            $template->hookpoint = $data['hookpoint'];
            $template->hookpoint_type = $data['type'];
            $template->content = $data['content'];
            $template->order = $data['order'];
            $template->version = Application::RQBP_BUILD;
            $template->updated = time();
            $template->save();
        }
        unset(\IPS\Data\Store::i()->custom_templates, \IPS\Data\Store::i()->custom_templates_rows);
    }
}
