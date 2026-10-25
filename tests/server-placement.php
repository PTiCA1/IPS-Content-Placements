<?php
/** Original fragment parser and placement boundary checks; no IPS installation required. */
define('IPS\SUITE_UNIQUE_KEY', 'server-placement-test');
require __DIR__ . '/../application/sources/ServerDocument/ServerDocument.php';
require __DIR__ . '/../application/sources/ServerSelector/ServerSelector.php';
require __DIR__ . '/../application/sources/ServerPlacement/ServerPlacement.php';

use IPS\rqblockplacements\ServerDocument;
use IPS\rqblockplacements\ServerPlacement;
use IPS\rqblockplacements\ServerSelector;

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) { throw new RuntimeException($message); }
}
function rule(int $id = 1, array $changes = []): array
{
    return $changes + ['id' => $id, 'name' => 'Example', 'selector' => '#target', 'position' => 'after', 'occurrence' => 'first', 'occurrence_index' => 1, 'sort_order' => 0];
}
function wrap(int $id, string $html = '<em>Inserted</em>', string $tag = 'div'): string
{
    return '<' . $tag . ' data-rqbp-placement="' . $id . '" data-rqbp-rule="' . $id . '">' . $html . '</' . $tag . '>';
}
function select(string $html, string $selector): array
{
    return ServerSelector::select(ServerSelector::parse($selector), ServerDocument::parse($html)->nodes);
}

$original = " \n<section DATA-example='A &amp; B' id=target><i-data data-name=\"a>b\">Žluťoučký</i-data><!-- <div id=fake> --><script>const html=\"<div id=fake></div>\";</script><style>.x::after{content:'<div>'}</style><textarea><div id=fake></textarea><img alt='x > y' src='<fileStore.core_Attachment>/image.png' /></section>\n";
$document = ServerDocument::parse($original);
check($document->nodes[0]['synthetic'] && $document->nodes[0]['attrs']['id'] === 'ipsLayout__main', 'The fragment has a virtual native main root');
check($document->nodes[0]['start'] === 0 && $document->nodes[0]['openEnd'] === 0 && $document->nodes[0]['closeStart'] === strlen($original) && $document->nodes[0]['end'] === strlen($original), 'Virtual root offsets cover the original bytes');
check($document->nodes[1]['tag'] === 'section' && $document->nodes[1]['attrs']['data-example'] === 'A & B', 'Tags and attribute names are canonical and attribute values are decoded once');
check(substr($original, $document->nodes[1]['start'], $document->nodes[1]['openEnd'] - $document->nodes[1]['start']) === "<section DATA-example='A &amp; B' id=target>", 'Opening offsets retain exact quote, case and entity spelling');
check(substr($original, $document->nodes[1]['closeStart'], $document->nodes[1]['end'] - $document->nodes[1]['closeStart']) === '</section>', 'Closing offsets point to the exact closing bytes');
check(count($document->nodes) === 7 && select($original, '#fake') === [], 'Comments, JavaScript, CSS and RCDATA strings never create fake elements');
check(select($original, 'i-data[data-name="a>b"]') === [2], 'Native custom elements and quoted angle brackets can be indexed');
check(select('<div data-x="&amp;lt;">Text</div>', '[data-x="&lt;"]') === [1], 'Entity decoding is not recursive');
check(ServerDocument::parse("<div title=\"line\r\nnext\rline\"></div>")->nodes[1]['attrs']['title'] === "line\nnext\nline", 'Literal attribute newlines follow HTML input preprocessing');
check(ServerDocument::parse('<div title="&copy=example &unknown text"></div>')->nodes[1]['attrs']['title'] === '&copy=example &unknown text', 'Literal ampersands and equals-suffixed names are not weakened into entities');
check(ServerDocument::parse('<div title="&#65; &#x41; &copy;"></div>')->nodes[1]['attrs']['title'] === 'A A ©', 'Standard terminated numeric and HTML5 named references match browser values');
check(select('<div>A < 2 and > 1</div>', 'div') === [0, 1], 'Literal less-than text is preserved instead of becoming a tag');
check(select('<div><fileStore.example><___base_url___></div>', 'div') === [0, 1], 'Native unresolved file placeholders remain opaque text');
check(select('<div><template><div id="inert"></div></template><div id="live"></div></div>', '#inert, #live') === [4], 'Inert template contents are excluded from the active tree');
$foreign = '<div><svg viewBox="0 0 10 10"><title>Icon</title><g><path d="M0 0" /></g></svg><math><mi>x</mi></math></div>';
check(count(ServerDocument::parse($foreign)->nodes) === 8, 'Balanced SVG and MathML with foreign self-closing nodes remain indexable without serialization');

