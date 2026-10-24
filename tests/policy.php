<?php
/** Original standalone policy tests; no IPS source or database is loaded. */
declare(strict_types=1);

define('IPS\SUITE_UNIQUE_KEY', 'rqbp-test');
require dirname(__DIR__) . '/application/sources/Policy/Policy.php';

use IPS\rqblockplacements\Policy;

$checks = 0;
function same(mixed $expected, mixed $actual, string $label): void
{
    global $checks;
    ++$checks;
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function rejects(callable $operation, string $label): void
{
    global $checks;
    ++$checks;
    try { $operation(); } catch (InvalidArgumentException $error) { return; }
    throw new RuntimeException($label . ': unsafe input was accepted');
}

same('welcome', Policy::parseBlockReference('{block="welcome"}'), 'Double-quoted native tag');
same('welcome', Policy::parseBlockReference("{block='welcome'}"), 'Single-quoted native tag');
same('my_block-2', Policy::parseBlockReference('my_block-2'), 'Block key');
foreach (['', '{block="x"}{block="y"}', '<b>{block="x"}</b>', '{expression="phpinfo()"}', '{block="x"} <?php echo 1;', "{block=\"x\nother\"}"] as $unsafe) {
    rejects(static fn() => Policy::parseBlockReference($unsafe), 'Accept exactly one block reference');
}
same([], Policy::lines('[]'), 'Empty JSON list stays unrestricted');
same(['a', 'b'], Policy::lines(" a\r\nb\na\n"), 'URL lines normalized once');
same(['2', '7'], Policy::lines('[2,7]'), 'Serialized group IDs accepted');
same(['0'], Policy::lines([0, '0', '']), 'Zero must not disappear as an empty group value');

$rule = ['enabled' => 1, 'mode' => 'hook', 'platforms' => [], 'allow_groups' => [], 'exclude_groups' => [], 'apps' => [], 'include_urls' => [], 'exclude_urls' => []];
$context = ['app' => 'forums', 'url' => '/topic/42/?page=2', 'platform' => 'Windows', 'groups' => [3, 7]];
same(null, Policy::evaluate($rule, $context), 'Unrestricted rule');
same('disabled', Policy::evaluate(array_replace($rule, ['enabled' => 0]), $context), 'Disabled rule never eligible');
same('application', Policy::evaluate(array_replace($rule, ['apps' => ['cms']]), $context), 'App restriction');
same(null, Policy::evaluate(array_replace($rule, ['apps' => ['forums', 'cms']]), $context), 'App multiselect');
same(null, Policy::evaluate(array_replace($rule, ['allow_groups' => [7]]), $context), 'Secondary group can allow');
same('group_excluded', Policy::evaluate(array_replace($rule, ['allow_groups' => [3], 'exclude_groups' => [7]]), $context), 'Secondary exclusion wins over primary allow');
same('group', Policy::evaluate(array_replace($rule, ['allow_groups' => [9]]), $context), 'No allowed membership');
same(null, Policy::evaluate(array_replace($rule, ['platforms' => ['Windows']]), $context), 'Exact native platform');
same('platform', Policy::evaluate(array_replace($rule, ['platforms' => ['iPhone']]), $context), 'Platform mismatch');
$unknown = array_replace($context, ['platform' => null]);
same(null, Policy::evaluate($rule, $unknown), 'Unknown native platform allowed by unrestricted list');
same(null, Policy::evaluate(array_replace($rule, ['platforms' => ['__unknown__']]), $unknown), 'Explicit unknown platform');
same('platform', Policy::evaluate(array_replace($rule, ['platforms' => ['Windows']]), $unknown), 'Unknown does not guess desktop');
same(null, Policy::evaluate(array_replace($rule, ['include_urls' => ['/topic/*']]), $context), 'Wildcard includes query');
same('url_excluded', Policy::evaluate(array_replace($rule, ['include_urls' => ['/topic/*'], 'exclude_urls' => ['*page=2']]), $context), 'URL exclude wins over include');
same('url', Policy::evaluate(array_replace($rule, ['include_urls' => ['/topic/42/']]), $context), 'Exact URL includes query');
same('url', Policy::evaluate(array_replace($rule, ['include_urls' => ['/topic/[0-9]+/.*']]), $context), 'Patterns are wildcards, not regular expressions');
same(null, Policy::evaluate(array_replace($rule, ['platforms' => ['iPhone'], 'allow_groups' => [99]]), $context, false), 'Route-only evaluation ignores audience');
same('application', Policy::evaluate(array_replace($rule, ['apps' => ['cms']]), $context, false), 'Route-only evaluation still checks app');
same('/topic/42/?a=1&b=2', Policy::relativeUrl('https://example.test/community/topic/42/?a=1&b=2#reply', 'https://example.test/community/'), 'Subfolder board path and query');
same('/community2/', Policy::relativeUrl('https://example.test/community2/', 'https://example.test/community/'), 'Base path boundary');
same('/', Policy::relativeUrl('https://example.test/community/', 'https://example.test/community/'), 'Board root');
same(true, Policy::selectedOccurrence('first', 1, 1), 'First matches once');
same(false, Policy::selectedOccurrence('first', 1, 2), 'First skips subsequent occurrence');
same(false, Policy::selectedOccurrence('nth', 3, 2), 'Nth skips before target');
same(true, Policy::selectedOccurrence('nth', 3, 3), 'Nth matches target');
same(false, Policy::selectedOccurrence('nth', 3, 4), 'Nth skips after target');
same(true, Policy::selectedOccurrence('all', 1, 40), 'All allows later occurrence');
same(false, Policy::selectedOccurrence('all', 1, 0), 'Zero is not an occurrence');
echo "Policy: {$checks} assertions passed.\n";
