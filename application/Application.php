<?php
/** Pages Block Placements - MIT. */
namespace IPS\rqblockplacements;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

class Application extends \IPS\Application
{
    public const RQBP_BUILD = 1000006;

    public static function asset(string $name): string
    {
        if (!in_array($name, ['placements.js', 'admin.js'], true)) { throw new \InvalidArgumentException('Invalid asset'); }
        return \IPS\Http\Url::baseUrl() . 'applications/rqblockplacements/resources/' . $name . '?v=' . self::RQBP_BUILD;
    }

    public function installLanguages(int $offset = null, int $limit = null): int
    {
        $count = parent::installLanguages($offset, $limit);
        Translation::install($this->getApplicationPath() . '/resources/lang-cs.xml');
        return $count;
    }

    public function installCustomTemplates(int $offset = null, int $limit = null): int
    {
        $count = parent::installCustomTemplates($offset, $limit);
        HookRegistry::reconcile();
        return $count;
    }

    /** Runtime rules must never be exported from a developer's forum. */
    public function buildCustomTemplates(): array
    {
        $data = self::staticTemplates();
        static::writeJson($this->getApplicationPath() . '/data/customtemplates.json', $data);
        return ['added' => [], 'edited' => [], 'removed' => []];
    }

    public static function staticTemplates(): array
    {
        return [
            'rqbp_prepare' => ['hookpoint' => 'core/front/global/globalTemplate:head', 'type' => 'inside-start', 'key' => '', 'version' => (string) self::RQBP_BUILD, 'content' => '{expression="\\IPS\\rqblockplacements\\Runtime::prepare()" raw="true"}'],
            'rqbp_payload' => ['hookpoint' => 'core/front/global/globalTemplate:body', 'type' => 'inside-end', 'key' => '', 'version' => (string) self::RQBP_BUILD, 'content' => '{expression="\\IPS\\rqblockplacements\\Runtime::payload()" raw="true"}'],
        ];
    }
}