$malformed = [
    '<div>', '</div>', '<div><p>Text</div></p>', '<div/><div></div>', '<div a="one"b="two"></div>', '<div a="unterminated></div>',
    '<div a=></div>', '<div a=x a=y></div>', '<div><!-- unclosed</div>', '<!--><div>Visible</div>-->', '<!---><div></div>-->',
    '<!-- --!><div></div>-->', '<!-- nested <!-- comment --><div></div>', '<script>unclosed', '<style>x</script>',
    '<script><!--<script>text</script><div></div>', '<script>x</script invalid><div></div>', '<script/>', '<style/>',
    '<p><div>Implicit paragraph close</div></p>', '<ul><li>First<li>Second</li></ul>', '<ul><li><li>Nested</li></li></ul>',
    '<ul><div>Invalid list child</div></ul>', '<li>Orphan list item</li>', '<dl><dt>Term<dd>Definition</dd></dl>',
    '<table><tr><td>Missing tbody</td></tr></table>', '<table>fostered text<tbody><tr><td>x</td></tr></tbody></table>',
    '<table><tbody><div>Fostered element</div></tbody></table>', '<td>Orphan cell</td>', '<select><div>Ignored element</div></select>',
    '<a><div><a>Nested anchor</a></div></a>', '<form><div><form></form></div></form>', '<button><button></button></button>',
    '<h1><h2>Implicit heading close</h2></h1>', '<nobr><div><nobr>Implicit nobr close</nobr></div></nobr>',
    '<p><center>Implicit paragraph close</center></p>', '<p><listing>Implicit paragraph close</listing></p>',
    '<ruby><rt>Text<rp>(</rp></rt></ruby>', '<svg><div>Foreign breakout</div></svg>', '<svg><foreignObject><div>x</div></foreignObject></svg>',
    '<svg><title><div>Foreign integration point</div></title></svg>',
    '<!DOCTYPE html><div></div>', '<?php $GLOBALS["parser_executed"] = true; ?>', '<html><body></body></html>', "<div>\0</div>",
    '<div title="&copy 2026"></div>', '<div title="&amp text"></div>', '<div title="&#65 text"></div>', '<div title="&#x41 text"></div>',
    '<div title="&#x80;"></div>', '<div title="&#0;"></div>',
];
$GLOBALS['parser_executed'] = false;
foreach ($malformed as $index => $html) {
    $calls = 0;
    $result = ServerPlacement::apply($html, [rule()], static function () use (&$calls): string { $calls++; return '<em>Unexpected</em>'; });
    check($result['html'] === $html && $calls === 0 && $result['reports'][0]['reason'] === 'server_html_unsupported' && $result['reports'][0]['inserted'] === 0, 'Malformed or ambiguous original fragment fails closed, fixture ' . $index);
}
check($GLOBALS['parser_executed'] === false, 'Parsing never executes submitted PHP');

