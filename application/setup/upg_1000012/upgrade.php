<?php
/** Content Placements — native hook positions. MIT. */
namespace IPS\rqblockplacements\setup\upg_1000012;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

/** Widen the position identifier without rewriting any existing rule values. */
class Upgrade
{
    public function step1(): bool
    {
        $table = \IPS\Db::i()->getTableDefinition('rqbp_rules', true);
        $column = $table['columns']['rule_position'] ?? null;
        if (!is_array($column) || strtoupper($column['type'] ?? '') !== 'VARCHAR') {
            throw new \RuntimeException('Unexpected placement-position column');
        }
        if ((int) ($column['length'] ?? 0) < 16) {
            $column['length'] = 16;
            if (\IPS\Db::i()->changeColumn('rqbp_rules', 'rule_position', $column) === false) {
                throw new \RuntimeException('Unable to widen the placement-position column');
            }
        }
        return true;
    }
}
