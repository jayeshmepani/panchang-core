<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;

use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Masa;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Paksha;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Festivals\Support\FestivalShared;

/**
 * Presentation / payload building for festival emit (structure-only split).
 *
 * Resolution algorithms stay on FestivalService. This trait only formats and localizes
 * the already-resolved festival payload. Behavior is unchanged.
 *
 * @see docs/FESTIVAL_REFACTOR_PARITY_CONTRACT.md
 */
trait FestivalPayloadPresentation
{    /**
     * Build a complete, localized festival payload while preserving the
     * calculation basis from the registry and resolver decision context.
     *
     * @return array<string, mixed>
     */
    public function buildFestivalPayload(string $name, array $rules, ?array $resolved = null): array
    {
        // Canonical identity for counting/dedup. Display name may differ (display_name / variants).
        $rawIdentity = $rules['identity_key'] ?? null;
        $identityKey = is_string($rawIdentity) && $rawIdentity !== ''
            ? $rawIdentity
            : $name;
        $displayName = $name;

        if ($name === 'Pradosh Vrat' && isset($resolved['observance_date'])) {
            $dateObj = CarbonImmutable::parse($resolved['observance_date']);
            $dayOfWeek = $dateObj->dayOfWeek; // 0 for Sunday, 6 for Saturday

            $weekdayNames = [
                0 => 'Ravi Pradosh Vrat',
                1 => 'Soma Pradosh Vrat',
                2 => 'Bhauma Pradosh Vrat',
                3 => 'Budha Pradosh Vrat',
                4 => 'Guru Pradosh Vrat',
                5 => 'Shukra Pradosh Vrat',
                6 => 'Shani Pradosh Vrat',
            ];

            $descriptions = [
                0 => 'Pradosh Vrat falling on a Sunday',
                1 => 'Pradosh Vrat falling on a Monday',
                2 => 'Pradosh Vrat falling on a Tuesday',
                3 => 'Pradosh Vrat falling on a Wednesday',
                4 => 'Pradosh Vrat falling on a Thursday',
                5 => 'Pradosh Vrat falling on a Friday',
                6 => 'Pradosh Vrat falling on a Saturday, highly auspicious for Lord Shiva and Shani',
            ];

            // DOC identities are weekday-specific Pradosh names; keep generic as alias.
            $weekdayName = $weekdayNames[$dayOfWeek] ?? null;
            $rules['description'] = $descriptions[$dayOfWeek] ?? ($rules['description'] ?? '');
            $rules['deity'] = ($dayOfWeek === 6) ? 'Shiva/Shani' : 'Shiva';
            $aliases = ['Pradosh Vrat'];
            if ($weekdayName !== null) {
                $identityKey = $weekdayName;
                $displayName = $weekdayName;
                $rules['weekday_classifier'] = $weekdayName;
            }

            if ($dayOfWeek === 6) {
                $aliases[] = 'Shani Trayodashi';
            }

            $rules['aliases'] = $aliases;
        }

        $rawDisplayName = $rules['display_name'] ?? null;
        if (is_string($rawDisplayName) && $rawDisplayName !== '') {
            $aliases = array_values(array_unique(array_merge(
                [$identityKey, $name],
                array_map(strval(...), (array) ($rules['aliases'] ?? [])),
            )));
            $rules['aliases'] = $aliases;
            // display_name is public label only — never overwrite the canonical identity key.
            $displayName = $rawDisplayName;
        }

        $regions = $rules['regions'] ?? ['Pan-India'];
        $localizedAliases = array_map(
            static fn (string $alias): string => Localization::translate('Festival', $alias),
            array_map(strval(...), (array) ($rules['aliases'] ?? []))
        );
        $aliases = array_values(array_unique($localizedAliases));
        $deity = $rules['deity'] ?? null;

        $payload = [
            'name' => Localization::translate('Festival', $displayName),
            'name_key' => $identityKey,
            'description' => Localization::translate('FestivalDesc', $rules['description'] ?? ''),
            'deity' => $deity === null ? null : Localization::translate('Deity', (string) $deity),
            'fasting' => (bool) ($rules['fasting'] ?? false),
            'regions' => array_map(static fn ($r): string => Localization::translate('Region', (string) $r), $regions),
            'aliases' => $aliases,
            'observance_note' => $resolved['observance_note'] ?? null,
            'calculation_basis' => $this->buildCalculationBasis($rules, $resolved),
        ];

        $resolution = $this->buildResolutionMetadata($resolved);
        if ($resolution !== []) {
            $payload['resolution'] = $resolution;
        }

        if (isset($resolved['decision']) && is_array($resolved['decision'])) {
            $payload['rules_applied'] = $this->localizeDecisionMetadata($resolved['decision']);
        }

        return $payload;
    }