$target = '<section id="target"><p>Original</p></section>';
$expectations = [
    'before' => wrap(1) . $target,
    'inside-start' => '<section id="target">' . wrap(1) . '<p>Original</p></section>',
    'inside-end' => '<section id="target"><p>Original</p>' . wrap(1) . '</section>',
    'after' => $target . wrap(1),
];
foreach ($expectations as $position => $expected) {
    $calls = 0;
    $result = ServerPlacement::apply($target, [rule(1, ['position' => $position])], static function () use (&$calls): string { $calls++; return '<em>Inserted</em>'; });
    check($result['html'] === $expected && $calls === 1 && $result['reports'] === [['id' => 1, 'matches' => 1, 'inserted' => 1, 'reason' => 'inserted']], 'Canonical insertion position preserves original bytes: ' . $position);
}
$result = ServerPlacement::apply($original, [rule()], static fn (): string => '<em>Inserted</em>');
check($result['html'] === substr($original, 0, $document->nodes[1]['end']) . wrap(1) . substr($original, $document->nodes[1]['end']), 'Successful placement preserves every original raw script, custom tag, whitespace and UTF-8 byte');
$empty = ServerPlacement::apply('', [rule(1, ['selector' => '#ipsLayout__main', 'position' => 'inside-start'])], static fn (): string => '<em>Inserted</em>');
check($empty['html'] === wrap(1), 'The virtual root accepts an insertion into an empty fragment');
foreach (['before', 'after'] as $position) {
    $result = ServerPlacement::apply($target, [rule(1, ['selector' => '#ipsLayout__main', 'position' => $position])], static fn (): string => '<em>Unexpected</em>');
    check($result['html'] === $target && $result['reports'][0]['reason'] === 'server_context_unsupported', 'The virtual root rejects outer position ' . $position);
}

$list = '<ul id="target"><li id="first">One</li><li>Two</li></ul>';
$result = ServerPlacement::apply($list, [rule(1, ['position' => 'inside-start'])], static fn (): string => '<p>Inserted</p>');
check($result['html'] === '<ul id="target">' . wrap(1, '<p>Inserted</p>', 'li') . '<li id="first">One</li><li>Two</li></ul>', 'List inner insertions use a list item wrapper');
$result = ServerPlacement::apply($list, [rule(1, ['selector' => '#first', 'position' => 'after'])], static fn (): string => '<em>Inserted</em>');
check(str_contains($result['html'], '</li>' . wrap(1, '<em>Inserted</em>', 'li') . '<li>Two'), 'List item siblings use li instead of div');
$result = ServerPlacement::apply($list, [rule(1, ['selector' => '#first', 'position' => 'inside-end'])], static fn (): string => '<em>Inserted</em>');
check(str_contains($result['html'], 'One' . wrap(1) . '</li>'), 'List item children use a div wrapper');

// Original pagination-shaped fixture: no vendor template or forum response data.
$pagination = static fn (string $id): string => '<nav id="' . $id . '"><form action="/example"><ul class="page-controls"><input type="number" name="page" min="1" max="10"><button type="submit">Go</button></ul></form></nav>';
$topic = $pagination('pager-top') . "\n"
    . '<form id="posts"><article data-ips-hook="postWrapper" id="post-one"><div>First example post</div></article>'
    . '<article data-ips-hook="postWrapper" id="post-two"><div>Second example post</div></article></form>' . "\n"
    . $pagination('pager-bottom') . '<script defer src="/example.js"></script>';
