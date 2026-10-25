<?php
/** Original template boundary doubles; optional licensed compiler is read locally only. */
namespace {
    define('IPS\SUITE_UNIQUE_KEY', 'template-content-test');
    define('DEBUG_TEMPLATES', false);
}
namespace IPS\Node { class Model {} }
namespace IPS {
    class Application {}
    class IPS { public static function mb_ucfirst(string $word): string { return ucfirst($word); } }
    class MafraApi {
        public static int $calls = 0;
        public function getMafraApiId(string $id): string {
            self::$calls++;
            return '<strong data-id="' . $id . '">Mafra</strong>';
        }
    }
    if (!getenv('RQBP_IPS_THEME_SOURCE')) {
        /** Predetermined compiler responses, not an alternative template implementation. */
        class Theme {
            public static int $compiles = 0;
            public static int $registrations = 0;
            public static function compileTemplate(string $code, string $name): string {
                self::$compiles++;
                $body = match ($code) {
                    '{expression="(new \\IPS\\MafraApi())->getMafraApiId(\'28\')" raw="true"}' => 'return (new \\IPS\\MafraApi())->getMafraApiId("28");',
                    '{{echo "echo ";}}<b>return</b>' => 'echo "echo "; return "<b>return</b>";',
                    '{{ob_start(); echo "nested ";}}<b>return</b>' => 'ob_start(); echo "nested "; return "<b>return</b>";',
                    '{{echo "SECRET"; ob_start(); echo "NESTED_SECRET"; throw new \\RuntimeException("failed");}}' => 'echo "SECRET"; ob_start(); echo "NESTED_SECRET"; throw new \\RuntimeException("failed");',
                    '{{if true}}broken' => 'if (true): return "broken";',
                    default => 'return ' . var_export($code, true) . ';',
                };
                return 'function ' . $name . '() {' . $body . '}';
            }
            public static function runProcessFunction(string $compiled, string $name): void {
                self::$registrations++;
                if (!function_exists('IPS\\Theme\\' . $name)) { eval('namespace IPS\\Theme;' . $compiled); }
            }
        }
    }
}
namespace {
    $native = getenv('RQBP_IPS_THEME_SOURCE');
    if ($native) {
        $base = rtrim($native, '/');
        require $base . '/system/Theme/Theme.php';
        require $base . '/system/Theme/Template.php';
        require $base . '/system/Output/Plugin/Expression.php';
    }
    require __DIR__ . '/../application/Application.php';
    require __DIR__ . '/../application/sources/TemplateContent/TemplateContent.php';
    use IPS\rqblockplacements\Application;
    use IPS\rqblockplacements\TemplateContent;

    $checks = 0;
    function check(bool $value, string $message): void {
        global $checks;
        $checks++;
        if (!$value) { throw new \RuntimeException($message); }
    }
    function rejects(string $code, string $message): void {
        $thrown = null;
        try { TemplateContent::validate($code); }
        catch (\InvalidArgumentException $error) { $thrown = $error; }
        check($thrown?->getMessage() === $message, 'Invalid template produces the expected validation error: ' . $message);
    }

    $example = <<<'IPS'
{expression="(new \IPS\MafraApi())->getMafraApiId('28')" raw="true"}
IPS;
    $function = 'IPS\\Theme\\rqbp_template_' . Application::RQBP_BUILD . '_' . hash('sha256', $example);
    TemplateContent::validate($example);
    TemplateContent::validate($example);
    check(\IPS\MafraApi::$calls === 0, 'Validating the exact user expression never calls MafraApi');
    check(!function_exists($function), 'Validation does not define the compiled template function');
    if (!$native) { check(\IPS\Theme::$registrations === 0 && \IPS\Theme::$compiles === 2, 'Validation only compiles/parses and never installs the function'); }
    $level = ob_get_level();
    $html = TemplateContent::render($example);
    check(trim($html) === '<strong data-id="28">Mafra</strong>', 'The exact raw expression renders unescaped Mafra HTML');
    check(\IPS\MafraApi::$calls === 1 && ob_get_level() === $level, 'Rendering calls the API exactly once and restores buffers');
    TemplateContent::render($example);
    check(\IPS\MafraApi::$calls === 2, 'Compiled function reuse never shares rendered HTML between invocations');

    $echoed = TemplateContent::render('{{echo "echo ";}}<b>return</b>');
    check(str_contains($echoed, 'echo ') && str_contains($echoed, '<b>return</b>'), 'Both echoed and returned template HTML are captured');
    $nested = TemplateContent::render('{{ob_start(); echo "nested ";}}<b>return</b>');
    check(str_contains($nested, 'nested ') && str_contains($nested, '<b>return</b>') && ob_get_level() === $level, 'Nested template output is captured without leaking a buffer');

    rejects('', 'rqbp_error_template_empty');
    rejects(" \n\t", 'rqbp_error_template_empty');
    rejects("a\0b", 'rqbp_error_template_invalid');
    rejects(str_repeat('a', TemplateContent::MAX_BYTES + 1), 'rqbp_error_template_too_long');
    rejects(str_repeat('ě', 32768), 'rqbp_error_template_too_long');
    rejects('<?php echo "raw";', 'rqbp_error_template_php_tags');
    rejects('prefix ?> suffix', 'rqbp_error_template_php_tags');
    rejects('<?xml version="1.0"?>', 'rqbp_error_template_php_tags');
    rejects('{{if true}}broken', 'rqbp_error_template_invalid');
    TemplateContent::validate(str_repeat('a', TemplateContent::MAX_BYTES));
    check(\IPS\MafraApi::$calls === 2, 'Length and syntax checks do not execute templates');

    $exception = null;
    ob_start();
    echo 'CALLER';
    try { TemplateContent::render('{{echo "SECRET"; ob_start(); echo "NESTED_SECRET"; throw new \\RuntimeException("failed");}}'); }
    catch (\Throwable $error) { $exception = $error; }
    $caller = ob_get_clean();
    check($exception instanceof \RuntimeException && $caller === 'CALLER', 'Runtime failure discards partial/nested template output and preserves the caller buffer');
    check(ob_get_level() === $level, 'Runtime failure restores the original output buffer depth');
    echo 'Template content tests (' . ($native ? 'local native compiler' : 'original boundary doubles') . "): {$checks} passed\n";
}
