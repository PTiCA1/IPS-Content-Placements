<?php
/** Original CSS-subset tests using source-ordered node fixtures, no HTML/IPS parser. */
define('IPS\SUITE_UNIQUE_KEY', 'server-selector-test');
require __DIR__ . '/../application/sources/ServerSelector/ServerSelector.php';

use IPS\rqblockplacements\ServerSelector;

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) { throw new RuntimeException($message); }
}
function addNode(array &$nodes, int $id, string $tag, array $attrs, ?int $parent): void
{
    $nodes[$id] = ['tag' => $tag, 'attrs' => $attrs, 'parent' => $parent, 'children' => [],
        'start' => $id * 10, 'openEnd' => $id * 10 + 1, 'closeStart' => $id * 10 + 8, 'end' => $id * 10 + 9, 'synthetic' => $id === 0];
    if ($parent !== null) { $nodes[$parent]['children'][] = $id; }
}
function selected(string $selector, array $nodes): array { return ServerSelector::select(ServerSelector::parse($selector), $nodes); }

$nodes = [];
addNode($nodes, 0, 'div', ['id' => 'ipsLayout__main'], null);
addNode($nodes, 1, 'section', ['id' => 'group', 'class' => "outer\tAlpha", 'lang' => 'en-US', 'data-value' => 'hello world', 'data-empty' => '', 'data-quoted' => 'a,b] > c', 'data-json' => '{"a":[1,2]}'], 0);
addNode($nodes, 2, 'ol', ['id' => 'categories', 'class' => 'ipsBlockSpacer'], 1);
addNode($nodes, 3, 'li', ['id' => 'category1', 'class' => 'category'], 2);
addNode($nodes, 4, 'ol', ['id' => 'forums', 'class' => 'ipsData--category'], 3);
addNode($nodes, 5, 'li', ['id' => 'forum1', 'class' => 'row special'], 4);
addNode($nodes, 6, 'li', ['id' => 'owned', 'data-rqbp-placement' => '9'], 4);
addNode($nodes, 7, 'article', ['id' => 'ownedChild', 'class' => 'ipsEntry', 'data-ips-hook' => 'postWrapper'], 6);
addNode($nodes, 8, 'li', ['id' => 'forum2', 'class' => 'row'], 4);
addNode($nodes, 9, 'li', ['id' => 'category2', 'class' => 'category'], 2);
addNode($nodes, 10, 'li', ['id' => 'category3', 'class' => 'category'], 2);
addNode($nodes, 11, 'ol', ['class' => 'ipsData--category'], 10);
addNode($nodes, 12, 'li', ['id' => 'forum3', 'class' => 'row'], 11);
addNode($nodes, 13, 'ol', ['class' => 'ipsData--topic-list'], 1);
addNode($nodes, 14, 'li', ['id' => 'topic1'], 13);
addNode($nodes, 15, 'li', ['id' => 'topic2'], 13);
addNode($nodes, 16, 'div', ['id' => 'posts', 'class' => 'ipsEntries--topic'], 1);
addNode($nodes, 17, 'span', ['id' => 'marker1'], 16);
addNode($nodes, 18, 'article', ['id' => 'post1', 'class' => 'ipsEntry', 'data-ips-hook' => 'postWrapper'], 16);
addNode($nodes, 19, 'div', ['id' => 'diagnostics', 'data-rqbp-diagnostics' => ''], 16);
addNode($nodes, 20, 'article', ['id' => 'diagnosticChild', 'class' => 'ipsEntry'], 19);
addNode($nodes, 21, 'span', ['id' => 'marker2'], 16);
addNode($nodes, 22, 'article', ['id' => 'post2', 'class' => 'ipsEntry', 'data-ips-hook' => 'postWrapper'], 16);
addNode($nodes, 23, 'article', ['id' => 'post3', 'class' => 'ipsEntry', 'data-ips-hook' => 'postWrapper'], 16);
addNode($nodes, 24, 'p', ['id' => 'only'], 1);
addNode($nodes, 25, 'em', ['id' => 'onlyChild'], 24);
addNode($nodes, 26, 'aside', ['id' => 'aside'], 1);
addNode($nodes, 27, 'ul', ['id' => 'formula'], 1);
for ($index = 1; $index <= 14; $index++) { addNode($nodes, 99 + $index, 'li', ['id' => 'n' . $index], 27); }
addNode($nodes, 200, 'div', ['id' => 'CaseID', 'class' => 'CaseClass caseclass', 'data-count' => '007', 'type' => 'text', 'rel' => 'Next alternate', 'dir' => 'ltr', 'contenteditable' => 'true', 'role' => 'button'], 1);

