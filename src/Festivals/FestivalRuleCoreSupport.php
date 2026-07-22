<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Festivals\Support\FestivalShared;
use LogicException;

/**
 * Core support methods for FestivalRuleEngine (paksha/karmakala/text).
 *
 * Thin wrappers over {@see Support\FestivalShared} so RuleEngine call sites keep
 * $this->method() style without forking logic.
 *
 * @see docs/FESTIVAL_REFACTOR_PARITY_CONTRACT.md
 */
trait FestivalRuleCoreSupport
{
    /** Resolve paksha constraint for a rule under the active calendar system. */
    private function resolveRulePaksha(array $rule, array $calendar, string $fallbackPaksha = 'Shukla'): array|string
    {
        return FestivalShared::resolveRulePaksha($rule, $calendar, $fallbackPaksha);
    }

    private function localizedPaksha(string $paksha): string
    {
        return match ($paksha) {
            'Shukla' => Localization::translate('String', 'Shukla Paksha (waxing)'),
            'Krishna' => Localization::translate('String', 'Krishna Paksha (waning)'),
            default => $paksha,
        };
    }

    private function localizedKarmakala(string $karmakalaType): string
    {
        return Localization::translate('String', $this->normalizeKarmakalaType($karmakalaType));
    }

    /** Canonical karmakala type aliases (nishita→nishitha, sayahna→sayankala, …). */
    private function normalizeKarmakalaType(string $type): string
    {
        return FestivalShared::normalizeKarmakalaType($type);
    }

    private function assertValidFestivalRule(string $name, array $rule): void
    {
        $kala = $this->normalizeKarmakalaType((string) ($rule['karmakala_type'] ?? 'sunrise'));
        if (!in_array($kala, FestivalShared::SUPPORTED_KARMAKALA_TYPES, true)) {
            throw new LogicException(sprintf("Unknown karmakala_type '%s' for %s", $kala, $name));
        }

        if (isset($rule['vriddhi_preference']) && ! in_array($rule['vriddhi_preference'], ['first', 'last'], true)) {
            throw new LogicException(sprintf('Invalid vriddhi_preference for %s', $name));
        }

        // kshaya: first | last | merged_host_day (host civil day of the skipped tithi interval)
        $allowedKshaya = ['first', 'last', 'merged_host_day', 'merged_day', 'host_day', 'merged'];
        if (isset($rule['kshaya_preference']) && ! in_array($rule['kshaya_preference'], $allowedKshaya, true)) {
            throw new LogicException(sprintf('Invalid kshaya_preference for %s', $name));
        }
    }

    private function isExecutableResolverProfile(array $rule): bool
    {
        $resolverCompatibility = (string) ($rule['resolver_compatibility'] ?? 'full');
        if ($resolverCompatibility === '' || $resolverCompatibility === 'full') {
            return true;
        }

        return (bool) ($rule['allow_partial_resolver_execution'] ?? false);
    }

    /** Normalize Sanskrit month names for robust matching across ASCII and diacritic forms. */
    private function normalizeMonthName(string $month): string
    {
        return FestivalShared::normalizeMonthName($month);
    }

    /** Normalize free-text labels (ASCII/Unicode) for robust equality checks. */
    private function normalizeLabel(string $label): string
    {
        return FestivalShared::normalizeLabel($label);
    }

}