$postSelector = '#ipsLayout__main form > article[data-ips-hook="postWrapper"]:nth-of-type(1)';
$topicDocument = ServerDocument::parse($topic);
$postIds = ServerSelector::select(ServerSelector::parse($postSelector), $topicDocument->nodes);
check(count($postIds) === 1 && $topicDocument->nodes[$postIds[0]]['attrs']['id'] === 'post-one', 'Top and bottom list-based pagination do not prevent the first article selector from matching');
check(count(ServerSelector::select(ServerSelector::parse('.page-controls > input, .page-controls > button'), $topicDocument->nodes)) === 4, 'Both pagination lists retain their direct input and button nodes');
$calls = 0;
$topicResult = ServerPlacement::apply($topic, [rule(1, ['selector' => $postSelector])], static function () use (&$calls): string { $calls++; return '<em>Inserted</em>'; });
$firstPostEnd = $topicDocument->nodes[$postIds[0]]['end'];
check($calls === 1 && $topicResult['reports'] === [['id' => 1, 'matches' => 1, 'inserted' => 1, 'reason' => 'inserted']], 'Native pagination permits one lazy server placement beside the first article');
check($topicResult['html'] === substr($topic, 0, $firstPostEnd) . wrap(1) . substr($topic, $firstPostEnd), 'The article placement preserves every original pagination, form and script byte');
check(strpos($topicResult['html'], wrap(1)) < strpos($topicResult['html'], '<script defer'), 'The placement is already in the response before deferred browser code');
$calls = 0;
$topicUnsafe = ServerPlacement::apply($topic, [rule(1, ['selector' => '.page-controls > input, .page-controls > button', 'occurrence' => 'all'])], static function () use (&$calls): string { $calls++; return '<em>Unexpected</em>'; });
check($calls === 0 && $topicUnsafe['html'] === $topic && $topicUnsafe['reports'][0]['matches'] === 4 && $topicUnsafe['reports'][0]['reason'] === 'server_context_unsupported', 'Direct pagination controls remain unsafe insertion targets and never execute their renderer');
foreach (['ul', 'ol', 'menu'] as $listTag) {
    $controls = '<' . $listTag . ' id="target"><input name="page" type="number"><button type="submit">Go</button></' . $listTag . '>';
    $controlsResult = ServerPlacement::apply($controls, [rule(1, ['position' => 'inside-start'])], static fn (): string => '<em>Inserted</em>');
    check($controlsResult['html'] === '<' . $listTag . ' id="target">' . wrap(1, '<em>Inserted</em>', 'li') . '<input name="page" type="number"><button type="submit">Go</button></' . $listTag . '>', 'Control-bearing list keeps the safe li insertion wrapper: ' . $listTag);
}

$result = ServerPlacement::apply('<p id="target">Text</p>', [rule()], static fn (): string => '<em>Inserted</em>');
check($result['html'] === '<p id="target">Text</p>' . wrap(1), 'Paragraph sibling insertions are allowed in a flow parent');

$unsafe = [
    '<p id="target">Text</p>' => ['selector' => '#target', 'position' => 'inside-end'],
    '<span><div id="target"></div></span>' => [],
    '<a href="/"><div id="target"></div></a>' => [],
    '<table><tbody><tr><td><div id="target"></div></td></tr></tbody></table>' => [],
    '<svg><g id="target"></g></svg>' => [],
    '<div id="target"></div><span id="unsafe"></span>' => ['selector' => '#target, #unsafe', 'occurrence' => 'all'],
];
foreach ($unsafe as $html => $changes) {
    $calls = 0;
    $result = ServerPlacement::apply($html, [rule(1, $changes)], static function () use (&$calls): string { $calls++; return 'Unexpected'; });
    check($result['html'] === $html && $calls === 0 && $result['reports'][0]['matches'] > 0 && $result['reports'][0]['inserted'] === 0 && $result['reports'][0]['reason'] === 'server_context_unsupported', 'Unsafe selected contexts are rejected before rendering: ' . $html);
}