    private function buildCalculationBasis(array $rules, ?array $resolved = null): array
    {
        $type = (string) ($rules['type'] ?? 'tithi');
        $nakshatraRaw = $rules['nakshatra'] ?? ($resolved['required_nakshatra'] ?? null);
        $parentFestivalRaw = $rules['parent_festival'] ?? null;

        $basis = [
            'type' => $type,
            'type_name' => $this->localizedString($type),
            'basis' => $this->inferFestivalBasis($rules),
            'basis_name' => $this->localizedString($this->inferFestivalBasis($rules)),
            'resolver' => $rules['resolver'] ?? null,
            'family' => $this->localizedString($rules['family'] ?? null),
            'family_key' => $rules['family'] ?? null,
            'name_classifier' => is_string($rules['name_classifier'] ?? null) ? Localization::translate('Festival', $rules['name_classifier']) : null,
            'name_classifier_key' => $rules['name_classifier'] ?? null,
            'inherit_decision_from' => is_string($rules['inherit_decision_from'] ?? null) ? Localization::translate('Festival', $rules['inherit_decision_from']) : null,
            'inherit_decision_from_key' => $rules['inherit_decision_from'] ?? null,
            'document_status' => $this->localizedString($rules['document_status'] ?? null),
            'document_status_key' => $rules['document_status'] ?? null,
            'tithi' => $this->formatTithiRule($rules['tithi'] ?? ($resolved['required_tithi'] ?? null), $rules['paksha'] ?? ($resolved['paksha'] ?? null)),
            'tithi_options' => $rules['tithi_options'] ?? null,
            'prefer_higher_tithi_option' => $rules['prefer_higher_tithi_option'] ?? null,
            'paksha' => $rules['paksha'] ?? ($resolved['paksha'] ?? null),
            'paksha_name' => $this->localizedPakshaName($rules['paksha'] ?? ($resolved['paksha'] ?? null)),
            'paksha_amanta' => $rules['paksha_amanta'] ?? null,
            'paksha_amanta_name' => $this->localizedPakshaName($rules['paksha_amanta'] ?? null),
            'paksha_purnimanta' => $rules['paksha_purnimanta'] ?? null,
            'paksha_purnimanta_name' => $this->localizedPakshaName($rules['paksha_purnimanta'] ?? null),
            'month' => $this->formatMonthRule($rules, $resolved['calendar_type'] ?? null),
            'solar_rashi' => $this->formatRashiRule($rules['rashi'] ?? null),
            'nakshatra' => is_string($nakshatraRaw) && $nakshatraRaw !== '' ? $this->localizedNakshatraName($nakshatraRaw) : $nakshatraRaw,
            'nakshatra_key' => $nakshatraRaw,
            'nakshatra_only' => $rules['nakshatra_only'] ?? null,
            'fixed_date' => $this->formatFixedDateRule($rules),
            'weekday' => $this->formatWeekdayRule($rules['weekday'] ?? null),
            'karmakala_type' => $rules['karmakala_type'] ?? ($resolved['karmakala_type'] ?? null),
            'karmakala_type_name' => $this->localizedString($rules['karmakala_type'] ?? ($resolved['karmakala_type'] ?? null)),
            'ritual_kala_type' => $rules['ritual_kala_type'] ?? null,
            'ritual_kala_type_name' => $this->localizedString($rules['ritual_kala_type'] ?? null),
            'strict_karmakala' => $rules['strict_karmakala'] ?? null,
            'strict_dashami_vedha' => $rules['strict_dashami_vedha'] ?? null,
            'require_sunrise_vyapini' => $rules['require_sunrise_vyapini'] ?? null,
            'require_previous_tithi_at' => $rules['require_previous_tithi_at'] ?? null,
            'vriddhi_preference' => $rules['vriddhi_preference'] ?? null,
            'prefer_first_karmakala' => $rules['prefer_first_karmakala'] ?? null,
            'prefer_full_karmakala_coverage' => $rules['prefer_full_karmakala_coverage'] ?? null,
            'prefer_nakshatra' => $rules['prefer_nakshatra'] ?? null,
            'prefer_nakshatra_window' => $rules['prefer_nakshatra_window'] ?? null,
            'require_nakshatra_window' => $rules['require_nakshatra_window'] ?? null,
            'avoid_bhadra_mukha' => $rules['avoid_bhadra_mukha'] ?? null,
            'prefer_bhadra_puchha' => $rules['prefer_bhadra_puchha'] ?? null,
            'chandradarshan_nishedh' => $rules['chandradarshan_nishedh'] ?? null,
            'chandra_darshana_visibility_model' => $rules['chandra_darshana_visibility_model'] ?? null,
            'chandra_darshana_visibility_model_name' => $this->localizedString($rules['chandra_darshana_visibility_model'] ?? null),
            'chandra_darshana_visibility_basis' => $rules['chandra_darshana_visibility_basis'] ?? null,
            'chandra_darshana_visibility_basis_name' => $this->localizedString($rules['chandra_darshana_visibility_basis'] ?? null),
            'ekadashi_nirnay_table' => (bool) ($rules['ekadashi_nirnay_table'] ?? false) || $this->isEkadashiNirnayRuleMetadata($rules) ? true : null,
            'pradosh_truth_table' => (bool) ($rules['pradosh_truth_table'] ?? false) || $this->isPradoshRuleMetadata($rules) ? true : null,
            'sankashti_truth_table' => (bool) ($rules['sankashti_truth_table'] ?? false) || $this->isSankashtiRuleMetadata($rules) ? true : null,
            'vinayaki_chaturthi_truth_table' => $rules['vinayaki_chaturthi_truth_table'] ?? null,
            'masik_janmashtami_truth_table' => $rules['masik_janmashtami_truth_table'] ?? null,
            'weekday_classifier_after_resolution' => $rules['weekday_classifier_after_resolution'] ?? null,
            'ekadesha_coverage_allowed' => $rules['ekadesha_coverage_allowed'] ?? null,
            'deepotsav_sequence' => $rules['deepotsav_sequence'] ?? null,
            'location_sensitive' => $rules['location_sensitive'] ?? null,
            'sect_specific' => $rules['sect_specific'] ?? null,
            'tradition_profile' => $this->localizedString($rules['tradition_profile'] ?? null),
            'tradition_profile_key' => $rules['tradition_profile'] ?? null,
            'ritual_profile' => $this->localizedString($rules['ritual_profile'] ?? null),
            'ritual_profile_key' => $rules['ritual_profile'] ?? null,
            'worship_profile' => $this->localizedString($rules['worship_profile'] ?? null),
            'worship_profile_key' => $rules['worship_profile'] ?? null,
            'fasting_guidance' => $this->localizedString($rules['fasting_guidance_key'] ?? null),
            'fasting_guidance_key' => $rules['fasting_guidance_key'] ?? null,
            'rule_convention' => $this->localizedString($rules['rule_convention'] ?? null),
            'rule_convention_key' => $rules['rule_convention'] ?? null,
            'tithi_boundary_rule' => $rules['tithi_boundary_rule'] ?? null,
            'tithi_boundary_rule_name' => $this->localizedString(isset($rules['tithi_boundary_rule']) ? 'tithi_boundary_' . $rules['tithi_boundary_rule'] : null),
            'govatsa_equal_pradosha_preference' => $rules['govatsa_equal_pradosha_preference'] ?? null,
            'vijaya_kaal_primary' => $rules['vijaya_kaal_primary'] ?? null,
            'gujarati_special_case' => $rules['gujarati_special_case'] ?? null,
            'dual_day_rule' => $this->localizedString($rules['dual_day_rule'] ?? null),
            'dual_day_rule_key' => $rules['dual_day_rule'] ?? null,
            'after_sunset_next_day_punya_rule' => $rules['after_sunset_next_day_punya_rule'] ?? null,
            'reject_anumati_purnima' => $rules['reject_anumati_purnima'] ?? null,
            'preferred_nakshatra' => is_string($nakshatraRaw) && $nakshatraRaw !== '' ? $this->localizedNakshatraName($nakshatraRaw) : null,
            'preferred_nakshatra_key' => $rules['nakshatra'] ?? null,
            'adhika' => $this->formatAdhikaRule($rules),
            'relative_day' => $this->formatRelativeDayRule($rules),
            'parent_festival' => is_string($parentFestivalRaw) && $parentFestivalRaw !== '' ? Localization::translate('Festival', $parentFestivalRaw) : null,
            'parent_festival_key' => $parentFestivalRaw,
            'calendar_rule' => $this->formatCalendarRuleMetadata($rules['calendar_rule'] ?? null),
            'astronomy_rule' => $this->formatAstronomyRuleMetadata($rules['astronomy_rule'] ?? null),
            'resolution_policy' => $this->formatResolutionPolicyMetadata($rules['resolution_policy'] ?? null),
            'ritual_layers' => $this->formatRitualLayersMetadata($rules['ritual_layers'] ?? null),
            'source_refs' => $this->formatSourceRefs($rules['source_refs'] ?? null),
            'source_ref_keys' => $rules['source_refs'] ?? null,
            'source_evidence' => $this->formatSourceEvidence($rules['source_evidence'] ?? null),
            'textual_variants' => $rules['textual_variants'] ?? null,
            'resolver_compatibility' => $rules['resolver_compatibility'] ?? null,
            'unresolved_conditions' => $this->formatLocalizedDisplayList($rules['unresolved_conditions'] ?? null),
            'unresolved_condition_keys' => $rules['unresolved_conditions'] ?? null,
        ];

        return $this->filterEmptyMetadata($basis);
    }