$cases = [
    '#ipsLayout__main' => [0],
    'DIV#ipsLayout__main > SECTION' => [1],
    '#group.outer.Alpha' => [1],
    '#group.alpha' => [],
    '#caseid' => [],
    '.CaseClass' => [200],
    '.caseclass' => [200],
    'section > *:last-child' => [200],
    '#posts > span:first-child' => [17],
    '#posts > article:last-child' => [23],
    '#onlyChild:only-child' => [25],
    '#aside:only-of-type' => [26],
    '#posts > article:first-of-type' => [18],
    '#posts > article:last-of-type' => [23],
    '#posts > article:only-of-type' => [],
    '#posts > article:nth-of-type(2)' => [22],
    '#posts > article:nth-last-of-type(2)' => [22],
    '#posts > :nth-child(3)' => [21],
    '#posts > :nth-last-child(2)' => [22],
    '#marker1 + article' => [18],
    '#post1 + span' => [21],
    '#post1 + article' => [],
    '#post1 ~ article' => [22, 23],
    '#categories > li + li' => [9, 10],
    '#categories > li ~ li:last-child' => [10],
    '#group .ipsEntry' => [18, 22, 23],
    '#forums > li:nth-child(2)' => [8],
    '#forums > li:first-child' => [5],
    '#forums > li:last-child' => [8],
    '#owned' => [],
    '#ownedChild' => [],
    '#diagnostics' => [],
    '#diagnosticChild' => [],
    '[data-rqbp-placement], [data-rqbp-diagnostics]' => [],
    '[lang]' => [1],
    '[LANG="en-US"]' => [1],
    '[lang="EN-us"]' => [1],
    '[lang|=en]' => [1],
    '[lang|=EN]' => [1],
    '[lang|=de]' => [],
    '[data-value~="hello"]' => [1],
    '[data-value~="hello world"]' => [],
    '[data-value^="hello"]' => [1],
    '[data-value$="world"]' => [1],
    '[data-value*="lo wo"]' => [1],
    '[data-empty]' => [1],
    '[data-empty=""]' => [1],
    '[data-value^=""]' => [],
    '[data-value$=""]' => [],
    '[data-value*=""]' => [],
    '[data-value~=""]' => [],
    '[data-count="007"]' => [200],
    '[type="TEXT"]' => [200],
    '[type^="TE"]' => [200],
    '[type$="XT"]' => [200],
    '[type*="EX"]' => [200],
    '[rel~="NEXT"]' => [200],
    '[dir="LTR"]' => [200],
    '[contenteditable="TRUE"]' => [],
    '[role="BUTTON"]' => [],
    '[data-quoted="a,b] > c"]' => [1],
    '[data-json=\'{"a":[1,2]}\']' => [1],
    '#posts > article:not(#post2)' => [18, 23],
    '#posts > article:not(:first-of-type)' => [22, 23],
    '#group:not([data-quoted="a,b] > c"])' => [],
    '#category3,#forum1,#category3,#forum1' => [5, 10],
    " \t#post3\n, #post1 \r" => [18, 23],
    '#categories > li:nth-child(3),#categories > li:nth-child(1)' => [3, 10],
];
$userSelector = '#ipsLayout__main .ipsBlockSpacer > li:nth-child(3),#ipsLayout__main .ipsData--category > li:nth-child(1),#ipsLayout__main .ipsData--topic-list > li:nth-child(2),#ipsLayout__main .ipsEntries--topic .ipsEntry[data-ips-hook="postWrapper"]:nth-of-type(1)';
$cases[$userSelector] = [5, 10, 12, 15, 18];
foreach ($cases as $selector => $expected) {
    check(selected($selector, $nodes) === $expected, 'Selector mismatch: ' . $selector);
}

