<?php
/** Content Placements — ACP rule permissions. MIT. */
namespace IPS\rqblockplacements;

if (!\defined('\IPS\SUITE_UNIQUE_KEY')) { exit; }

/** Keep every route that can activate or modify template code behind one ACP permission. */
final class RuleAccess
{
    public static function canManageCode(): bool
    {
        return \IPS\Member::loggedIn()->hasAcpRestriction('rqblockplacements', 'placements', 'rqbp_manage_code');
    }

    public static function assertCodePermission(): void
    {
        \IPS\Dispatcher::i()->checkAcpPermission('rqbp_manage_code', 'rqblockplacements', 'placements');
    }

    /** Old records without a content type remain blocks; unknown types require code access. */
    public static function requiresCodePermission(Rule|array $rule): bool
    {
        $config = $rule instanceof Rule ? $rule->toConfig() : Policy::normalize($rule);
        return $config['content_type'] !== 'block';
    }

    public static function assertForRule(Rule|array $rule): void
    {
        if (self::requiresCodePermission($rule)) { self::assertCodePermission(); }
    }

    /** Authorize the entire new order before changing any model or database row. */
    public static function assertReorder(array $records): void
    {
        foreach (array_values($records) as $position => $record) {
            if ((int) $record->sort_order !== ($position + 1) * 10) {
                self::assertForRule($record);
            }
        }
    }

    /** Stopping the application remains available to block administrators as a panic stop. */
    public static function assertMasterEnable(bool $currentlyEnabled, bool $requestedEnabled): void
    {
        if ($currentlyEnabled || !$requestedEnabled) { return; }
        foreach (Rule::all() as $record) {
            $config = $record->toConfig();
            if ($config['enabled']) { self::assertForRule($config); }
        }
    }
}