$many = '<div class="target" id="one"></div><div data-rqbp-placement="99"><div class="target" id="owned"></div></div><div class="target" id="two"></div><div class="target" id="three"></div>';
$result = ServerPlacement::apply($many, [rule(1, ['selector' => '.target', 'occurrence' => 'nth', 'occurrence_index' => 2])], static fn (): string => '<em>Inserted</em>');
check(str_contains($result['html'], 'id="two"></div>' . wrap(1)) && $result['reports'][0]['matches'] === 3 && $result['reports'][0]['inserted'] === 1, 'Nth occurrence ignores whole previously owned subtrees');
$calls = 0;
$result = ServerPlacement::apply($many, [rule(1, ['selector' => '.target, #one', 'occurrence' => 'all'])], static function () use (&$calls): string { $calls++; return '<div class="target">New target</div>'; });
check($calls === 1 && $result['reports'][0]['matches'] === 3 && $result['reports'][0]['inserted'] === 3, 'Comma groups deduplicate and all occurrences reuse one lazy render');
check(substr_count($result['html'], 'data-rqbp-placement="1"') === 3, 'Injected matches are never selected recursively');
$result = ServerPlacement::apply($many, [rule(1, ['selector' => '.target', 'occurrence' => 'nth', 'occurrence_index' => 9])], static fn (): string => 'Unexpected');
check($result['html'] === $many && $result['reports'][0]['reason'] === 'server_occurrence_missing' && $result['reports'][0]['matches'] === 3, 'Missing nth occurrence is reported separately from a selector with no matches');
$result = ServerPlacement::apply($target, [rule(1, ['selector' => '#missing']), rule(2, ['selector' => '#target:hover'])], static fn (): string => 'Unexpected');
check($result['html'] === $target && $result['reports'][0]['reason'] === 'server_no_match' && $result['reports'][1]['reason'] === 'server_selector_invalid', 'No match and unsupported selector syntax are distinct and leave the fragment unchanged');
$result = ServerPlacement::apply($target, [rule(1, ['selector' => '#target:hover']), rule(2)], static fn (): string => '<em>Inserted</em>');
check($result['html'] === $target . wrap(2) && $result['reports'][0]['reason'] === 'server_selector_invalid' && $result['reports'][1]['inserted'] === 1, 'One invalid selector cannot poison an unrelated valid rule');

$adjacent = '<div id="left"></div><div id="right"></div>';
$result = ServerPlacement::apply($adjacent, [rule(2, ['selector' => '#right', 'position' => 'before']), rule(1, ['selector' => '#left', 'position' => 'after'])], static fn (array $rule): string => '<em>' . $rule['id'] . '</em>');
check($result['html'] === '<div id="left"></div>' . wrap(1, '<em>1</em>') . wrap(2, '<em>2</em>') . '<div id="right"></div>', 'Shared sibling boundary follows physical DOM position instead of rule input order');
$nested = '<section id="parent"><div id="child"></div></section>';
$result = ServerPlacement::apply($nested, [rule(1, ['selector' => '#parent', 'position' => 'inside-end']), rule(2, ['selector' => '#child', 'position' => 'after'])], static fn (array $rule): string => '<em>' . $rule['id'] . '</em>');
check($result['html'] === '<section id="parent"><div id="child"></div>' . wrap(2, '<em>2</em>') . wrap(1, '<em>1</em>') . '</section>', 'Child after and parent inside-end have stable nested boundary order');
$result = ServerPlacement::apply('<div id="target"></div>', [rule(4, ['position' => 'after']), rule(3, ['position' => 'inside-end']), rule(2, ['position' => 'inside-start']), rule(1, ['position' => 'before'])], static fn (array $rule): string => '<em>' . $rule['id'] . '</em>');
check($result['html'] === wrap(1, '<em>1</em>') . '<div id="target">' . wrap(2, '<em>2</em>') . wrap(3, '<em>3</em>') . '</div>' . wrap(4, '<em>4</em>'), 'Four positions on one empty element stay in structural order');
$result = ServerPlacement::apply($target, [rule(3, ['sort_order' => 20]), rule(1, ['sort_order' => 10]), rule(2, ['sort_order' => 10])], static fn (array $rule): string => '<em>' . $rule['id'] . '</em>');
check($result['html'] === $target . wrap(1, '<em>1</em>') . wrap(2, '<em>2</em>') . wrap(3, '<em>3</em>'), 'Shared insertion point uses sort order with stable original ties');
$result = ServerPlacement::apply($target, [rule(1), rule(2, ['selector' => '[data-rqbp-placement]'])], static fn (): string => '<em>Inserted</em>');
check($result['reports'][1]['reason'] === 'server_no_match' && $result['reports'][1]['inserted'] === 0, 'Every rule is planned against the same original tree');

