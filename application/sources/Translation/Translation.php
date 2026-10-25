<?php
/** Pages Block Placements — bundled translations. MIT. */
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
        $dictionaries = [];
        foreach (self::LOCALES as $code => $windowsName) {
            $dictionaries[$code] = self::readWords($directory . '/lang-' . $code . '.xml');
        }
        self::apply($dictionaries);
    }

    private static function apply(array $dictionaries): void
    {
        foreach (Lang::languages() as $language) {
            $code = self::languageForLocale((string) $language->short);
            if ($code === null || !isset($dictionaries[$code])) { continue; }
            $words = $dictionaries[$code];
            $languageId = (int) $language->id;
            $changed = false;
            foreach (Db::i()->select(
                'word_id, word_key, word_custom',
                'core_sys_lang_words',
                ['lang_id=? AND word_app=? AND word_js=?', $languageId, self::APP, 0]
            ) as $row) {
                $key = $row['word_key'];
                // An explicitly empty translation is also a user edit and must survive.
                if ($row['word_custom'] !== null || !array_key_exists($key, $words)) { continue; }
                $affected = Db::i()->update(
                    'core_sys_lang_words',
                    ['word_custom' => $words[$key], 'word_custom_version' => Application::RQBP_BUILD],
                    ['word_id=? AND word_app=? AND lang_id=? AND word_js=? AND word_custom IS NULL', (int) $row['word_id'], self::APP, $languageId, 0]
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