    /** Whether the rule is an Ekadashi nirnay truth-table profile. {@see Support\FestivalShared::isEkadashiNirnayRule()} */
    private function isEkadashiNirnayRuleMetadata(array $rules): bool
    {
        return FestivalShared::isEkadashiNirnayRule($rules);
    }

    /** Whether the rule is a Pradosh family profile. {@see Support\FestivalShared::isPradoshRule()} */
    private function isPradoshRuleMetadata(array $rules): bool
    {
        return FestivalShared::isPradoshRule($rules);
    }

    /** Whether the rule is a Sankashti Chaturthi profile. {@see Support\FestivalShared::isSankashtiRule()} */
    private function isSankashtiRuleMetadata(array $rules): bool
    {
        return FestivalShared::isSankashtiRule($rules);
    }

    private function formatCalendarRuleMetadata(mixed $rule): ?array
    {
        if (!is_array($rule) || $rule === []) {
            return null;
        }

        $paksha = $rule['paksha'] ?? null;
        $pakshaAmanta = $rule['paksha_amanta'] ?? null;
        $pakshaPurnimanta = $rule['paksha_purnimanta'] ?? null;
        $formatted = [
            'tithi' => $this->formatTithiRule($rule['tithi'] ?? null, $paksha),
            'paksha' => $paksha,
            'paksha_name' => $this->localizedPakshaName($paksha),
            'paksha_amanta' => $pakshaAmanta,
            'paksha_amanta_name' => $this->localizedPakshaName($pakshaAmanta),
            'paksha_purnimanta' => $pakshaPurnimanta,
            'paksha_purnimanta_name' => $this->localizedPakshaName($pakshaPurnimanta),
            'month_amanta' => $rule['month_amanta'] ?? null,
            'month_amanta_name' => $this->localizedMonthName($rule['month_amanta'] ?? null),
            'month_purnimanta' => $rule['month_purnimanta'] ?? null,
            'month_purnimanta_name' => $this->localizedMonthName($rule['month_purnimanta'] ?? null),
        ];

        return $this->filterEmptyMetadata($formatted);
    }

