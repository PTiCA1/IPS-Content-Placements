<?php
/** Pages Block Placements — additive beta.5 upgrade. MIT. */
namespace IPS\rqblockplacements\setup\upg_1000005;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

/** Preserve existing block rules and resume safely after a partial schema upgrade. */
class Upgrade
{
    public function step1(): bool
    {
        $columns = [
            [
                'name' => 'rule_content_type',
                'type' => 'VARCHAR',
                'length' => 16,
                'decimals' => null,
                'values' => [],
                'allow_null' => false,
                'default' => 'block',
                'comment' => '',
                'unsigned' => false,
                'auto_increment' => false,
            ],
            [
                'name' => 'rule_template_code',
                'type' => 'MEDIUMTEXT',
                'length' => 0,
                'decimals' => null,
                'values' => [],
                'allow_null' => true,
                'default' => null,
                'comment' => '',
                'unsigned' => false,
                'auto_increment' => false,
            ],
        ];
        foreach ($columns as $column) {
            if (!\IPS\Db::i()->checkForColumn('rqbp_rules', $column['name'])) {
                \IPS\Db::i()->addColumn('rqbp_rules', $column);
            }
        }
        return true;
    }
}
