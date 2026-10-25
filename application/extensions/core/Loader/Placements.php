<?php
/** Content Placements - MIT. */
namespace IPS\rqblockplacements\extensions\core\Loader;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

class Placements extends \IPS\Extensions\LoaderAbstract
{
    public function onFinish(): void { \IPS\rqblockplacements\Runtime::prepare(); }
}
