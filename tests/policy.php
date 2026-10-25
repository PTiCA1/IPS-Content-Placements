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
same('block', Policy::normalize(['rule_block_id' => 9])['content_type'], 'Older records retain Pages as their content source');
same('', Policy::normalize(['rule_block_id' => 9])['template_code'], 'Older records have no custom template');
$custom = '{expression="(new \\IPS\\MafraApi())->getMafraApiId(\'28\')" raw="true"}';
same($custom, Policy::normalize(['rule_content_type' => 'template', 'rule_template_code' => $custom])['template_code'], 'Preserve native template code without evaluation');

$rule = ['enabled' => 1, 'mode' => 'hook', 'platforms' => [], 'allow_groups' => [], 'exclude_groups' => [], 'apps' => [], 'include_urls' => [], 'exclude_urls' => []];
$context = ['app' => 'forums', 'url' => '/topic/42/?page=2', 'platform' => 'Windows', 'groups' => [3, 7]];
same(null, Policy::evaluate($rule, $context), 'Unrestricted rule');
same('disabled', Policy::evaluate(array_replace($rule, ['enabled' => 0]), $context), 'Disabled rule never eligible');
same('application', Policy::evaluate(array_replace($rule, ['apps' => ['cms']]), $context), 'App restriction');
same(null, Policy::evaluate(array_replace($rule, ['apps' => ['forums', 'cms']]), $context), 'App multiselect');
same(null, Policy::evaluate(array_replace($rule, ['allow_groups' => [7]]), $context), 'Secondary group can allow');
same('group_excluded', Policy::evaluate(array_replace($rule, ['allow_groups' => [3], 'exclude_groups' => [7]]), $context), 'Secondary exclusion wins over primary allow');
same('group', Policy::evaluate(array_replace($rule, ['allow_groups' => [9]]), $context), 'No allowed membership');
same(null, Policy::evaluate(array_replace($rule, ['platforms' => ['desktop']]), $context), 'Desktop matches native Windows');
same('platform', Policy::evaluate(array_replace($rule, ['platforms' => ['mobile']]), $context), 'Mobile-only skips desktop');
$unknown = array_replace($context, ['platform' => null]);
same(null, Policy::evaluate($rule, $unknown), 'Unknown device allowed by unrestricted list');
same(null, Policy::evaluate(array_replace($rule, ['platforms' => ['desktop']]), $unknown), 'Unknown device uses documented desktop fallback');
same('platform', Policy::evaluate(array_replace($rule, ['platforms' => ['mobile', 'tablet']]), $unknown), 'Unknown device does not receive mobile/tablet rules');
$phone = array_replace($context, ['platform' => 'Android', 'user_agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36']);
$tablet = array_replace($context, ['platform' => 'Android', 'user_agent' => 'Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 Chrome/130.0 Safari/537.36']);
same(null, Policy::evaluate(array_replace($rule, ['platforms' => ['mobile']]), $phone), 'Android phone receives mobile rule');
same('platform', Policy::evaluate(array_replace($rule, ['platforms' => ['mobile']]), $tablet), 'Android tablet skips mobile rule');
same(null, Policy::evaluate(array_replace($rule, ['platforms' => ['tablet']]), $tablet), 'Android tablet receives tablet rule');
same('platform', Policy::evaluate(array_replace($rule, ['platforms' => ['tablet']]), $phone), 'Android phone skips tablet rule');
same(null, Policy::evaluate(array_replace($rule, ['platforms' => ['Android']]), $phone), 'Legacy Android keeps phones');
same(null, Policy::evaluate(array_replace($rule, ['platforms' => ['Android']]), $tablet), 'Legacy Android keeps tablets');
same('platform', Policy::evaluate(array_replace($rule, ['platforms' => ['Android']]), $context), 'Legacy Android remains restricted');

same(['mobile', 'tablet', 'desktop'], array_keys(Policy::platformOptions()), 'Exactly three selectable device classes');
same([], Policy::platforms('[]'), 'Unrestricted platforms stay unrestricted');
same(['mobile', 'tablet'], Policy::platforms('["Android"]'), 'Legacy Android includes both device types');
same(['mobile'], Policy::platforms(['iPhone', 'iPod', 'Windows Phone', 'BlackBerry', 'Symbian', 'Sailfish', 'Tizen']), 'Legacy phone platforms become mobile');
same(['tablet'], Policy::platforms(['iPad', 'Kindle', 'Kindle Fire', 'PlayBook']), 'Legacy tablet platforms become tablet');
same(['desktop'], Policy::platforms(['Windows', 'Macintosh', 'Linux', 'Chrome OS', '__unknown__']), 'Legacy other platforms use desktop');
same(['desktop'], Policy::platforms(['unexpected nonempty platform']), 'Unknown saved value must not become unrestricted');
same(['mobile', 'tablet', 'desktop'], Policy::platforms(['Android', 'iPad', 'mobile', 'Windows', 'desktop']), 'Mixed versions deduplicate into stable class order');
same(['mobile', 'tablet'], Policy::normalize(['rule_platforms' => '["Android"]'])['platforms'], 'Stored record is normalized before editing or matching');
same('mobile', Policy::deviceType('iPhone', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile/15E148'), 'iPhone');
same('mobile', Policy::deviceType('iPod'), 'Native iPod without raw UA');
same('tablet', Policy::deviceType('iPad', 'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) Mobile/15E148'), 'iPad Mobile token must not mean phone');
same('tablet', Policy::deviceType('Macintosh', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1'), 'iPad-style Macintosh UA retaining Mobile token');
same('desktop', Policy::deviceType('Macintosh', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/18.0 Safari/605.1.15'), 'Pure Macintosh UA remains desktop');
same('mobile', Policy::deviceType('Windows Phone', 'Mozilla/5.0 (Windows Phone 10.0; Android 6.0.1) Mobile Safari/537.36'), 'Windows Phone takes precedence over Android token');
same('tablet', Policy::deviceType('Kindle Fire', 'Mozilla/5.0 (Linux; Android 9; KFMAWI) Silk/80.3 Mobile Safari/537.36'), 'Known Fire tablet takes precedence over Mobile token');
same('tablet', Policy::deviceType(null, 'Mozilla/5.0 (PlayBook; U; RIM Tablet OS 2.1.0)'), 'Tablet identified even without parsed platform');
same('tablet', Policy::deviceType('Windows', 'Mozilla/5.0 (Windows NT 10.0; Tablet PC) Edge/130.0'), 'Explicit tablet marker');
same('desktop', Policy::deviceType('Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; Touch) Edge/130.0'), 'Touch by itself does not imply tablet');
same('desktop', Policy::deviceType(null, null), 'Missing device info uses desktop');
same('desktop', Policy::deviceType(null, 'ExampleBot/1.0'), 'Bot without device markers uses desktop');
same('mobile', Policy::deviceType('Android', 'Mozilla/5.0 (Linux; Android 6.0.1) Chrome/130.0 Mobile Safari/537.36 (compatible; Googlebot/2.1)'), 'Smartphone bot follows device markers');
same('tablet', Policy::deviceType('tablet'), 'Canonical runtime class remains stable');
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
