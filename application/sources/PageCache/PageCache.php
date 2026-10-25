<?php
/** Content Placements — private response cache policy. MIT. */
namespace IPS\rqblockplacements;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

/** Apply to every eligible route before considering its current audience. */
final class PageCache
{
    public static function disable(): void
    {
        \IPS\Output::setCacheTime(false);
        $output = \IPS\Output::i();
        $replacements = \IPS\Output::getNoCacheHeaders();
        $replacements['Cache-Control'] = 'private, no-store, no-cache, must-revalidate, max-age=0, s-maxage=0';
        $replacements['Pragma'] = 'no-cache';
        // IPS gives instance headers precedence over setCacheTime(). Normalize
        // earlier public headers, including case variants, before setting ours.
        foreach (array_keys($output->httpHeaders) as $name) {
            $normalized = strtolower(trim((string) $name));
            if (in_array($normalized, ['cache-control', 'expires', 'pragma'], true)) {
                unset($output->httpHeaders[$name]);
            } elseif (in_array($normalized, ['surrogate-control', 'cdn-cache-control', 'cloudflare-cdn-cache-control'], true)) {
                unset($output->httpHeaders[$name]);
                $canonical = match ($normalized) {
                    'surrogate-control' => 'Surrogate-Control',
                    'cdn-cache-control' => 'CDN-Cache-Control',
                    default => 'Cloudflare-CDN-Cache-Control',
                };
                $replacements[$canonical] = 'no-store';
            } elseif ($normalized === 'x-accel-expires') {
                unset($output->httpHeaders[$name]);
                $replacements['X-Accel-Expires'] = '0';
            }
        }
        $output->httpHeaders = $replacements + $output->httpHeaders;
    }
}
