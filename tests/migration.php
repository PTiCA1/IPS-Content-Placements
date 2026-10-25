<?php
/** Original schema-upgrade boundary fixtures; no platform implementation is bundled. */
namespace { define('IPS\SUITE_UNIQUE_KEY', 'migration-test'); }

namespace IPS {
    class Db
    {
        public static array $columns = [];
        public static array $rows = [];
        public static array $added = [];
        public static array $changed = [];
        public static ?string $failOn = null;

        public static function i(): self { static $instance; return $instance ??= new self; }

        public function checkForColumn(string $table, string $column): bool
        {
            if ($table !== 'rqbp_rules') { throw new \RuntimeException('Unexpected schema table'); }
            return array_key_exists($column, self::$columns);
        }

        public function addColumn(string $table, array $definition): bool
        {
            if ($table !== 'rqbp_rules') { throw new \RuntimeException('Unexpected schema table'); }
            $name = $definition['name'];
            if (isset(self::$columns[$name])) { throw new \RuntimeException('Attempted to add an existing column'); }
            if (self::$failOn === $name) { throw new \RuntimeException('Simulated interrupted schema upgrade'); }
            self::$columns[$name] = $definition;
            self::$added[] = $name;
            // MySQL fills an added column on existing rows with its declared default.
            foreach (self::$rows as &$row) { $row[$name] = $definition['default']; }
            unset($row);
            return true;
        }

        public function getTableDefinition(string $table, bool $columnsOnly = false): array
        {
            if ($table !== 'rqbp_rules') { throw new \RuntimeException('Unexpected schema table'); }
            return ['columns' => self::$columns];
        }

        public function changeColumn(string $table, string $name, array $definition): bool
        {
            if ($table !== 'rqbp_rules' || $name !== 'rule_position' || !isset(self::$columns[$name])) {
                throw new \RuntimeException('Unexpected schema alteration');
            }
            if (self::$failOn === $name) { throw new \RuntimeException('Simulated interrupted column widening'); }
            self::$columns[$name] = $definition;
            self::$changed[] = $name;
            return true;
        }
    }
}

namespace {
    require dirname(__DIR__) . '/application/setup/upg_1000005/upgrade.php';
    require dirname(__DIR__) . '/application/setup/upg_1000012/upgrade.php';
    use IPS\Db;
    use IPS\rqblockplacements\setup\upg_1000005\Upgrade;
    use IPS\rqblockplacements\setup\upg_1000012\Upgrade as PositionUpgrade;

    $checks = 0;
    function same(mixed $expected, mixed $actual, string $message): void
    {
        global $checks;
        $checks++;
        if ($expected !== $actual) { throw new \RuntimeException($message); }
    }
    function resetDb(array $columns, array $rows): void
    {
        Db::$columns = $columns;
        Db::$rows = $rows;
        Db::$added = [];
        Db::$changed = [];
        Db::$failOn = null;
    }

    $schema = json_decode(file_get_contents(dirname(__DIR__) . '/application/data/schema.json'), true, 512, JSON_THROW_ON_ERROR);
    $newColumns = array_intersect_key($schema['rqbp_rules']['columns'], array_flip(['rule_content_type', 'rule_template_code']));
    same(2, count($newColumns), 'Fresh installations declare both content fields');
    same('VARCHAR', $newColumns['rule_content_type']['type'], 'Content type uses a native string column');
    same(16, $newColumns['rule_content_type']['length'], 'Content type has space for the two supported identifiers');
    same(false, $newColumns['rule_content_type']['allow_null'], 'Every stored rule has a content type');
    same('block', $newColumns['rule_content_type']['default'], 'Existing and new rules default to Pages blocks');
    same('MEDIUMTEXT', $newColumns['rule_template_code']['type'], 'Multibyte template code fits without a database truncation limit');
    same(true, $newColumns['rule_template_code']['allow_null'], 'Existing rules do not require code');
    same(null, $newColumns['rule_template_code']['default'], 'No template code is seeded');
    $data = json_decode(file_get_contents(dirname(__DIR__) . '/application/setup/upg_1000005/data.json'), true, 512, JSON_THROW_ON_ERROR);
    same([1000005, '1.0.0-beta.5'], [$data['id'], $data['name']], 'Upgrade metadata uses the beta.5 build');
    same(['customRoutines'], array_keys(array_filter($data['steps'])), 'Native upgrader schedules only the custom additive routine');
    same(false, $data['forceMainUpgrader'], 'The application can upgrade through its ACP installer');

    $oldRow = ['rule_id' => 7, 'rule_name' => 'Existing block', 'rule_block_id' => 42, 'rule_enabled' => 1,
        'rule_mode' => 'hook', 'rule_hook_path' => 'forums/front/index/index', 'rule_platforms' => '["mobile"]'];
    $oldColumns = array_diff_key($schema['rqbp_rules']['columns'], $newColumns);
    $upgrade = new Upgrade;

