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
            if (!is_file($path)) { throw new \RuntimeException('Missing translation fixture'); }
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
    $dictionaries = [];
    foreach (['cs', 'sk', 'pl', 'de'] as $code) {
        $dictionaries[$code] = require dirname(__DIR__) . '/application/resources/lang/' . $code . '.php';
    }
    $resourceDirectory = dirname(__DIR__) . '/application/resources';
    $path = $resourceDirectory . '/lang-cs.xml';
    foreach (['cs', 'cs_CZ', 'cs_CZ.UTF-8', 'cs-CZ', 'cs_CZ@euro', 'Czech', 'Czech_Czech Republic.1250', 'czech_Czechia.utf8'] as $locale) {
        check(Translation::isCzech($locale), 'Recognize Czech locale ' . $locale);
    }
    foreach (['en_US', 'sk_SK', 'cspanish', 'csomething', '', 'en-CZ'] as $locale) {
        check(!Translation::isCzech($locale), 'Do not guess Czech from unrelated locale ' . $locale);
    }
    foreach ([
        'cs' => ['cs', 'CS-cz', 'cs_CZ.UTF-8', 'Czech_Czech Republic.1250'],
        'sk' => ['sk', 'sk_SK', 'sk-SK', 'sk_SK.UTF-8', 'sk_SK@euro', 'Slovak', 'Slovak_Slovakia.1250', 'SLOVAK_Slovakia.utf8'],
        'pl' => ['pl', 'pl_PL', 'pl-PL', 'pl_PL.UTF-8', 'Polish', 'Polish_Poland.1250', ' POLISH_Poland.utf8 '],
        'de' => ['de', 'de_DE.UTF-8', 'de-AT', 'de_AT@euro', 'de_CH.UTF-8', 'de-CH', 'German', 'German_Germany.1252', 'German_Austria.1252', 'German_Switzerland.1252'],
    ] as $code => $locales) {
        foreach ($locales as $locale) {
            check(Translation::languageForLocale($locale) === $code, 'Select the correct bundled dictionary for locale ' . $locale);
        }
    }
    foreach (['en_US', 'en-CZ', 'sl', 'sl_SI', 'Slovenian_Slovenia.1250', 'cz_CZ', 'cspanish', 'csomething',
        'sketch', 'slovakian', 'plenty', 'polished', 'desktop', 'germanic', '', 'Čeština', 'Slovenčina', 'Deutsch'] as $locale) {
        check(Translation::languageForLocale($locale) === null, 'Do not infer a supported locale from a title or unrelated prefix: ' . $locale);
    }
    \IPS\Lang::$installed = [
        1 => new \IPS\Lang(1, 'en_US', 'English'),
        2 => new \IPS\Lang(2, 'cs_CZ.UTF-8', 'Čeština'),
        3 => new \IPS\Lang(3, 'Czech_Czech Republic.1250', 'Czech Windows'),
        4 => new \IPS\Lang(4, 'en_US', 'Čeština'),
        5 => new \IPS\Lang(5, 'sk_SK', 'Slovenčina'),
        6 => new \IPS\Lang(6, 'pl_PL.UTF-8', 'Polski'),
        7 => new \IPS\Lang(7, 'de_DE.UTF-8', 'Deutsch'),
        8 => new \IPS\Lang(8, 'German_Austria.1252', 'Austrian German'),
        9 => new \IPS\Lang(9, 'de_CH.UTF-8', 'Swiss German'),
        10 => new \IPS\Lang(10, 'sl_SI', 'Slovenčina'),
        11 => new \IPS\Lang(11, 'en_US', 'Slovak Polish German'),
        12 => new \IPS\Lang(12, 'Slovak_Slovakia.1250', 'English'),
        13 => new \IPS\Lang(13, 'Polish_Poland.1250', 'English'),
        14 => new \IPS\Lang(14, 'de-AT', 'English'),
        15 => new \IPS\Lang(15, 'German_Switzerland.1252', 'English'),
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
        word(12, 6, 'rqbp_add_rule'), word(13, 7, 'rqbp_add_rule'), word(14, 8, 'rqbp_add_rule'), word(15, 9, 'rqbp_add_rule'),
        word(16, 10, 'rqbp_add_rule'), word(17, 11, 'rqbp_add_rule'), word(18, 12, 'rqbp_add_rule'),
        word(19, 13, 'rqbp_add_rule'), word(20, 14, 'rqbp_add_rule'), word(21, 15, 'rqbp_add_rule'),
        word(22, 5, 'rqbp_platform_tablet', 'Vlastný tablet'), word(23, 5, 'rqbp_platform_desktop', ''),
        word(24, 6, 'rqbp_platform_tablet', 'Własny tablet'), word(25, 6, 'rqbp_platform_desktop', ''),
        word(26, 7, 'rqbp_platform_tablet', 'Eigenes Tablet'), word(27, 7, 'rqbp_platform_desktop', ''),
        word(28, 5, 'rqbp_enabled', null, 1), word(29, 6, 'rqbp_enabled', null, 1), word(30, 7, 'rqbp_enabled', null, 1),
        word(31, 6, 'rqbp_enabled', null, 0, 'otherapp'), word(32, 5, 'unrelated_key'),
    ];
    foreach (\IPS\Db::$rows as $row) {
        \IPS\Lang::$installed[$row['lang_id']]->words[$row['word_key']] = $row['word_custom'] ?: $row['word_default'];
    }
    $before = \IPS\Db::$rows;
    $metadataBefore = array_map(fn ($lang) => [$lang->id, $lang->short, $lang->title], \IPS\Lang::$installed);
    $count = (new Application)->installLanguages(250, 250);
    check($count === 23 && \IPS\Application::$nativeCalls === [[250, 250]], 'The native batch offset, limit and return count are preserved');
    $localizedLanguages = [2 => 'cs', 3 => 'cs', 5 => 'sk', 6 => 'pl', 7 => 'de', 8 => 'de', 9 => 'de', 12 => 'sk', 13 => 'pl', 14 => 'de', 15 => 'de'];
    check(count(\IPS\Db::$rows) === count($before), 'Only existing language rows are updated; no translation rows are created');
    foreach (\IPS\Db::$rows as $index => $row) {
        $code = $localizedLanguages[$row['lang_id']] ?? null;
        $shouldTranslate = $code !== null && $before[$index]['word_custom'] === null && $row['word_app'] === 'rqblockplacements'
            && $row['word_js'] === 0 && array_key_exists($row['word_key'], $dictionaries[$code]);
        check($row['word_default'] === $before[$index]['word_default'] && $row['word_default_version'] === $before[$index]['word_default_version'], 'Keep English defaults for future language creation');
        if ($shouldTranslate) {
            check($row['word_custom'] === $dictionaries[$code][$row['word_key']] && $row['word_custom_version'] === Application::RQBP_BUILD, 'Fill missing ' . $code . ' text with the current version');
            check(\IPS\Lang::$installed[$row['lang_id']]->words[$row['word_key']] === $dictionaries[$code][$row['word_key']], 'Refresh already loaded language words in this request');
        } else {
            check($row === $before[$index], 'Preserve existing overrides, other languages, unrelated keys/apps and JS words');
        }
    }
    check($metadataBefore === array_map(fn ($lang) => [$lang->id, $lang->short, $lang->title], \IPS\Lang::$installed), 'Do not rename languages, alter locales or create new languages');
    foreach (\IPS\Lang::$installed as $id => $language) {
        check($language->cacheClears === (isset($localizedLanguages[$id]) ? 1 : 0), 'Invalidate only a changed supported language once: ' . $language->short);
    }
    $writeCount = count(\IPS\Db::$writes);
    $cachesBefore = array_map(fn ($language) => $language->cacheClears, \IPS\Lang::$installed);
    Translation::installBundled($resourceDirectory);
    check(count(\IPS\Db::$writes) === $writeCount && $cachesBefore === array_map(fn ($language) => $language->cacheClears, \IPS\Lang::$installed), 'Repeated bundled installation is idempotent for every locale');
    Translation::install($path);
    check(count(\IPS\Db::$writes) === $writeCount && \IPS\Lang::$installed[2]->cacheClears === 1, 'The legacy Czech installer remains idempotent');

    // A language created later copies English defaults and clears custom translations.
    foreach ($localizedLanguages as $language => $code) {
        $clonedEnglish = array_map(static fn ($row) => array_replace($row, ['lang_id' => 99, 'word_custom' => null]), array_filter(\IPS\Db::$rows, static fn ($row) => $row['lang_id'] === $language && $row['word_export'] === 1));
        check(array_values($clonedEnglish)[0]['word_default'] === $english['rqbp_add_rule'], 'A newly created language inherits English defaults rather than ' . $code);
    }

    \IPS\Db::$rows[] = word(100, 2, 'rqbp_copy_suffix');
    \IPS\Db::$rows[] = word(101, 5, 'rqbp_copy_suffix');
    Translation::install($path);
    check(\IPS\Db::$rows[count(\IPS\Db::$rows) - 2]['word_custom'] === $dictionaries['cs']['rqbp_copy_suffix'], 'The legacy installer still fills Czech text');
    check(\IPS\Db::$rows[array_key_last(\IPS\Db::$rows)]['word_custom'] === null, 'The legacy Czech-only installer does not fill other locales');
    \IPS\Db::$rows[] = word(102, 6, 'rqbp_copy_suffix');
    \IPS\Db::$rows[] = word(103, 7, 'rqbp_copy_suffix');
    $newKeys = [101 => 'sk', 102 => 'pl', 103 => 'de'];
    Translation::installBundled($resourceDirectory);
    foreach (\IPS\Db::$rows as $row) {
        if (!isset($newKeys[$row['word_id']])) { continue; }
        $code = $newKeys[$row['word_id']];
        check($row['word_custom'] === $dictionaries[$code]['rqbp_copy_suffix'] && $row['word_custom_version'] === Application::RQBP_BUILD, 'A newly added untranslated key is filled during an update for ' . $code);
        check($row['word_default'] === $english['rqbp_copy_suffix'], 'An update preserves the English default of the new ' . $code . ' key');
    }
    $afterNewKeys = \IPS\Db::$rows;
    $newKeyWrites = count(\IPS\Db::$writes);
    Translation::installBundled($resourceDirectory);
    check(\IPS\Db::$rows === $afterNewKeys && count(\IPS\Db::$writes) === $newKeyWrites, 'Repeating an update does not rewrite any newly filled or existing SK/PL/DE translation');

    foreach ([2 => 'cs', 5 => 'sk', 6 => 'pl', 7 => 'de'] as $language => $code) {
        $id = 200 + $language;
        \IPS\Db::$rows[] = word($id, $language, 'rqbp_log_deleted');
        \IPS\Lang::$installed[$language]->words['rqbp_log_deleted'] = $english['rqbp_log_deleted'];
        \IPS\Db::$raceId = $id;
        Translation::installBundled($resourceDirectory);
        check(\IPS\Db::$rows[array_key_last(\IPS\Db::$rows)]['word_custom'] === 'Concurrent administrator text', 'Do not overwrite a concurrent manual translation for ' . $code);
        check(\IPS\Lang::$installed[$language]->words['rqbp_log_deleted'] === $english['rqbp_log_deleted'], 'Do not inject stale automatic text after losing a concurrent update for ' . $code);
    }

    $invalid = tempnam(sys_get_temp_dir(), 'rqbp-language-');
    try {
        file_put_contents($invalid, '<language><app key="otherapp"><word key="rqbp_add_rule" js="0">Foreign text</word></app></language>');
        $beforeInvalid = \IPS\Db::$rows;
        $rejected = false;
        try { Translation::install($invalid); } catch (\Throwable) { $rejected = true; }
        check($rejected && \IPS\Db::$rows === $beforeInvalid, 'The legacy installer rejects a foreign dictionary before writing translations');
    } finally { unlink($invalid); }

    // Every file must be validated before even the first supported language is updated.
    $temporaryDirectory = tempnam(sys_get_temp_dir(), 'rqbp-dictionaries-');
    unlink($temporaryDirectory);
    mkdir($temporaryDirectory);
    try {
        foreach (array_keys($dictionaries) as $code) {
            copy($resourceDirectory . '/lang-' . $code . '.xml', $temporaryDirectory . '/lang-' . $code . '.xml');
        }
        \IPS\Db::$rows[] = word(300, 2, 'rqbp_log_saved');
        foreach (array_keys($dictionaries) as $code) {
            $dictionaryPath = $temporaryDirectory . '/lang-' . $code . '.xml';
            $original = file_get_contents($dictionaryPath);
            file_put_contents($dictionaryPath, '<language><app key="otherapp"><word key="rqbp_log_saved" js="0">Foreign text</word></app></language>');
            $rowsBefore = \IPS\Db::$rows;
            $writesBefore = count(\IPS\Db::$writes);
            $rejected = false;
            try { Translation::installBundled($temporaryDirectory); } catch (\Throwable) { $rejected = true; }
            check($rejected && \IPS\Db::$rows === $rowsBefore && count(\IPS\Db::$writes) === $writesBefore, 'A foreign ' . $code . ' dictionary aborts the whole bundle before any database mutation');
            file_put_contents($dictionaryPath, $original);
        }
        $lastDictionary = $temporaryDirectory . '/lang-de.xml';
        $original = file_get_contents($lastDictionary);
        file_put_contents($lastDictionary, '<language><app key="rqblockplacements"><word key="rqbp_log_saved" js="0">Truncated');
        $rowsBefore = \IPS\Db::$rows;
        $writesBefore = count(\IPS\Db::$writes);
        $rejected = false;
        try { Translation::installBundled($temporaryDirectory); } catch (\Throwable) { $rejected = true; }
        check($rejected && \IPS\Db::$rows === $rowsBefore && count(\IPS\Db::$writes) === $writesBefore, 'A malformed last dictionary does not partially install preceding dictionaries');
        file_put_contents($lastDictionary, $original);
        unlink($lastDictionary);
        $rejected = false;
        try { Translation::installBundled($temporaryDirectory); } catch (\Throwable) { $rejected = true; }
        check($rejected && \IPS\Db::$rows === $rowsBefore && count(\IPS\Db::$writes) === $writesBefore, 'A missing last dictionary also prevents partial installation');
    } finally {
        foreach (glob($temporaryDirectory . '/*') as $file) { unlink($file); }
        rmdir($temporaryDirectory);
    }
    echo "Translation installation boundary tests: {$checks} passed\n";
}