$result = ServerPlacement::apply($target, [rule(1), rule(2), rule(3)], static function (array $rule): string {
    if ($rule['id'] === 1) { throw new RuntimeException('Example rendering failure'); }
    return $rule['id'] === 2 ? '' : '<em>Inserted</em>';
});
check($result['html'] === $target . wrap(3) && array_column($result['reports'], 'reason') === ['server_error', '', 'inserted'], 'Callback errors are isolated and an empty render preserves the caller diagnostic');
foreach (['</div><div>Orphan close</div>', '<div>Unclosed', '<li>Orphan item</li>', '<table><tr><td>Repaired table</td></tr></table>'] as $content) {
    $result = ServerPlacement::apply($target, [rule()], static fn (): string => $content);
    check($result['html'] === $target && $result['reports'][0]['reason'] === 'server_content_unsupported', 'Rendered content cannot escape or invalidate the selected wrapper');
}
$moderation = '<form action="/moderate"><div id="target">Post</div></form>';
$result = ServerPlacement::apply($moderation, [rule()], static fn (): string => '<form action="/example"><input name="example"></form>');
check($result['html'] === $moderation && $result['reports'][0]['reason'] === 'server_context_unsupported', 'A block form cannot become a nested form in a moderation form');
$result = ServerPlacement::apply('<form id="target"></form>', [rule()], static fn (): string => '<form action="/example"><input name="example"></form>');
check($result['reports'][0]['inserted'] === 1, 'A sibling form outside the original form remains allowed');
$script = '<script>window.example="<div></head>";</script><style>.example{content:"<section>"}</style>';
$result = ServerPlacement::apply($target, [rule()], static fn (): string => $script);
check($result['html'] === $target . wrap(1, $script), 'Valid raw script and style output is inserted byte-for-byte');

$calls = 0;
$large = str_repeat('x', ServerDocument::MAX_FRAGMENT_BYTES + 1);
$result = ServerPlacement::apply($large, [rule()], static function () use (&$calls): string { $calls++; return ''; });
check($result['html'] === $large && $calls === 0 && $result['reports'][0]['reason'] === 'server_fragment_too_large', 'The fragment byte limit fails closed before parsing or rendering');
unset($large, $result);
$deep = str_repeat('<div>', ServerDocument::MAX_DEPTH + 1) . str_repeat('</div>', ServerDocument::MAX_DEPTH + 1);
$result = ServerPlacement::apply($deep, [rule()], static fn (): string => 'Unexpected');
check($result['html'] === $deep && $result['reports'][0]['reason'] === 'server_fragment_too_large', 'Deep nesting is bounded before recursive work');
$wide = str_repeat('<div></div>', ServerDocument::MAX_NODES);
$result = ServerPlacement::apply($wide, [rule()], static fn (): string => 'Unexpected');
check($result['html'] === $wide && $result['reports'][0]['reason'] === 'server_fragment_too_large', 'The element count is bounded independently from input size');
unset($wide, $result);
$wide = str_repeat('<div class="target"></div>', ServerPlacement::MAX_INSERTIONS + 1);
$result = ServerPlacement::apply($wide, [rule(1, ['selector' => '.target', 'occurrence' => 'all'])], static fn (): string => 'Unexpected');
check($result['html'] === $wide && $result['reports'][0]['reason'] === 'server_fragment_too_large' && $result['reports'][0]['matches'] === ServerPlacement::MAX_INSERTIONS + 1, 'One all-occurrence rule cannot create unbounded inserts');
unset($wide, $result);
$budgetInput = str_repeat('<div class="target"></div>', 20);
$memoryBefore = memory_get_usage(true);
if (function_exists('memory_reset_peak_usage')) { memory_reset_peak_usage(); }
$result = ServerPlacement::apply($budgetInput, [rule(1, ['selector' => '.target', 'occurrence' => 'all'])], static fn (): string => str_repeat('x', 1048576));
check($result['reports'][0]['reason'] === 'server_fragment_too_large' && $result['reports'][0]['inserted'] === 0, 'The final output byte limit rejects a whole oversized rule');
check($result['html'] === $budgetInput, 'Expansion budget rejection preserves every original byte');
if (function_exists('memory_reset_peak_usage')) {
    check(memory_get_peak_usage(true) - $memoryBefore < 16777216, 'The expansion budget is checked before duplicating rendered content for each target');
}

echo "Server placement tests: {$checks} passed\n";