    resetDb($oldColumns, [$oldRow]);
    same(true, $upgrade->step1(), 'A clean upgrade finishes in one native step');
    same(['rule_content_type', 'rule_template_code'], Db::$added, 'A clean upgrade adds only the missing columns');
    same(array_replace($oldRow, ['rule_content_type' => 'block', 'rule_template_code' => null]), Db::$rows[0], 'Old rule fields and configured block remain unchanged');
    foreach ($newColumns as $name => $definition) {
        same($definition, Db::$columns[$name], 'Upgrade column matches the fresh-install schema: ' . $name);
    }
    same(true, $upgrade->step1(), 'Re-running a finished upgrade succeeds');
    same(['rule_content_type', 'rule_template_code'], Db::$added, 'Retry does not add or rewrite existing columns');

    foreach ($newColumns as $existingName => $definition) {
        $existingValue = $existingName === 'rule_content_type' ? 'template' : '<p>Existing administrator template</p>';
        resetDb(array_replace($oldColumns, [$existingName => $definition]), [array_replace($oldRow, [$existingName => $existingValue])]);
        same(true, $upgrade->step1(), 'An upgrade resumes when either new column already exists');
        same(array_values(array_diff(array_keys($newColumns), [$existingName])), Db::$added, 'Only the missing column is added after a partial upgrade');
        same($existingValue, Db::$rows[0][$existingName], 'Partial retry preserves already stored content');
        same($oldRow, array_intersect_key(Db::$rows[0], $oldRow), 'Partial retry preserves every original rule field');
    }

    resetDb($oldColumns, [$oldRow]);
    Db::$failOn = 'rule_template_code';
    $interrupted = false;
    try { $upgrade->step1(); } catch (\RuntimeException $error) { $interrupted = true; }
    same(true, $interrupted, 'A database error propagates instead of marking an incomplete upgrade successful');
    same(['rule_content_type'], Db::$added, 'The first successful addition survives an interrupted second addition');
    Db::$failOn = null;
    same(true, $upgrade->step1(), 'The interrupted step can be retried');
    same(['rule_content_type', 'rule_template_code'], Db::$added, 'Retry adds the remaining column once');
    same(array_replace($oldRow, ['rule_content_type' => 'block', 'rule_template_code' => null]), Db::$rows[0], 'Interrupted upgrade keeps the rule usable as its original block');

    resetDb($schema['rqbp_rules']['columns'], []);
    same(true, $upgrade->step1(), 'The step tolerates a fresh database that already has the complete schema');
    same([], Db::$added, 'Complete fresh schema requires no alterations');

    $position = $schema['rqbp_rules']['columns']['rule_position'];
    same(16, $position['length'], 'Fresh installations can store native inside-start and inside-end identifiers');
    $positionData = json_decode(file_get_contents(dirname(__DIR__) . '/application/setup/upg_1000012/data.json'), true, 512, JSON_THROW_ON_ERROR);
    same([1000012, '1.0.0-beta.12'], [$positionData['id'], $positionData['name']], 'Position upgrade metadata uses the beta.12 build');
    same(['customRoutines'], array_keys(array_filter($positionData['steps'])), 'Position widening is scheduled as a native custom routine');
    $legacyColumns = $schema['rqbp_rules']['columns'];
    $legacyColumns['rule_position']['length'] = 8;
    $legacyRows = [array_replace($oldRow, ['rule_position' => 'before']), array_replace($oldRow, ['rule_position' => 'after', 'rule_id' => 8])];
    $positionUpgrade = new PositionUpgrade;
    resetDb($legacyColumns, $legacyRows);
    same(true, $positionUpgrade->step1(), 'An existing short position column is widened');
    same(['rule_position'], Db::$changed, 'Only the position column changes');
    same($position, Db::$columns['rule_position'], 'The widened column matches the clean-install definition');
    same($legacyRows, Db::$rows, 'Existing before/after rules and all other stored values remain unchanged');
    same(true, $positionUpgrade->step1(), 'A completed widening step is retryable');
    same(['rule_position'], Db::$changed, 'A retry performs no additional schema alteration');
    resetDb($legacyColumns, $legacyRows);
    Db::$failOn = 'rule_position';
    $failed = false;
    try { $positionUpgrade->step1(); } catch (\RuntimeException) { $failed = true; }
    same(true, $failed, 'A failed widening does not mark the upgrade complete');
    same($legacyRows, Db::$rows, 'A failed widening preserves original rows');
    Db::$failOn = null;
    same(true, $positionUpgrade->step1(), 'An interrupted widening can be retried');
    same(['rule_position'], Db::$changed, 'The retry widens the column once');
    foreach ([16, 32] as $length) {
        $columns = $schema['rqbp_rules']['columns'];
        $columns['rule_position']['length'] = $length;
        resetDb($columns, $legacyRows);
        same(true, $positionUpgrade->step1(), 'Complete or already wider columns are accepted');
        same([], Db::$changed, 'An already sufficient column is never shrunk or rewritten');
    }
    echo "Schema migration boundary tests: {$checks} passed\n";
}
