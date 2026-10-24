<?php
/** Original installation-boundary fixtures; no platform implementation is bundled. */
namespace { define('IPS\SUITE_UNIQUE_KEY', 'translation-test'); }

namespace IPS {
    class Application {
        public static array $nativeCalls = [];
        public static int $nativeCount = 23;
        public function getApplicationPath(): string { return dirname(__DIR__) . '/application'; }
        public function installLanguages(int $offset = null, int $limit = null): int {
            self::$nativeCalls[] = [$offset, $limit];
            return self::$nativeCount;
        }
    }
    class Lang {
        public static array $installed = [];
        public array $words = [];
        public int $cacheClears = 0;
        public function __construct(public int $id, public string $short, public string $title) {}
        public static function languages(): array { return self::$installed; }
        public function clearCaches(): void { $this->cacheClears++; }
    }
    class Db {
        public static array $rows = [];
        public static array $writes = [];
        public static ?int $raceId = null;
        public static function i(): self { static $instance; return $instance ??= new self; }
        private function matches(array $row, array $where): bool {
            $argument = 1;
            preg_match_all('/(word_id|word_app|lang_id|word_js)\s*=\s*(\?|0)/i', $where[0], $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $expected = $match[2] === '?' ? $where[$argument++] : 0;
                if ((string) $row[strtolower($match[1])] !== (string) $expected) { return false; }
            }
            if (preg_match('/word_custom\s+IS\s+NULL/i', $where[0]) && $row['word_custom'] !== null) { return false; }
            return true;
        }
        public function select(string $columns, string $table, array $where): array {
            if ($table !== 'core_sys_lang_words') { throw new \RuntimeException('Unexpected translation table'); }
            return array_values(array_filter(self::$rows, fn ($row) => $this->matches($row, $where)));
        }
        public function update(string $table, array $values, array $where): int {
            if ($table !== 'core_sys_lang_words') { throw new \RuntimeException('Language metadata must stay unchanged'); }
            foreach (['word_app', 'lang_id', 'word_js', 'word_id'] as $column) {
                if (!preg_match('/\b' . $column . '\s*=/', $where[0])) { throw new \RuntimeException('Unscoped translation update: ' . $column); }
            }
            if (!preg_match('/word_custom\s+IS\s+NULL/i', $where[0])) { throw new \RuntimeException('An administrator edit must win a concurrent update'); }
            if (array_diff(array_keys($values), ['word_custom', 'word_custom_version'])) { throw new \RuntimeException('English defaults and all other columns must remain unchanged'); }
            self::$writes[] = [$values, $where];
            $changed = 0;
            foreach (self::$rows as &$row) {
                if (!$this->matches($row, $where)) { continue; }
                if ($row['word_id'] === self::$raceId) { $row['word_custom'] = 'Concurrent administrator text'; self::$raceId = null; continue; }
                $row = array_replace($row, $values);
                $changed++;
            }
            unset($row);
            return $changed;
        }
    }
}

namespace IPS\Xml {
    class XMLReader {
        public static function safeOpen(string $path): \XMLReader {
            $reader = new \XMLReader;
            if (!$reader->open($path, null, LIBXML_NONET)) { throw new \RuntimeException('Cannot open translation fixture'); }
            return $reader;
        }
    }
}

namespace {
    require dirname(__DIR__) . '/application/sources/Translation/Translation.php';
    require dirname(__DIR__) . '/application/Application.php';
    use IPS\rqblockplacements\Application;
    use IPS\rqblockplacements\Translation;