$formulas = [
    '1' => [100], '10' => [109], '+2' => [101], '0' => [], '-1' => [],
    'odd' => [100, 102, 104, 106, 108, 110, 112],
    'EVEN' => [101, 103, 105, 107, 109, 111, 113],
    '2n+10' => [109, 111, 113], '2n + 10' => [109, 111, 113],
    "2n\t+\r10" => [109, 111, 113],
    '-n+3' => [100, 101, 102], '-2n+7' => [100, 102, 104, 106],
    '0n+4' => [103], '3n-1' => [101, 104, 107, 110, 113],
    '-2n-1' => [], '1000000000' => [],
    'n' => range(100, 113), '+n' => range(100, 113), 'n-2' => range(100, 113),
];
foreach ($formulas as $formula => $expected) {
    check(selected('#formula > li:nth-child(' . $formula . ')', $nodes) === $expected, 'an+b mismatch: ' . $formula);
}
check(selected('#formula > li:nth-last-child(-n+3)', $nodes) === [111, 112, 113], 'Reverse an+b counts from the end');
check(selected('#posts > article:nth-of-type(-n+2)', $nodes) === [18, 22], 'Type formulas count only same-tag, non-owned siblings');

$invalid = [
    '', ' ', ',', 'div,', ',div', 'div,,p', '> div', 'div >', 'div >> li', 'div +~ li',
    '#', '.', '#123', '.123', 'div|p', '[xml|lang]', '[attr="value" i]', '[attr!=value]',
    '[attr=123]', '[attr=]', '[attr', '[attr="unclosed]', '[attr="a" b]',
    "[attr=\"line\nbreak\"]", '[attr="a\\b"]', '#escaped\\:id',
    ':is(div,p)', ':where(div)', ':has(p)', ':hover', ':focus', ':visited', ':root',
    '::before', ':contains("value")', ':first-child(1)', ':nth-child()', ':nth-child(2 of li)',
    ':nth-child(2 n)', ':nth-child(n 2)', ':nth-child(2n++1)', ':nth-child(odd+1)',
    ':nth-child(2.5n)', ':nth-child(n+a)', ':nth-child((2))', ':nth-child(2',
    ':nth-child(1000000001)', ':nth-child(-1000000001n+2)',
    ':not()', ':not(.a,.b)', ':not(div > p)', ':not(:not(.a))',
    'div<script>', 'div; color:red', '<?php $GLOBALS["selector_probe"]++; ?>',
    "div\0p", str_repeat('a', 1001), implode(',', array_fill(0, 65, '*')), implode(' ', array_fill(0, 65, '*')),
];
$GLOBALS['selector_probe'] = 0;
foreach ($invalid as $selector) {
    $error = null;
    try { ServerSelector::parse($selector); }
    catch (InvalidArgumentException $caught) { $error = $caught; }
    check($error?->getMessage() === 'rqbp_error_server_selector', 'Invalid or unsupported selector was accepted: ' . $selector);
}
check($GLOBALS['selector_probe'] === 0, 'Selector parsing never executes submitted code');
$before = $nodes;
check(selected($userSelector, $nodes) === [5, 10, 12, 15, 18] && $nodes === $before, 'Selection leaves the source node map untouched');

$ownedRoot = $nodes;
$ownedRoot[0]['attrs']['data-rqbp-placement'] = '';
check(selected('*', $ownedRoot) === [], 'An owned root prunes its complete subtree');

$large = [];
addNode($large, 0, 'div', ['id' => 'ipsLayout__main'], null);
for ($index = 1; $index <= 5000; $index++) { addNode($large, $index, 'li', ['class' => 'row'], 0); }
check(count(selected('#ipsLayout__main > li:nth-child(2n+10)', $large)) === 2496, 'Large sibling sets use correct multi-digit an+b coefficients');
check(selected('li:nth-child(2) ~ li:nth-child(-n+4)', $large) === [3, 4], 'General siblings match through a forward pass without ancestor backtracking');
echo "Server selector tests: {$checks} passed\n";
