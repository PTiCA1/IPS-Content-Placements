<?php
/** Content Placements - original application code, MIT licensed. */
namespace IPS\rqblockplacements;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

/** Plans insertions against one immutable fragment and then applies byte inserts. */
final class ServerPlacement
{
    public const MAX_INSERTIONS = 10000;
    public const MAX_OUTPUT_BYTES = 8388608;
    private const FLOW = ['div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav', 'li', 'form', 'figure', 'blockquote'];
    private const LISTS = ['ul', 'ol', 'menu'];
    private const ANCHORS = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    private const FORBIDDEN = ['html', 'head', 'title', 'script', 'style', 'textarea', 'xmp', 'iframe', 'noembed', 'noframes', 'noscript', 'template',
        'select', 'option', 'optgroup', 'datalist', 'table', 'caption', 'colgroup', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'svg', 'math',
        'a', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'button', 'label', 'legend', 'summary', 'pre', 'code', 'strong', 'em', 'small',
        'b', 'i', 'u', 's', 'sub', 'sup', 'nobr', 'ruby', 'rb', 'rt', 'rp', 'rtc', 'picture'];

    /** Audience and route checks belong to the caller; render is called lazily. */
    public static function apply(string $html, array $rules, callable $render): array
    {
        $ordered = [];
        foreach (array_values($rules) as $index => $rule) { $ordered[] = ['rule' => $rule, 'index' => $index]; }
        usort($ordered, static fn (array $a, array $b): int => ((int) ($a['rule']['sort_order'] ?? 0) <=> (int) ($b['rule']['sort_order'] ?? 0)) ?: ($a['index'] <=> $b['index']));
        $reports = [];
        try { $document = ServerDocument::parse($html); }
        catch (\Throwable $error) {
            $reason = $error->getMessage() === 'server_fragment_too_large' ? 'server_fragment_too_large' : 'server_html_unsupported';
            foreach ($ordered as $item) { $reports[] = self::report($item['rule'], $reason); }
            return ['html' => $html, 'reports' => $reports];
        }
        $plans = [];
        $needed = [];
        $plannedCount = 0;
        foreach ($ordered as $order => $item) {
            $rule = $item['rule'];
            $report = self::report($rule, 'server_no_match');
            $reports[] = $report;
            try {
                $compiled = ServerSelector::parse((string) ($rule['selector'] ?? ''));
                $matches = ServerSelector::select($compiled, $document->nodes);
            } catch (\InvalidArgumentException $error) {
                $reports[$order]['reason'] = 'server_selector_invalid';
                continue;
            } catch (\Throwable $error) {
                $reports[$order]['reason'] = 'server_error';
                continue;
            }
            $reports[$order]['matches'] = count($matches);
            if (!$matches) { continue; }
            $selected = [];
            $occurrence = (string) ($rule['occurrence'] ?? 'first');
            if ($occurrence === 'all') { $selected = $matches; }
            elseif ($occurrence === 'first') { $selected = [$matches[0]]; }
            elseif ($occurrence === 'nth') {
                $nth = (int) ($rule['occurrence_index'] ?? 1);
                if ($nth > 0 && isset($matches[$nth - 1])) { $selected = [$matches[$nth - 1]]; }
            }
            if (!$selected) { $reports[$order]['reason'] = 'server_occurrence_missing'; continue; }
            $position = (string) ($rule['position'] ?? 'after');
            $destinations = [];
            foreach ($selected as $id) {
                $wrapper = self::wrapper($document->nodes, $id, $position);
                if ($wrapper === null) { $destinations = []; break; }
                $destinations[] = ['id' => $id, 'wrapper' => $wrapper, 'position' => $position];
            }
            if (!$destinations) { $reports[$order]['reason'] = 'server_context_unsupported'; continue; }
            if ($plannedCount + count($destinations) > self::MAX_INSERTIONS) {
                $reports[$order]['reason'] = 'server_fragment_too_large';
                continue;
            }
            $plannedCount += count($destinations);
            foreach ($destinations as $destination) { $needed[$destination['id']] = true; }
            $plans[$order] = ['rule' => $rule, 'destinations' => $destinations];
        }
        $ranks = self::ranks($document->nodes, $needed);
        $edits = [];
        $outputBytes = strlen($html);
        foreach ($plans as $order => $plan) {
            try {
                $content = $render($plan['rule']);
                if ($content === null || $content === '') { $reports[$order]['reason'] = ''; continue; }
                if (!is_string($content)) { throw new \UnexpectedValueException('Expected rendered HTML'); }
                try { $inserted = ServerDocument::parse($content); }
                catch (\InvalidArgumentException $error) {
                    $reports[$order]['reason'] = $error->getMessage() === 'server_fragment_too_large' ? 'server_fragment_too_large' : 'server_content_unsupported';
                    continue;
                }
                if (self::nestedForm($document->nodes, $plan['destinations'], $inserted->nodes)) {
                    $reports[$order]['reason'] = 'server_context_unsupported';
                    continue;
                }
                // Bound expansion before copying content once per destination.
                $ruleBytes = 0;
                foreach ($plan['destinations'] as $destination) {
                    $wrapper = $destination['wrapper'];
                    $ruleBytes += strlen('<' . $wrapper . ' data-rqbp-placement="' . (int) $plan['rule']['id'] . '" data-rqbp-rule="' . (int) $plan['rule']['id'] . '"></' . $wrapper . '>') + strlen($content);
                }
                if ($outputBytes + $ruleBytes > self::MAX_OUTPUT_BYTES) { $reports[$order]['reason'] = 'server_fragment_too_large'; continue; }
                $ruleEdits = [];
                foreach ($plan['destinations'] as $destination) {
                    $node = $document->nodes[$destination['id']];
                    $wrapper = $destination['wrapper'];
                    $markup = '<' . $wrapper . ' data-rqbp-placement="' . (int) $plan['rule']['id'] . '" data-rqbp-rule="' . (int) $plan['rule']['id'] . '">' . $content . '</' . $wrapper . '>';
                    $offset = match ($destination['position']) {
                        'before' => $node['start'], 'inside-start' => $node['openEnd'], 'inside-end' => $node['closeStart'], 'after' => $node['end'],
                    };
                    $ruleEdits[] = ['offset' => $offset, 'rank' => $ranks[$destination['id']][$destination['position']], 'order' => $order, 'html' => $markup];
                }
                $outputBytes += $ruleBytes;
                array_push($edits, ...$ruleEdits);
                $reports[$order]['inserted'] = count($ruleEdits);
                $reports[$order]['reason'] = 'inserted';
            } catch (\Throwable $error) { $reports[$order]['reason'] = 'server_error'; }
        }
        if (!$edits) { return ['html' => $html, 'reports' => $reports]; }
        usort($edits, static fn (array $a, array $b): int => ($a['offset'] <=> $b['offset']) ?: (($a['rank'] <=> $b['rank']) ?: ($a['order'] <=> $b['order'])));
        $result = '';
        $offset = 0;
        foreach ($edits as $edit) {
            $result .= substr($html, $offset, $edit['offset'] - $offset) . $edit['html'];
            $offset = $edit['offset'];
        }
        $result .= substr($html, $offset);
        return ['html' => $result, 'reports' => $reports];
    }