    private function formatAstronomyRuleMetadata(mixed $rule): ?array
    {
        if (!is_array($rule) || $rule === []) {
            return null;
        }

        $nakshatra = $rule['nakshatra'] ?? null;
        $formatted = [
            'nakshatra' => is_string($nakshatra) && $nakshatra !== '' ? $this->localizedNakshatraName($nakshatra) : $nakshatra,
            'nakshatra_key' => $nakshatra,
            'require_sunrise_vyapini' => $rule['require_sunrise_vyapini'] ?? null,
            'sunrise_reference' => $rule['sunrise_reference'] ?? null,
            'sunrise_reference_name' => $this->localizedString($rule['sunrise_reference'] ?? null),
        ];

        return $this->filterEmptyMetadata($formatted);
    }

    private function formatResolutionPolicyMetadata(mixed $policy): ?array
    {
        if (!is_array($policy) || $policy === []) {
            return null;
        }

        $formatted = [
            'vriddhi_preference' => $policy['vriddhi_preference'] ?? null,
            'kshaya_policy' => $policy['kshaya_policy'] ?? null,
            'kshaya_policy_name' => $this->localizedString($policy['kshaya_policy'] ?? null),
            'dual_day_rule' => $this->localizedString($policy['dual_day_rule'] ?? null),
            'dual_day_rule_key' => $policy['dual_day_rule'] ?? null,
            'dual_day_rule_name' => $this->localizedString($policy['dual_day_rule'] ?? null),
        ];

        return $this->filterEmptyMetadata($formatted);
    }

