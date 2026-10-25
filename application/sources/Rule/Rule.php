<?php
/** Content Placements - MIT. */
namespace IPS\rqblockplacements;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

class Rule extends \IPS\Patterns\ActiveRecord
{
    public static ?string $databaseTable = 'rqbp_rules';
    public static string $databasePrefix = 'rule_';
    protected static array $multitons = [];

    public static function all(): array
    {
        $rules = [];
        foreach (\IPS\Db::i()->select('*', static::$databaseTable, null, 'rule_sort_order ASC, rule_id ASC') as $row) { $rules[] = static::constructFromData($row); }
        return $rules;
    }

    public function toConfig(): array { return Policy::normalize($this->_data); }

    public function save(): void
    {
        parent::save();
        static::changed();
    }

    public function delete(): void
    {
        parent::delete();
        static::changed();
    }

    public static function changed(): void
    {
        Runtime::reset();
        HookRegistry::reconcile();
        // IPS object caches are invalidated; external reverse proxies must also be purged.
        \IPS\Data\Cache::i()->clearAll();
    }
}
