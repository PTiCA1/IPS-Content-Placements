<?php
/** Pages Block Placements - original application code, MIT licensed. */
namespace IPS\rqblockplacements;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

/** Native IPS template syntax supplied by a trusted ACP administrator. */
final class TemplateContent
{
    public const MAX_BYTES = 65535;

    /** Compile and parse only: never define or invoke the submitted function. */
    public static function validate(string $code): void
    {
        self::compile($code);
    }

    /** No rendered output is shared between rules, requests or visitors. */
    public static function render(string $code): string
    {
        $level = ob_get_level();
        ob_start();
        try {
            $functionName = self::functionName($code);
            $compiled = self::compile($code);
            \IPS\Theme::runProcessFunction($compiled, $functionName);
            $function = '\\IPS\\Theme\\' . $functionName;
            $returned = $function();
            // A template may use echo or open nested buffers as well as return
            // native template HTML. Keep their output inside this render only.
            while (ob_get_level() > $level + 1) {
                $nested = ob_get_clean();
                echo $nested;
            }
            if (ob_get_level() !== $level + 1) {
                throw new \RuntimeException('The template closed its output buffer.');
            }
            return ob_get_clean() . (string) ($returned ?? '');
        } catch (\Throwable $error) {
            while (ob_get_level() > $level) { ob_end_clean(); }
            throw $error;
        }
    }

    private static function functionName(string $code): string
    {
        return 'rqbp_template_' . Application::RQBP_BUILD . '_' . hash('sha256', $code);
    }

    private static function compile(string $code): string
    {
        if (trim($code) === '') { throw new \InvalidArgumentException('rqbp_error_template_empty'); }
        if (strlen($code) > self::MAX_BYTES) { throw new \InvalidArgumentException('rqbp_error_template_too_long'); }
        if (str_contains($code, "\0")) { throw new \InvalidArgumentException('rqbp_error_template_invalid'); }
        if (str_contains($code, '<?') || str_contains($code, '?>')) {
            throw new \InvalidArgumentException('rqbp_error_template_php_tags');
        }
        try {
            $compiled = \IPS\Theme::compileTemplate($code, self::functionName($code));
            // This checks generated PHP syntax without eval, function
            // registration, submitted API calls or a template invocation.
            token_get_all("<?php namespace IPS\\Theme;\n" . $compiled, TOKEN_PARSE);
            return $compiled;
        } catch (\Throwable $error) {
            throw new \InvalidArgumentException('rqbp_error_template_invalid', 0, $error);
        }
    }
}