    private function formatRitualLayersMetadata(mixed $layers): ?array
    {
        if (!is_array($layers) || $layers === []) {
            return null;
        }

        $formatted = [];
        foreach ($layers as $layer) {
            if (!is_string($layer) || $layer === '') {
                continue;
            }

            $formatted[] = [
                'key' => $layer,
                'name' => $this->localizedString($layer),
            ];
        }

        return $formatted === [] ? null : $formatted;
    }

    private function buildResolutionMetadata(?array $resolved): array
    {
        if ($resolved === null) {
            return [];
        }

        $allowed = [
            'festival_name',
            'required_tithi',
            'required_nakshatra',
            'paksha',
            'karmakala_type',
            'tithi_at_karmakala_today',
            'tithi_at_karmakala_tomorrow',
            'tithi_coverage_seconds_today',
            'tithi_coverage_seconds_tomorrow',
            'tithi_at_sunrise_today',
            'tithi_at_sunrise_tomorrow',
            'is_tithi_vriddhi',
            'is_tithi_kshaya',
            'target_tithi_start_jd',
            'target_tithi_end_jd',
            'standard_date',
            'observance_date',
            'observance_note',
            'decision',
        ];

        $out = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $resolved)) {
                $out[$key] = $resolved[$key];
            }
        }

        if (isset($out['festival_name'])) {
            $out['festival_name_key'] = (string) $out['festival_name'];
            $out['festival_name'] = Localization::translate('Festival', (string) $out['festival_name']);
            $out['festival_name_localized'] = $out['festival_name'];
        }

        if (isset($out['paksha'])) {
            $out['paksha_name'] = $this->localizedPakshaName($out['paksha']);
        }

        if (isset($out['karmakala_type'])) {
            $out['karmakala_type_name'] = $this->localizedString($out['karmakala_type']);
        }

        if (isset($out['required_nakshatra'])) {
            $rawNakshatra = (string) $out['required_nakshatra'];
            $out['required_nakshatra_key'] = $rawNakshatra;
            $out['required_nakshatra'] = $this->localizedNakshatraName($rawNakshatra);
            $out['required_nakshatra_name'] = $out['required_nakshatra'];
        }

        if (isset($out['decision']) && is_array($out['decision'])) {
            $out['decision'] = $this->localizeDecisionMetadata($out['decision']);
        }

        return $this->filterEmptyMetadata($out);
    }

    private function inferFestivalBasis(array $rules): string
    {
        if ((bool) ($rules['nakshatra_only'] ?? false)) {
            return 'nakshatra';
        }

        return match ((string) ($rules['type'] ?? 'tithi')) {
            'solar_sankranti', 'solar' => 'solar',
            'fixed_date' => 'gregorian_fixed_date',
            'weekday' => 'weekday_recurrence',
            'weekday_in_month' => 'weekday_in_lunar_month',
            'weekday_tithi' => 'weekday_and_tithi',
            'derived_vaishnava_ekadashi', 'derived_mahadvadashi', 'derived_adhika_month_boundary' => 'derived_observance',
            'day_after' => 'relative_day_after_parent_festival',
            default => 'tithi',
        };
    }

    private function formatTithiRule(mixed $ruleTithi, mixed $paksha): ?array
    {
        if ($ruleTithi === null || $ruleTithi === '') {
            return null;
        }

        $numbers = array_values(array_map(intval(...), is_array($ruleTithi) ? $ruleTithi : [$ruleTithi]));
        $pakshaName = is_string($paksha) && $paksha !== '' ? $paksha : null;
        $absoluteNumbers = array_map(static function (int $number) use ($pakshaName): int {
            if ($pakshaName === 'Krishna' && $number <= 15) {
                return $number + 15;
            }

            return $number;
        }, $numbers);

        return $this->filterEmptyMetadata([
            'numbers' => $numbers,
            'paksha' => $pakshaName,
            'paksha_name' => $this->localizedPakshaName($pakshaName),
            'absolute_numbers' => $absoluteNumbers,
            'names' => array_map($this->safeTithiName(...), $absoluteNumbers),
        ]);
    }

    private function safeTithiName(int $absoluteNumber): ?string
    {
        if ($absoluteNumber < 1 || $absoluteNumber > 30) {
            return null;
        }

        return Tithi::from($absoluteNumber)->getName();
    }

    private function formatMonthRule(array $rules, ?string $calendarTypeOverride = null): ?array
    {
        if (!isset($rules['month_amanta']) && !isset($rules['month_purnimanta'])) {
            return null;
        }

        $calendarType = strtolower((string) ($calendarTypeOverride ?? AstroCore::getConfig('panchang.defaults.calendar_type', 'amanta')));
        $field = $calendarType === 'purnimanta' ? 'month_purnimanta' : 'month_amanta';
        $fallbackField = $field === 'month_purnimanta' ? 'month_amanta' : 'month_purnimanta';
        $month = $rules[$field] ?? $rules[$fallbackField] ?? null;

        return $this->filterEmptyMetadata([
            'calendar_type' => $calendarType === 'purnimanta' ? 'purnimanta' : 'amanta',
            'value' => $this->localizedMonthName($month),
            'value_key' => $month,
            'name' => $this->localizedMonthName($month),
        ]);
    }

    private function formatRashiRule(mixed $rashi): ?array
    {
        if ($rashi === null || $rashi === '') {
            return null;
        }

        $index = (int) $rashi;
        if ($index < 0 || $index > 11) {
            return ['index' => $index];
        }

        $sign = Rasi::from($index);
        return [
            'index' => $index,
            'number' => $index + 1,
            'name' => $sign->getName(),
            'english_name' => $sign->getEnglishName(),
            'symbol' => $sign->getSymbol(),
        ];
    }

    private function formatFixedDateRule(array $rules): ?array
    {
        if (!isset($rules['month'], $rules['day'])) {
            return null;
        }

        return [
            'month' => (int) $rules['month'],
            'day' => (int) $rules['day'],
        ];
    }

    private function formatWeekdayRule(mixed $weekday): ?array
    {
        if ($weekday === null || $weekday === '') {
            return null;
        }

        $number = (int) $weekday;
        if ($number < 0 || $number > 6) {
            return ['number' => $number];
        }

        $vara = Vara::from($number);
        return [
            'number' => $number,
            'name' => $vara->getName(),
            'english_name' => $vara->getEnglishName(),
        ];
    }

    private function formatAdhikaRule(array $rules): ?array
    {
        if (!array_key_exists('allow_adhika', $rules) && !array_key_exists('allows_adhika', $rules) && !array_key_exists('adhika_only', $rules)) {
            return null;
        }

        return $this->filterEmptyMetadata([
            'allow_adhika' => $rules['allow_adhika'] ?? null,
            'allows_adhika' => $rules['allows_adhika'] ?? null,
            'adhika_only' => $rules['adhika_only'] ?? null,
        ]);
    }

    private function formatRelativeDayRule(array $rules): ?array
    {
        if (($rules['type'] ?? '') !== 'day_after') {
            return null;
        }

        return $this->filterEmptyMetadata([
            'parent_festival' => isset($rules['parent_festival']) ? Localization::translate('Festival', (string) $rules['parent_festival']) : null,
            'parent_festival_key' => $rules['parent_festival'] ?? null,
            'parent_festival_name' => isset($rules['parent_festival']) ? Localization::translate('Festival', (string) $rules['parent_festival']) : null,
            'days_after' => isset($rules['days_after']) ? (int) $rules['days_after'] : null,
        ]);
    }

    private function localizeDecisionMetadata(array $decision): array
    {
        if (isset($decision['winning_reason'])) {
            $reasonRaw = (string) $decision['winning_reason'];
            $decision['winning_reason_key'] = $reasonRaw;
            $decision['winning_reason'] = $this->localizedString($reasonRaw);
            $decision['winning_reason_name'] = $decision['winning_reason'];
        }

        if (isset($decision['parent_festival'])) {
            $parentRaw = (string) $decision['parent_festival'];
            $decision['parent_festival_key'] = $parentRaw;
            $decision['parent_festival'] = Localization::translate('Festival', $parentRaw);
            $decision['parent_festival_name'] = $decision['parent_festival'];
        }

        if (isset($decision['nakshatra_name'])) {
            $nakshatraRaw = (string) $decision['nakshatra_name'];
            $decision['nakshatra_name_key'] = $nakshatraRaw;
            $decision['nakshatra_name'] = $this->localizedNakshatraName($nakshatraRaw);
            $decision['nakshatra_name_localized'] = $decision['nakshatra_name'];
        }

        if (isset($decision['preferred_nakshatra'])) {
            $preferredRaw = (string) $decision['preferred_nakshatra'];
            $decision['preferred_nakshatra_key'] = $preferredRaw;
            $decision['preferred_nakshatra'] = $this->localizedNakshatraName($preferredRaw);
            $decision['preferred_nakshatra_name'] = $decision['preferred_nakshatra'];
        }

        if (isset($decision['dual_day_rule'])) {
            $ruleRaw = (string) $decision['dual_day_rule'];
            $decision['dual_day_rule_key'] = $ruleRaw;
            $decision['dual_day_rule'] = $this->localizedString($ruleRaw);
            $decision['dual_day_rule_name'] = $decision['dual_day_rule'];
        }

        if (isset($decision['bhadra_decision']) && is_array($decision['bhadra_decision'])) {
            $decision['bhadra_decision'] = $this->localizeDecisionMetadata($decision['bhadra_decision']);
        }

        if (isset($decision['reason'])) {
            $reasonRaw = (string) $decision['reason'];
            $decision['reason_key'] = $reasonRaw;
            $decision['reason'] = $this->localizedString($reasonRaw);
            $decision['reason_name'] = $decision['reason'];
        }

        if (isset($decision['sankashti_selection']) && is_array($decision['sankashti_selection'])) {
            $decision['sankashti_selection'] = $this->localizeNamedPayloadFields($decision['sankashti_selection']);
        }

        if (isset($decision['visibility_assessment']) && is_array($decision['visibility_assessment'])) {
            $decision['visibility_assessment'] = $this->localizeNamedPayloadFields($decision['visibility_assessment']);
        }

        return $decision;
    }

    /**
     * Localize nested user-facing string fields (special_name, reason, etc.).
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function localizeNamedPayloadFields(array $payload): array
    {
        if (isset($payload['special_name']) && is_string($payload['special_name']) && $payload['special_name'] !== '') {
            $raw = $payload['special_name'];
            $payload['special_name_key'] = $raw;
            $payload['special_name'] = Localization::translate('Festival', $raw);
        }

        if (isset($payload['reason']) && is_string($payload['reason']) && $payload['reason'] !== '') {
            $raw = $payload['reason'];
            $payload['reason_key'] = $raw;
            $payload['reason'] = $this->localizedString($raw) ?? $raw;
            $payload['reason_name'] = $payload['reason'];
        }

        if (isset($payload['winning_reason']) && is_string($payload['winning_reason']) && $payload['winning_reason'] !== '') {
            $raw = $payload['winning_reason'];
            $payload['winning_reason_key'] = $raw;
            $payload['winning_reason'] = $this->localizedString($raw) ?? $raw;
            $payload['winning_reason_name'] = $payload['winning_reason'];
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->localizeNamedPayloadFields($value);
            }
        }

        return $payload;
    }

    private function localizedPakshaName(mixed $paksha): ?string
    {
        if (!is_string($paksha) || $paksha === '') {
            return null;
        }

        return match ($paksha) {
            'Shukla' => Paksha::Shukla->getName(),
            'Krishna' => Paksha::Krishna->getName(),
            default => Localization::translate('String', $paksha),
        };
    }

    private function localizedMonthName(mixed $month): ?string
    {
        if (!is_string($month) || $month === '') {
            return null;
        }

        $normalized = $this->normalizeMonthName($month);
        foreach (self::MONTHS as $monthName => $number) {
            if ($this->normalizeMonthName($monthName) === $normalized) {
                return Masa::from($number - 1)->getName();
            }
        }

        return Localization::translate('Masa', $month);
    }

    private function localizedNakshatraName(string $nakshatra): string
    {
        foreach (Nakshatra::cases() as $case) {
            if ($case->getName('en') === $nakshatra) {
                return $case->getName();
            }
        }

        return $nakshatra;
    }

    private function nakshatraRuleMatches(string $requiredNakshatra, array $snapshotNakshatra): bool
    {
        $required = $this->resolveNakshatraNumber($requiredNakshatra);
        $current = isset($snapshotNakshatra['number'])
            ? (int) $snapshotNakshatra['number']
            : $this->resolveNakshatraNumber((string) ($snapshotNakshatra['name'] ?? ''));

        if ($required !== null && $current >= 1 && $current <= 27) {
            return $required === $current;
        }

        return strcasecmp($requiredNakshatra, (string) ($snapshotNakshatra['name'] ?? '')) === 0;
    }

    /** Resolve canonical nakshatra number (1..27). {@see Support\FestivalShared::resolveNakshatraNumber()} */
    private function resolveNakshatraNumber(string $label): ?int
    {
        return FestivalShared::resolveNakshatraNumber($label);
    }

    private function localizedString(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return Localization::translate('String', $value);
    }

    /**
     * Source evidence often contains English editorial prose. For localized JSON, only expose a
     * display value when a translation exists; keep raw prose under explicit *_key fields.
     */
    private function localizedDisplayOrNull(string $type, mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $translated = Localization::translate($type, $value);
        $locale = (string) AstroCore::getConfig('panchang.defaults.locale', 'en');

        return $locale === 'en' || $translated !== $value ? $translated : null;
    }

    private function formatSourceRefs(mixed $refs): ?array
    {
        if (!is_array($refs)) {
            return null;
        }

        $localized = [];
        foreach ($refs as $ref) {
            if (!is_string($ref) || $ref === '') {
                continue;
            }

            $localized[] = $this->localizedSourceRef($ref);
        }

        return $localized === [] ? null : $localized;
    }

    private function localizedSourceRef(string $ref): string
    {
        $direct = Localization::translate('Source', $ref);
        if ($direct !== $ref) {
            return $direct;
        }

        $patterns = [
            '/^Satsangi Jeevan(.*)$/u' => 'Satsangi Jeevan',
            '/^Nirnaya Sindhu \\/ Dharma Sindhu$/u' => 'Nirnaya Sindhu / Dharma Sindhu',
            '/^Vrat Parva Viveka \\(Priyavrat Sharma\\)$/u' => 'Vrat Parva Viveka (Priyavrat Sharma)',
            '/^Siddhanta Darpana$/u' => 'Siddhanta Darpana',
            '/^Garga Samhita(.*)$/u' => 'Garga Samhita',
        ];

        foreach ($patterns as $pattern => $base) {
            if (preg_match($pattern, $ref, $matches) !== 1) {
                continue;
            }

            $baseLocalized = Localization::translate('Source', $base);
            if ($baseLocalized === $base) {
                return $ref;
            }

            return $baseLocalized . ($matches[1] ?? '');
        }

        return $ref;
    }

    private function formatSourceEvidence(mixed $evidence): ?array
    {
        if (!is_array($evidence)) {
            return null;
        }

        $out = [];
        foreach ($evidence as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $kind = $entry['kind'] ?? null;
            $source = $entry['source'] ?? null;
            $locator = $entry['locator'] ?? null;
            $supports = $entry['supports'] ?? null;

            $out[] = $this->filterEmptyMetadata([
                'kind' => $kind,
                'kind_name' => $this->localizedDisplayOrNull('String', $kind),
                'source_key' => $source,
                'source' => is_string($source) && $source !== '' ? $this->localizedSourceRef($source) : null,
                'locator_key' => $locator,
                'locator' => $this->localizedDisplayOrNull('String', $locator),
                'supports_key' => $supports,
                'supports' => $this->localizedDisplayOrNull('String', $supports),
            ]);
        }

        return $out === [] ? null : $out;
    }

    private function formatLocalizedDisplayList(mixed $values): ?array
    {
        if (!is_array($values)) {
            return null;
        }

        $localized = [];
        foreach ($values as $value) {
            $display = $this->localizedDisplayOrNull('String', $value);
            if ($display !== null) {
                $localized[] = $display;
            }
        }

        return $localized === [] ? null : $localized;
    }

    private function filterEmptyMetadata(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = $this->filterEmptyMetadata($value);
            }

            if ($value === null || $value === []) {
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }
}
