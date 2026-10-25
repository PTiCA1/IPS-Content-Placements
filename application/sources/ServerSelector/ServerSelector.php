<?php
/** Content Placements - original application code, MIT licensed. */
namespace IPS\rqblockplacements;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

/** A bounded CSS subset over an original, source-ordered HTML node map. */
final class ServerSelector
{
    private const SPACE = " \t\r\n\f";
    private const ERROR = 'rqbp_error_server_selector';
    private const MAX_NUMBER = 1000000000;
    // HTML's fixed ASCII-insensitive attribute-value list, independent of tag:
    // https://html.spec.whatwg.org/multipage/semantics-other.html#case-sensitivity-of-selectors
    private const INSENSITIVE_ATTRIBUTES = [
        'accept' => true, 'accept-charset' => true, 'align' => true, 'alink' => true,
        'axis' => true, 'bgcolor' => true, 'charset' => true, 'checked' => true,
        'clear' => true, 'codetype' => true, 'color' => true, 'compact' => true,
        'declare' => true, 'defer' => true, 'dir' => true, 'direction' => true,
        'disabled' => true, 'enctype' => true, 'face' => true, 'frame' => true,
        'hreflang' => true, 'http-equiv' => true, 'lang' => true, 'language' => true,
        'link' => true, 'media' => true, 'method' => true, 'multiple' => true,
        'nohref' => true, 'noresize' => true, 'noshade' => true, 'nowrap' => true,
        'readonly' => true, 'rel' => true, 'rev' => true, 'rules' => true,
        'scope' => true, 'scrolling' => true, 'selected' => true, 'shape' => true,
        'target' => true, 'text' => true, 'type' => true, 'valign' => true,
        'valuetype' => true, 'vlink' => true,
    ];

    /** Unsupported syntax is an error, never a silently weakened selector. */
    public static function parse(string $selector): array
    {
        if ($selector === '' || strlen($selector) > 1000 || str_contains($selector, "\0") || str_contains($selector, '\\')) {
            throw new \InvalidArgumentException(self::ERROR);
        }
        $offset = 0;
        $length = strlen($selector);
        $groups = [];
        self::space($selector, $offset);
        while ($offset < $length) {
            $parts = [self::compound($selector, $offset)];
            $combinators = [];
            while ($offset < $length) {
                $spaced = self::space($selector, $offset);
                if ($offset === $length || $selector[$offset] === ',') { break; }
                $next = $selector[$offset];
                if (str_contains('>+~', $next)) {
                    $combinators[] = $next;
                    $offset++;
                    self::space($selector, $offset);
                } elseif ($spaced) {
                    $combinators[] = ' ';
                } else {
                    throw new \InvalidArgumentException(self::ERROR);
                }
                $parts[] = self::compound($selector, $offset);
                if (count($parts) > 64) { throw new \InvalidArgumentException(self::ERROR); }
            }
            $groups[] = ['parts' => $parts, 'combinators' => $combinators];
            if (count($groups) > 64) { throw new \InvalidArgumentException(self::ERROR); }
            if ($offset === $length) { break; }
            $offset++; // A top-level comma, not one inside an attribute value.
            self::space($selector, $offset);
            if ($offset === $length) { throw new \InvalidArgumentException(self::ERROR); }
        }
        if (!$groups) { throw new \InvalidArgumentException(self::ERROR); }
        return ['groups' => $groups];
    }

    /** Return deduplicated IDs in document order, including a matching virtual root. */
    public static function select(array $compiled, array $nodes): array
    {
        $ids = array_keys($nodes);
        sort($ids, SORT_NUMERIC);
        $visible = [];
        $siblings = [];
        foreach ($ids as $id) {
            $node = $nodes[$id];
            $parent = $node['parent'];
            $attrs = $node['attrs'];
            if (($parent !== null && !isset($visible[$parent]))
                || array_key_exists('data-rqbp-placement', $attrs) || array_key_exists('data-rqbp-diagnostics', $attrs)) {
                continue;
            }
            $visible[$id] = true;
            $siblings[$parent ?? 'root'][] = $id;
        }
        $stats = [];
        foreach ($siblings as $children) {
            $types = [];
            foreach ($children as $id) { $types[$nodes[$id]['tag']] = ($types[$nodes[$id]['tag']] ?? 0) + 1; }
            $seen = [];
            $previous = null;
            foreach ($children as $index => $id) {
                $tag = $nodes[$id]['tag'];
                $seen[$tag] = ($seen[$tag] ?? 0) + 1;
                $stats[$id] = ['index' => $index + 1, 'total' => count($children), 'typeIndex' => $seen[$tag], 'typeTotal' => $types[$tag], 'previous' => $previous];
                $previous = $id;
            }
        }
        $selected = [];
        foreach ($compiled['groups'] as $group) {
            $matches = [];
            foreach ($group['parts'] as $partIndex => $part) {
                $next = [];
                $ancestors = [];
                $preceding = [];
                $relation = $partIndex === 0 ? null : $group['combinators'][$partIndex - 1];
                foreach ($visible as $id => $_) {
                    $parent = $nodes[$id]['parent'];
                    $parentKey = $parent ?? 'root';
                    $ancestors[$id] = $parent !== null && (isset($matches[$parent]) || ($ancestors[$parent] ?? false));
                    $related = match ($relation) {
                        null => true,
                        ' ' => $ancestors[$id],
                        '>' => $parent !== null && isset($matches[$parent]),
                        '+' => $stats[$id]['previous'] !== null && isset($matches[$stats[$id]['previous']]),
                        '~' => $preceding[$parentKey] ?? false,
                    };
                    if ($related && self::matches($part, $nodes[$id], $stats[$id])) { $next[$id] = true; }
                    if (isset($matches[$id])) { $preceding[$parentKey] = true; }
                }
                $matches = $next;
                if (!$matches) { break; }
            }
            $selected += $matches;
        }
        return array_values(array_filter(array_keys($visible), static fn (int $id): bool => isset($selected[$id])));
    }

