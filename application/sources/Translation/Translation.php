<?php
/** Content Placements — bundled translations. MIT. */
namespace IPS\rqblockplacements;

use InvalidArgumentException;
use IPS\Db;
use IPS\Lang;
use IPS\Xml\XMLReader;
use RuntimeException;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

/** Fill missing translations without changing English defaults or user edits. */
final class Translation
{
    private const APP = 'rqblockplacements';
    private const LOCALES = ['cs' => 'czech', 'sk' => 'slovak', 'pl' => 'polish', 'de' => 'german'];

    /** Only these shipped beta.1–8 texts may be renamed during an upgrade. */
    private const LEGACY_BRANDING = [
        'en' => [
            '__app_rqblockplacements' => 'Pages Block Placements',
            'menutab__rqblockplacements' => 'Block placements',
            'menu__rqblockplacements_placements' => 'Block placements',
            'r__rqblockplacements' => 'Pages Block Placements',
            'r__placements' => 'Block placements',
            'r__rqbp_manage' => 'Manage Pages block placements',
            'rqbp_settings' => 'Block placement settings',
            'rqbp_front_diagnostics' => 'Block placement diagnostics',
            'rqbp_enabled' => 'Enable Pages Block Placements',
            'rqbp_log_settings' => 'Changed Pages Block Placements settings',
        ],
        'cs' => [
            '__app_rqblockplacements' => 'Pages Block Placements',
            'menutab__rqblockplacements' => 'Umístění bloků',
            'menu__rqblockplacements_placements' => 'Umístění bloků',
            'r__rqblockplacements' => 'Pages Block Placements',
            'r__placements' => 'Umístění bloků',
            'r__rqbp_manage' => 'Spravovat umístění bloků Pages',
            'rqbp_settings' => 'Nastavení umístění bloků',
            'rqbp_front_diagnostics' => 'Diagnostika umístění bloků',
            'rqbp_enabled' => 'Zapnout Pages Block Placements',
            'rqbp_log_settings' => 'Změněno nastavení Pages Block Placements',
        ],
        'sk' => [
            '__app_rqblockplacements' => 'Pages Block Placements',
            'menutab__rqblockplacements' => 'Umiestnenia blokov',
            'menu__rqblockplacements_placements' => 'Umiestnenia blokov',
            'r__rqblockplacements' => 'Pages Block Placements',
            'r__placements' => 'Umiestnenia blokov',
            'r__rqbp_manage' => 'Spravovať umiestnenia blokov Pages',
            'rqbp_settings' => 'Nastavenia umiestnenia blokov',
            'rqbp_front_diagnostics' => 'Diagnostika umiestnenia blokov',
            'rqbp_enabled' => 'Zapnúť Pages Block Placements',
            'rqbp_log_settings' => 'Zmenené nastavenia Pages Block Placements',
        ],
        'pl' => [
            '__app_rqblockplacements' => 'Pages Block Placements',
            'menutab__rqblockplacements' => 'Umieszczanie bloków',
            'menu__rqblockplacements_placements' => 'Umieszczanie bloków',
            'r__rqblockplacements' => 'Pages Block Placements',
            'r__placements' => 'Umieszczanie bloków',
            'r__rqbp_manage' => 'Zarządzanie umieszczaniem bloków Pages',
            'rqbp_settings' => 'Ustawienia umieszczania bloków',
            'rqbp_front_diagnostics' => 'Diagnostyka umieszczania bloków',
            'rqbp_enabled' => 'Włącz Pages Block Placements',
            'rqbp_log_settings' => 'Zmieniono ustawienia Pages Block Placements',
        ],
        'de' => [
            '__app_rqblockplacements' => 'Pages Block Placements',
            'menutab__rqblockplacements' => 'Blockplatzierungen',
            'menu__rqblockplacements_placements' => 'Blockplatzierungen',
            'r__rqblockplacements' => 'Pages Block Placements',
            'r__placements' => 'Blockplatzierungen',
            'r__rqbp_manage' => 'Platzierungen von Pages-Blöcken verwalten',
            'rqbp_settings' => 'Einstellungen für Blockplatzierungen',
            'rqbp_front_diagnostics' => 'Diagnose der Blockplatzierungen',
            'rqbp_enabled' => 'Pages Block Placements aktivieren',
            'rqbp_log_settings' => 'Einstellungen von Pages Block Placements geändert',
        ],
    ];

    /** Match native POSIX/BCP 47 and Windows locale names, never display titles. */
    public static function languageForLocale(string $locale): ?string
    {
        foreach (self::LOCALES as $code => $windowsName) {
            if (preg_match('/^(?:' . $code . '(?:[_\-.@]|$)|' . $windowsName . '(?:[_ .-]|$))/i', trim($locale)) === 1) {
                return $code;
            }
        }
        return null;
    }