    $checks = 0;
    function check(bool $condition, string $message): void {
        global $checks;
        $checks++;
        if (!$condition) { throw new \RuntimeException($message); }
    }
    $english = require dirname(__DIR__) . '/application/resources/lang/en.php';
    $czech = require dirname(__DIR__) . '/application/resources/lang/cs.php';
    $path = dirname(__DIR__) . '/application/resources/lang-cs.xml';
    foreach (['cs', 'cs_CZ', 'cs_CZ.UTF-8', 'cs-CZ', 'cs_CZ@euro', 'Czech', 'Czech_Czech Republic.1250', 'czech_Czechia.utf8'] as $locale) {
        check(Translation::isCzech($locale), 'Recognize Czech locale ' . $locale);
    }
    foreach (['en_US', 'sk_SK', 'cspanish', 'csomething', '', 'en-CZ'] as $locale) {
        check(!Translation::isCzech($locale), 'Do not guess Czech from unrelated locale ' . $locale);
    }
    \IPS\Lang::$installed = [
        1 => new \IPS\Lang(1, 'en_US', 'English'),
        2 => new \IPS\Lang(2, 'cs_CZ.UTF-8', 'Čeština'),
        3 => new \IPS\Lang(3, 'Czech_Czech Republic.1250', 'Czech Windows'),
        4 => new \IPS\Lang(4, 'en_US', 'Čeština'),
        5 => new \IPS\Lang(5, 'sk_SK', 'Slovenčina'),
    ];
    function word(int $id, int $language, string $key, ?string $custom = null, int $js = 0, string $app = 'rqblockplacements'): array {
        global $english;
        return ['word_id' => $id, 'lang_id' => $language, 'word_app' => $app, 'word_key' => $key, 'word_default' => $english[$key] ?? 'Foreign default',
            'word_custom' => $custom, 'word_default_version' => Application::RQBP_BUILD, 'word_custom_version' => $custom === null ? null : 1000001, 'word_js' => $js, 'word_export' => 1];
    }
    \IPS\Db::$rows = [
        word(1, 1, 'rqbp_add_rule'), word(2, 2, 'rqbp_add_rule'), word(3, 2, 'rqbp_platform_mobile'),
        word(4, 2, 'rqbp_platform_tablet', 'Vlastní tablet'), word(5, 2, 'rqbp_platform_desktop', ''),
        word(6, 2, 'rqbp_enabled', null, 1), word(7, 2, 'rqbp_add_rule', null, 0, 'otherapp'),
        word(8, 2, 'unrelated_key'), word(9, 3, 'rqbp_add_rule'), word(10, 4, 'rqbp_add_rule'), word(11, 5, 'rqbp_add_rule'),
    ];
    foreach (\IPS\Db::$rows as $row) {
        \IPS\Lang::$installed[$row['lang_id']]->words[$row['word_key']] = $row['word_custom'] ?: $row['word_default'];
    }
    $before = \IPS\Db::$rows;
    $metadataBefore = array_map(fn ($lang) => [$lang->id, $lang->short, $lang->title], \IPS\Lang::$installed);
    $count = (new Application)->installLanguages(250, 250);
    check($count === 23 && \IPS\Application::$nativeCalls === [[250, 250]], 'The native batch offset, limit and return count are preserved');
    foreach (\IPS\Db::$rows as $index => $row) {
        $shouldTranslate = in_array($row['word_id'], [2, 3, 9], true);
        check($row['word_default'] === $before[$index]['word_default'] && $row['word_default_version'] === $before[$index]['word_default_version'], 'Keep English defaults for future language creation');
        if ($shouldTranslate) {
            check($row['word_custom'] === $czech[$row['word_key']] && $row['word_custom_version'] === Application::RQBP_BUILD, 'Fill the missing Czech text with the current version');
            check(\IPS\Lang::$installed[$row['lang_id']]->words[$row['word_key']] === $czech[$row['word_key']], 'Refresh already loaded language words in this request');
        } else {
            check($row === $before[$index], 'Preserve existing overrides, other languages, unrelated keys/apps and JS words');
        }
    }
    check($metadataBefore === array_map(fn ($lang) => [$lang->id, $lang->short, $lang->title], \IPS\Lang::$installed), 'Do not rename languages, alter locales or create new languages');
    check(\IPS\Lang::$installed[2]->cacheClears === 1 && \IPS\Lang::$installed[3]->cacheClears === 1, 'Invalidate each changed Czech language once');
    check(\IPS\Lang::$installed[1]->cacheClears === 0 && \IPS\Lang::$installed[4]->cacheClears === 0 && \IPS\Lang::$installed[5]->cacheClears === 0, 'Leave unrelated language caches untouched');
    $writeCount = count(\IPS\Db::$writes);
    Translation::install($path);
    check(count(\IPS\Db::$writes) === $writeCount && \IPS\Lang::$installed[2]->cacheClears === 1, 'Repeated installation is idempotent');

    // A language created later copies English defaults and clears custom translations.
    $clonedEnglish = array_map(static fn ($row) => array_replace($row, ['lang_id' => 6, 'word_custom' => null]), array_filter(\IPS\Db::$rows, static fn ($row) => $row['lang_id'] === 2 && $row['word_export'] === 1));
    check(array_values($clonedEnglish)[0]['word_default'] === $english['rqbp_add_rule'], 'New non-Czech languages inherit English rather than Czech');

    \IPS\Db::$rows[] = word(12, 2, 'rqbp_copy_suffix');
    \IPS\Db::$raceId = 12;
    Translation::install($path);
    check(\IPS\Db::$rows[array_key_last(\IPS\Db::$rows)]['word_custom'] === 'Concurrent administrator text', 'Do not overwrite a concurrent manual translation');
    check((\IPS\Lang::$installed[2]->words['rqbp_copy_suffix'] ?? null) !== $czech['rqbp_copy_suffix'], 'Do not inject stale automatic text after losing a concurrent update');

    $invalid = tempnam(sys_get_temp_dir(), 'rqbp-language-');
    try {
        file_put_contents($invalid, '<language><app key="otherapp"><word key="rqbp_add_rule" js="0">Foreign text</word></app></language>');
        $beforeInvalid = \IPS\Db::$rows;
        $rejected = false;
        try { Translation::install($invalid); } catch (\Throwable) { $rejected = true; }
        check($rejected && \IPS\Db::$rows === $beforeInvalid, 'Reject a foreign dictionary before writing translations');
    } finally { unlink($invalid); }
    echo "Translation installation boundary tests: {$checks} passed\n";
}