    private static function compound(string $text, int &$offset, int $depth = 0): array
    {
        $length = strlen($text);
        $tag = null;
        $tests = [];
        if (($text[$offset] ?? '') === '*') { $tag = '*'; $offset++; }
        elseif ($name = self::identifier($text, $offset)) { $tag = strtolower($name); }
        while ($offset < $length) {
            $char = $text[$offset];
            if ($char === '#' || $char === '.') {
                $offset++;
                $name = self::identifier($text, $offset);
                if ($name === null) { throw new \InvalidArgumentException(self::ERROR); }
                $tests[] = ['kind' => $char === '#' ? 'id' : 'class', 'value' => $name];
            } elseif ($char === '[') {
                $tests[] = self::attribute($text, $offset);
            } elseif ($char === ':') {
                $offset++;
                $name = strtolower(self::identifier($text, $offset) ?? '');
                if ($name === 'not') {
                    if ($depth || ($text[$offset] ?? '') !== '(') { throw new \InvalidArgumentException(self::ERROR); }
                    $offset++;
                    self::space($text, $offset);
                    $not = self::compound($text, $offset, $depth + 1);
                    self::space($text, $offset);
                    if (($text[$offset] ?? '') !== ')') { throw new \InvalidArgumentException(self::ERROR); }
                    $offset++;
                    $tests[] = ['kind' => 'not', 'compound' => $not];
                } elseif (in_array($name, ['first-child', 'last-child', 'only-child', 'first-of-type', 'last-of-type', 'only-of-type'], true)) {
                    $tests[] = ['kind' => 'pseudo', 'name' => $name];
                } elseif (in_array($name, ['nth-child', 'nth-of-type', 'nth-last-child', 'nth-last-of-type'], true)) {
                    if (($text[$offset] ?? '') !== '(') { throw new \InvalidArgumentException(self::ERROR); }
                    $end = strpos($text, ')', $offset + 1);
                    if ($end === false) { throw new \InvalidArgumentException(self::ERROR); }
                    [$a, $b] = self::anb(substr($text, $offset + 1, $end - $offset - 1));
                    $offset = $end + 1;
                    $tests[] = ['kind' => 'pseudo', 'name' => $name, 'a' => $a, 'b' => $b];
                } else {
                    throw new \InvalidArgumentException(self::ERROR);
                }
            } else { break; }
            if (count($tests) > 128) { throw new \InvalidArgumentException(self::ERROR); }
        }
        if ($tag === null && !$tests) { throw new \InvalidArgumentException(self::ERROR); }
        return ['tag' => $tag ?? '*', 'tests' => $tests];
    }

    private static function attribute(string $text, int &$offset): array
    {
        $offset++;
        self::space($text, $offset);
        $name = self::identifier($text, $offset);
        if ($name === null) { throw new \InvalidArgumentException(self::ERROR); }
        $name = strtolower($name);
        self::space($text, $offset);
        if (($text[$offset] ?? '') === ']') {
            $offset++;
            return ['kind' => 'attr', 'name' => $name, 'operator' => null, 'value' => ''];
        }
        $operator = substr($text, $offset, 2);
        if (in_array($operator, ['~=', '|=', '^=', '$=', '*='], true)) { $offset += 2; }
        elseif (($text[$offset] ?? '') === '=') { $operator = '='; $offset++; }
        else { throw new \InvalidArgumentException(self::ERROR); }
        self::space($text, $offset);
        $quote = $text[$offset] ?? '';
        if ($quote === '"' || $quote === "'") {
            $end = strpos($text, $quote, $offset + 1);
            if ($end === false) { throw new \InvalidArgumentException(self::ERROR); }
            $value = substr($text, $offset + 1, $end - $offset - 1);
            if (strpbrk($value, "\r\n\f") !== false) { throw new \InvalidArgumentException(self::ERROR); }
            $offset = $end + 1;
        } else {
            $value = self::identifier($text, $offset);
            if ($value === null) { throw new \InvalidArgumentException(self::ERROR); }
        }
        self::space($text, $offset);
        if (($text[$offset] ?? '') !== ']') { throw new \InvalidArgumentException(self::ERROR); }
        $offset++;
        return ['kind' => 'attr', 'name' => $name, 'operator' => $operator, 'value' => $value];
    }