    public static function isCzech(string $locale): bool
    {
        return self::languageForLocale($locale) === 'cs';
    }

    /** Retain the original single-language installer for Czech packages. */
    public static function install(string $path): void
    {
        self::apply(['cs' => self::readWords($path)]);
    }

    public static function installBundled(string $directory): void
    {
        // Validate every bundled dictionary before performing any database writes.
        $dictionaries = ['en' => self::readWords(dirname($directory) . '/data/lang.xml')];
        foreach (self::LOCALES as $code => $windowsName) {
            $dictionaries[$code] = self::readWords($directory . '/lang-' . $code . '.xml');
        }
        self::apply($dictionaries, true);
    }

    private static function apply(array $dictionaries, bool $rebrand = false): void
    {
        foreach (Lang::languages() as $language) {
            $code = self::languageForLocale((string) $language->short);
            $fillMissing = $code !== null;
            $english = preg_match('/^(?:en(?:[_\-.@]|$)|english(?:[_ .-]|$))/i', trim((string) $language->short)) === 1;
            $words = $dictionaries[$code ?? 'en'] ?? null;
            if ($words === null) { continue; }
            $legacy = self::LEGACY_BRANDING[$code ?? 'en'];
            if ($code === null && !$english) {
                // An unbundled locale may have its own translations. Only its old
                // application identity uses the same brand in every language.
                $legacy = array_intersect_key($legacy, array_flip(['__app_' . self::APP, 'r__' . self::APP]));
            }
            $languageId = (int) $language->id;
            $changed = false;
            foreach (Db::i()->select(
                'word_id, word_key, word_custom, word_custom_version',
                'core_sys_lang_words',
                ['lang_id=? AND word_app=? AND word_js=?', $languageId, self::APP, 0]
            ) as $row) {
                $key = $row['word_key'];
                if (!array_key_exists($key, $words)) { continue; }
                $where = ['word_id=? AND word_app=? AND lang_id=? AND word_js=?', (int) $row['word_id'], self::APP, $languageId, 0];
                if ($row['word_custom'] === null) {
                    if (!$fillMissing) { continue; }
                    $where[0] .= ' AND word_custom IS NULL';
                } else {
                    // Preserve empty and different user text. Identical old shipped
                    // labels receive the new name; version alone does not prove ownership.
                    $version = (string) ($row['word_custom_version'] ?? '');
                    if (!$rebrand || !isset($legacy[$key]) || $row['word_custom'] !== $legacy[$key]
                        || !in_array($version, ['1000001', '1000002', '1000003', '1000004', '1000005', '1000006', '1000007', '1000008'], true)) {
                        continue;
                    }
                    // The native table may use a case-insensitive collation. Compare
                    // bytes and the original version so concurrent edits always win.
                    $where[0] .= ' AND BINARY word_custom=? AND word_custom_version=?';
                    $where[] = $row['word_custom'];
                    $where[] = $row['word_custom_version'];
                }
                $affected = Db::i()->update(
                    'core_sys_lang_words',
                    ['word_custom' => $words[$key], 'word_custom_version' => Application::RQBP_BUILD],
                    $where
                );
                if ($affected > 0) {
                    $language->words[$key] = $words[$key];
                    $changed = true;
                }
                // A zero count may mean a concurrent user edit won. Leave its cache alone.
            }
            if ($changed) { $language->clearCaches(); }
        }
    }

    /** Read only this application's non-JavaScript strings from its bundled language XML. */
    private static function readWords(string $path): array
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $reader = null;
        try {
            $reader = XMLReader::safeOpen($path);
            if (!is_object($reader)) { throw new RuntimeException('Unable to open bundled translation'); }
            $words = [];
            $appDepth = null;
            $foundApp = false;
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::DOC_TYPE) {
                    throw new InvalidArgumentException('Bundled translation must not declare a document type');
                }
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'app') {
                    if ($reader->getAttribute('key') !== self::APP || $appDepth !== null || $foundApp) {
                        throw new InvalidArgumentException('Unexpected application in bundled translation');
                    }
                    $foundApp = true;
                    $appDepth = $reader->isEmptyElement ? null : $reader->depth;
                    continue;
                }
                if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'app' && $reader->depth === $appDepth) {
                    $appDepth = null;
                    continue;
                }
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'word'
                    || $appDepth === null || $reader->depth !== $appDepth + 1 || $reader->getAttribute('js') !== '0') {
                    continue;
                }
                $key = $reader->getAttribute('key');
                if ($key === null || $key === '' || array_key_exists($key, $words)) {
                    throw new InvalidArgumentException('Invalid or duplicate bundled translation key');
                }
                $words[$key] = $reader->readString();
            }
            if (!$foundApp || libxml_get_errors()) {
                throw new InvalidArgumentException('Malformed bundled translation');
            }
            return $words;
        } finally {
            if (is_object($reader)) { $reader->close(); }
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
