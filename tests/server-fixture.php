<?php
/** Original CLI-only browser fixture; never included in the application TAR. */
if (PHP_SAPI !== 'cli') { exit; }
define('IPS\SUITE_UNIQUE_KEY', 'server-browser-fixture');
foreach (['Policy', 'ServerSelector', 'ServerDocument', 'ServerPlacement'] as $class) {
    require dirname(__DIR__) . '/application/sources/' . $class . '/' . $class . '.php';
}
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (($input['operation'] ?? 'place') === 'select') {
    $document = \IPS\rqblockplacements\ServerDocument::parse($input['html']);
    $results = [];
    foreach ($input['selectors'] as $selector) {
        $ids = \IPS\rqblockplacements\ServerSelector::select(\IPS\rqblockplacements\ServerSelector::parse($selector), $document->nodes);
        $results[] = array_map(static fn ($id) => $document->nodes[$id]['attrs']['id'] ?? $document->nodes[$id]['tag'], $ids);
    }
    echo json_encode($results, JSON_THROW_ON_ERROR);
} else {
    echo json_encode(\IPS\rqblockplacements\ServerPlacement::apply($input['html'], $input['rules'], static fn ($rule) => $rule['html']), JSON_THROW_ON_ERROR);
}