    private static function identifier(string $text, int &$offset): ?string
    {
        if (!preg_match('/\G(?:--|-[a-zA-Z_]|[a-zA-Z_])[a-zA-Z0-9_-]*/', $text, $match, 0, $offset)) { return null; }
        $offset += strlen($match[0]);
        return $match[0];
    }

    private static function space(string $text, int &$offset): bool
    {
        $start = $offset;
        while (isset($text[$offset]) && str_contains(self::SPACE, $text[$offset])) { $offset++; }
        return $offset !== $start;
    }

    private static function anb(string $argument): array
    {
        $argument = strtolower(trim($argument, self::SPACE));
        if ($argument === 'odd') { return [2, 1]; }
        if ($argument === 'even') { return [2, 0]; }
        if (preg_match('/^[+-]?\d+$/D', $argument)) { return [0, self::integer($argument)]; }
        if (!preg_match('/^([+-]?\d*)n(?:[ \t\r\n\f]*([+-])[ \t\r\n\f]*(\d+))?$/D', $argument, $match)) {
            throw new \InvalidArgumentException(self::ERROR);
        }
        $coefficient = match ($match[1]) { '', '+' => 1, '-' => -1, default => self::integer($match[1]) };
        $constant = isset($match[3]) ? self::integer(($match[2] === '-' ? '-' : '') . $match[3]) : 0;
        return [$coefficient, $constant];
    }

    private static function integer(string $value): int
    {
        $digits = ltrim(ltrim($value, '+-'), '0');
        if (strlen($digits) > 10 || (strlen($digits) === 10 && strcmp($digits, (string) self::MAX_NUMBER) > 0)) {
            throw new \InvalidArgumentException(self::ERROR);
        }
        return (int) $value;
    }

    private static function matches(array $compound, array $node, array $stats): bool
    {
        if ($compound['tag'] !== '*' && $compound['tag'] !== $node['tag']) { return false; }
        $attrs = $node['attrs'];
        foreach ($compound['tests'] as $test) {
            $pass = match ($test['kind']) {
                'id' => ($attrs['id'] ?? null) === $test['value'],
                'class' => in_array($test['value'], preg_split('/[ \t\r\n\f]+/', trim($attrs['class'] ?? '', self::SPACE)), true),
                'attr' => self::matchesAttribute($test, $attrs),
                'not' => !self::matches($test['compound'], $node, $stats),
                'pseudo' => self::matchesPseudo($test, $stats),
            };
            if (!$pass) { return false; }
        }
        return true;
    }

    private static function matchesAttribute(array $test, array $attrs): bool
    {
        if (!array_key_exists($test['name'], $attrs)) { return false; }
        $value = $attrs[$test['name']];
        $expected = $test['value'];
        if (isset(self::INSENSITIVE_ATTRIBUTES[$test['name']])) {
            $value = strtolower($value);
            $expected = strtolower($expected);
        }
        return match ($test['operator']) {
            null => true,
            '=' => $value === $expected,
            '~=' => $expected !== '' && in_array($expected, preg_split('/[ \t\r\n\f]+/', trim($value, self::SPACE)), true),
            '|=' => $value === $expected || str_starts_with($value, $expected . '-'),
            '^=' => $expected !== '' && str_starts_with($value, $expected),
            '$=' => $expected !== '' && str_ends_with($value, $expected),
            '*=' => $expected !== '' && str_contains($value, $expected),
        };
    }

    private static function matchesPseudo(array $test, array $stats): bool
    {
        $name = $test['name'];
        $typed = str_ends_with($name, '-of-type');
        $index = $stats[$typed ? 'typeIndex' : 'index'];
        $total = $stats[$typed ? 'typeTotal' : 'total'];
        if (str_starts_with($name, 'first-')) { return $index === 1; }
        if (str_starts_with($name, 'last-')) { return $index === $total; }
        if (str_starts_with($name, 'only-')) { return $total === 1; }
        if (str_starts_with($name, 'nth-last-')) { $index = $total - $index + 1; }
        $delta = $index - $test['b'];
        return $test['a'] === 0 ? $delta === 0 : $delta % $test['a'] === 0 && intdiv($delta, $test['a']) >= 0;
    }
}
