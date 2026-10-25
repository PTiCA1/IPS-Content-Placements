<?php
/** Pure HEAD-fragment grammar checks, without a browser or executable content. */
define('IPS\SUITE_UNIQUE_KEY', 'head-content-test');
require __DIR__ . '/../application/sources/HeadContent/HeadContent.php';

use IPS\rqblockplacements\HeadContent;

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) { throw new RuntimeException($message); }
}

$valid = [
    'meta' => '<meta charset="utf-8">',
    'uppercase meta' => '<META CHARSET="utf-8">',
    'self-closing meta' => '<meta name="description" content="Example" />',
    'unquoted attributes' => '<meta name=description content=example>',
    'attribute whitespace' => "<meta\tname \n= 'description'\rcontent=\"Example\">",
    'quoted greater-than' => '<meta name="description" content="A > B">',
    'single-quoted greater-than' => "<link rel='stylesheet' href='asset.css?value=>'>",
    'quoted HTML strings' => '<meta name="description" content="<div></head>">',
    'empty attribute value' => '<meta name="description" content="">',
    'link' => '<link rel="stylesheet" href="/assets/site.css">',
    'self-closing link' => '<link rel="preload" href="/assets/site.js" as="script"/>',
    'boolean attributes' => '<script async defer nomodule src="/assets/legacy.js"></script>',
    'inline script' => '<script>window.headExample=true;</script>',
    'empty script' => '<script></script>',
    'module script' => '<script type="module">export const example=1;</script>',
    'mixed case closing' => '<ScRiPt>const example=1;</sCrIpT>',
    'closing whitespace' => "<script>const example=1;</script \t\n\r\f>",
    'JS HTML strings' => '<script>const html="<div>Content</div></head><body>";</script>',
    'raw nonclosing name' => '<script>const text="</scripture>";</script>',
    'legacy comment wrapper without nested script' => "<script><!--\nconst example=1;\n//--></script>",
    'inline CSS' => '<style>.example { color: red; }</style>',
    'empty style' => '<style></style>',
    'CSS HTML strings' => '<style>.example::after { content: "<div></head>"; }</style>',
    'case-insensitive style close' => "<STYLE>.example{color:red}</sTyLe\n>",
    'HTML whitespace' => " \t\r\n\f<meta charset=utf-8> \t\r\n\f",
    'empty comment around metadata' => '<!----><meta charset=utf-8><!---->',
    'comment with visible markup' => '<!-- <div>Only a comment</div> --><meta charset=utf-8>',
    'multiple metadata nodes' => '<meta charset="utf-8"><link rel="stylesheet" href="/site.css"><script src="/site.js"></script><style>body{margin:0}</style>',
    'large quoted value' => '<meta content="' . str_repeat('text > ', 2048) . '">',
];
foreach ($valid as $name => $html) {
    check(HeadContent::isValid($html), 'Expected valid HEAD content: ' . $name);
}

$invalid = [
    'empty fragment' => '',
    'whitespace only' => " \t\r\n\f",
    'comment only' => '<!-- comment -->',
    'multiple comments only' => '<!----> <!-- another -->',
    'visible text' => 'Visible text',
    'visible text after metadata' => '<meta charset=utf-8>Visible text',
    'entity text' => '&#32;<meta charset=utf-8>',
    'non-HTML whitespace' => "\v<meta charset=utf-8>",
    'div' => '<div>Content</div>',
    'iframe' => '<iframe src="/embed"></iframe>',
    'head wrapper' => '<head><meta charset=utf-8></head>',
    'html wrapper' => '<html><meta charset=utf-8></html>',
    'body wrapper' => '<body><script></script></body>',
    'title' => '<title>Title</title>',
    'noscript' => '<noscript><meta charset=utf-8></noscript>',
    'doctype' => '<!DOCTYPE html><meta charset=utf-8>',
    'processing instruction' => '<?example value?><meta charset=utf-8>',
    'XML instruction' => '<?xml version="1.0"?><meta charset=utf-8>',
    'PHP tags' => '<?php $GLOBALS["head_probe"]++; ?>',
    'closing head' => '</head><script></script>',
    'closing void' => '<meta charset=utf-8></meta>',
    'closing link' => '<link href="/site.css"></link>',
    'unclosed comment' => '<!-- comment <meta charset=utf-8>',
    'abrupt empty comment' => '<!--><div>Visible</div>--><meta charset=utf-8>',
    'abrupt dashed comment' => '<!---><div>Visible</div>--><meta charset=utf-8>',
    'alternate comment ending' => '<!-- --!><div>Visible</div>--><meta charset=utf-8>',
    'nested comment opener' => '<!-- nested <!-- comment --><meta charset=utf-8>',
    'unclosed script' => '<script>const example=1;',
    'unclosed style' => '<style>.example{color:red}',
    'mismatched script close' => '<script>example</style>',
    'mismatched style close' => '<style>example</script>',
    'self-closing script' => '<script src="/site.js"/>',
    'self-closing style' => '<style/>body{margin:0}</style>',
    'incomplete script close' => '<script>example</script',
    'incomplete style close' => '<style>example</style',
    'early closing in JS string' => '<script>const html="</script><div>Visible</div>";</script>',
    'early closing in CSS string' => '<style>.example{content:"</style><div>Visible</div>"}</style>',
    'end-tag attributes' => '<script>example</script extra><div>Visible</div><script></script>',
    'end-tag self-closing slash' => '<script>example</script/><div>Visible</div><script></script>',
    'double-escaped script prefix' => '<script><!--<script>example</script><meta charset=utf-8>',
    'double-escaped mixed-case prefix' => '<script><!--<ScRiPt >example</script><meta charset=utf-8>',
    'style end-tag attributes' => '<style>example</style extra><div>Visible</div><style></style>',
    'malformed opening tag' => '<meta! charset=utf-8>',
    'space after opening bracket' => '< meta charset=utf-8>',
    'missing attribute separator' => '<meta name="description"content="Example">',
    'unclosed double quote' => '<meta content="A > B>',
    'unclosed single quote' => "<link href='A > B>",
    'missing attribute value' => '<meta content=>',
    'extra equals' => '<meta content==example>',
    'unquoted angle bracket' => '<meta content=<div>>',
    'malformed self-close' => '<link href="/site.css" / >',
    'unexpected mixed node' => '<meta charset=utf-8><div>Visible</div><script></script>',
    'NUL before metadata' => "\0<meta charset=utf-8>",
    'NUL in attribute' => "<meta content=\"nul\0value\">",
    'NUL in raw script' => "<script>const text=\"nul\0value\";</script>",
    'NUL in comment' => "<!-- nul\0value --><meta charset=utf-8>",
];
$GLOBALS['head_probe'] = 0;
foreach ($invalid as $name => $html) {
    check(!HeadContent::isValid($html), 'Expected invalid HEAD content: ' . $name);
}

$raw = '<script>const example="<div></head>"; /* $GLOBALS["head_probe"]++; */</script>';
$before = $raw;
ob_start();
$accepted = HeadContent::isValid($raw);
$output = ob_get_clean();
check($accepted && $output === '' && $GLOBALS['head_probe'] === 0, 'Validation never executes or emits submitted content');
check($raw === $before, 'Validation does not rewrite its input');
echo "HEAD content tests: {$checks} passed\n";