    private static function report(array $rule, string $reason): array
    {
        return ['id' => (int) ($rule['id'] ?? 0), 'matches' => 0, 'inserted' => 0, 'reason' => $reason];
    }

    private static function wrapper(array $nodes, int $id, string $position): ?string
    {
        if (!isset($nodes[$id]) || !in_array($position, ['before', 'inside-start', 'inside-end', 'after'], true)) { return null; }
        $node = $nodes[$id];
        $inside = str_starts_with($position, 'inside-');
        if ($node['synthetic']) { return $inside ? 'div' : null; }
        if (!in_array($node['tag'], array_merge(self::FLOW, self::LISTS, self::ANCHORS), true)) { return null; }
        if ($inside && !in_array($node['tag'], array_merge(self::FLOW, self::LISTS), true)) { return null; }
        $ancestor = $node['parent'];
        while ($ancestor !== null) {
            $current = $nodes[$ancestor];
            if (in_array($current['tag'], self::FORBIDDEN, true)
                || array_key_exists('data-rqbp-placement', $current['attrs']) || array_key_exists('data-rqbp-diagnostics', $current['attrs'])) { return null; }
            $ancestor = $current['parent'];
        }
        if (array_key_exists('data-rqbp-placement', $node['attrs']) || array_key_exists('data-rqbp-diagnostics', $node['attrs'])) { return null; }
        $destination = $inside ? $node : $nodes[$node['parent']];
        if (in_array($destination['tag'], self::LISTS, true)) { return 'li'; }
        return in_array($destination['tag'], self::FLOW, true) ? 'div' : null;
    }

    /** Event order distinguishes adjacent sibling and nested boundary inserts. */
    private static function ranks(array $nodes, array $needed): array
    {
        $ranks = [];
        $stack = [[0, false]];
        $rank = 0;
        while ($stack) {
            [$id, $exit] = array_pop($stack);
            if ($exit) {
                if (isset($needed[$id])) { $ranks[$id]['inside-end'] = $rank; $ranks[$id]['after'] = $rank + 1; }
                $rank += 2;
                continue;
            }
            if (isset($needed[$id])) { $ranks[$id] = ['before' => $rank, 'inside-start' => $rank + 1]; }
            $rank += 2;
            $stack[] = [$id, true];
            foreach (array_reverse($nodes[$id]['children']) as $child) { $stack[] = [$child, false]; }
        }
        return $ranks;
    }

    private static function nestedForm(array $nodes, array $destinations, array $inserted): bool
    {
        $hasForm = false;
        foreach ($inserted as $node) { if ($node['tag'] === 'form') { $hasForm = true; break; } }
        if (!$hasForm) { return false; }
        foreach ($destinations as $destination) {
            $ancestor = str_starts_with($destination['position'], 'inside-') ? $destination['id'] : $nodes[$destination['id']]['parent'];
            while ($ancestor !== null) {
                if ($nodes[$ancestor]['tag'] === 'form') { return true; }
                $ancestor = $nodes[$ancestor]['parent'];
            }
        }
        return false;
    }
}
