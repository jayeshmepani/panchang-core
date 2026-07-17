<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;

/**
 * Special festival day matchers (Anvadhan, Kalashtami, Sheetala, regional rules).
 *
 * Structure-only split from FestivalService. Algorithms unchanged.
 *
 * @see docs/FESTIVAL_REFACTOR_PARITY_CONTRACT.md
 */
trait FestivalSpecialMatchers
{
    private function matchesAnvadhanRule(
        CarbonImmutable $date,
        array $todayDetails,
        array $tomorrowDetails,
        ?array $yesterdayDetails,
        ?callable $fetchHistoricalSnapshot
    ): bool
    {
        if ($this->isAnvadhanLatePurnimaTransitionDay($todayDetails, $tomorrowDetails)) {
            return true;
        }

        if ($this->matchesAnvadhanPurnimaAnchor($date, $todayDetails, $tomorrowDetails)) {
            $isAdhika = (bool) (($todayDetails['Hindu_Calendar']['Is_Adhika'] ?? false));
            $previousAlsoPurnima = $yesterdayDetails !== null
                && (
                    $this->matchesAnvadhanPurnimaAnchor($date->subDay(), $yesterdayDetails, $todayDetails)
                    || $this->isAnvadhanLatePurnimaTransitionDay($yesterdayDetails, $todayDetails)
                );
            $afterTomorrow = $fetchHistoricalSnapshot === null ? null : $fetchHistoricalSnapshot($date->addDays(2));
            $nextAlsoPurnima = is_array($afterTomorrow)
                && $this->matchesAnvadhanPurnimaAnchor($date->addDay(), $tomorrowDetails, $afterTomorrow);

            return $isAdhika ? !$nextAlsoPurnima : !$previousAlsoPurnima;
        }

        if ($this->isAnvadhanDarshaSunriseDay($todayDetails) && $yesterdayDetails !== null) {
            if ($this->shouldMoveAnvadhanDarshaToSunriseDay($yesterdayDetails, $todayDetails)) {
                return true;
            }

            if ($this->shouldPreferAnvadhanDarshaTransitionDay($yesterdayDetails, $todayDetails)) {
                return false;
            }
        }

        if (!$this->matchesAnvadhanDarshaAnchor($date, $todayDetails, $tomorrowDetails)) {
            return false;
        }

        if ($this->shouldMoveAnvadhanDarshaToSunriseDay($todayDetails, $tomorrowDetails)) {
            return false;
        }

        if ($this->shouldPreferAnvadhanDarshaTransitionDay($todayDetails, $tomorrowDetails)) {
            return true;
        }

        $afterTomorrow = $fetchHistoricalSnapshot === null ? null : $fetchHistoricalSnapshot($date->addDays(2));
        $nextAlsoDarsha = is_array($afterTomorrow)
            && $this->matchesAnvadhanDarshaAnchor($date->addDay(), $tomorrowDetails, $afterTomorrow)
            && !$this->shouldMoveAnvadhanDarshaToSunriseDay($tomorrowDetails, $afterTomorrow);

        return !$nextAlsoDarsha;
    }

    private function matchesBrahmaSavarniManvadiRule(array $todayDetails): bool
    {
        if ($this->tithiPaksha($todayDetails) !== 'Shukla') {
            return false;
        }

        $calendar = (array) ($todayDetails['Hindu_Calendar'] ?? []);
        $samvatsara = (string) ($calendar['Samvatsara'] ?? '');
        $month = $this->normalizeMonthName((string) ($calendar['Month_Amanta_En'] ?? $calendar['Month_Amanta'] ?? ''));

        if ($samvatsara === 'Parabhava') {
            return $this->tithiPhase($todayDetails) === 7 && $month === 'magha';
        }

        return $this->tithiPhase($todayDetails) === 10 && $month === 'pausha';
    }

    private function matchesSheetalaAshtamiRule(
        array $todayDetails,
        array $tomorrowDetails,
        ?array $yesterdayDetails,
        array $rules
    ): bool {
        if (!$this->monthRuleMatches($rules, (array) ($todayDetails['Hindu_Calendar'] ?? []))) {
            return false;
        }

        if ($this->isSheetalaSaptamiViddhaDay($todayDetails, $tomorrowDetails)) {
            return true;
        }

        if ($this->tithiPaksha($todayDetails) !== 'Krishna' || $this->tithiPhase($todayDetails) !== 8) {
            return false;
        }

        return $yesterdayDetails === null
            || !$this->isSheetalaSaptamiViddhaDay($yesterdayDetails, $todayDetails);
    }

    private function isSheetalaSaptamiViddhaDay(array $todayDetails, array $tomorrowDetails): bool
    {
        if ($this->tithiPaksha($todayDetails) !== 'Krishna'
            || $this->tithiPhase($todayDetails) !== 7
            || $this->tithiPaksha($tomorrowDetails) !== 'Krishna'
            || $this->tithiPhase($tomorrowDetails) !== 8) {
            return false;
        }

        $ashtamiStartJd = $this->tithiEndJd($todayDetails);
        $todayAparahna = $this->karmakalaWindowJdFromDetails($todayDetails, 'aparahna');
        $ctx = (array) ($todayDetails['Resolution_Context'] ?? []);
        $sunsetJd = $ctx['sunset_jd'] ?? null;
        if ($ashtamiStartJd === null || $todayAparahna === null || !is_numeric($sunsetJd)) {
            return false;
        }

        return $ashtamiStartJd > $todayAparahna['end_jd']
            && $ashtamiStartJd < (float) $sunsetJd;
    }

    private function matchesKalashtamiRule(array $todayDetails, array $tomorrowDetails, ?array $yesterdayDetails): bool
    {
        if ($this->isAdhikaDay($todayDetails)) {
            return false;
        }

        if ($this->isKalashtamiTransitionDay($todayDetails, $tomorrowDetails)) {
            return true;
        }

        if ($this->tithiPaksha($todayDetails) !== 'Krishna' || $this->tithiPhase($todayDetails) !== 8) {
            return false;
        }

        return $yesterdayDetails === null
            || (
                $this->tithiPhase($yesterdayDetails) !== 8
                && !$this->isKalashtamiTransitionDay($yesterdayDetails, $todayDetails)
            );
    }

    private function isKalashtamiTransitionDay(array $todayDetails, array $tomorrowDetails): bool
    {
        if ($this->tithiPaksha($todayDetails) !== 'Krishna'
            || $this->tithiPhase($todayDetails) !== 7
            || $this->tithiPaksha($tomorrowDetails) !== 'Krishna') {
            return false;
        }

        if ($this->tithiPhase($tomorrowDetails) === 8) {
            return $this->isSheetalaSaptamiViddhaDay($todayDetails, $tomorrowDetails);
        }

        if ($this->tithiPhase($tomorrowDetails) !== 9) {
            return false;
        }

        $ashtamiStartJd = $this->tithiEndJd($todayDetails);
        $navamiStartJd = (array) ($tomorrowDetails['Resolution_Context'] ?? []);
        $navamiStartJd = $navamiStartJd['tithi_start_jd'] ?? null;

        $ctx = (array) ($todayDetails['Resolution_Context'] ?? []);
        $nextSunriseJd = $ctx['next_sunrise_jd'] ?? null;

        return $ashtamiStartJd !== null
            && is_numeric($navamiStartJd)
            && is_numeric($nextSunriseJd)
            && $ashtamiStartJd < (float) $nextSunriseJd
            && (float) $navamiStartJd <= (float) $nextSunriseJd;
    }

    private function matchesAttukalPongalRule(array $todayDetails, array $tomorrowDetails): bool
    {
        if (!$this->sunSignRuleMatches(10, $todayDetails)) {
            return false;
        }

        $todayNakshatra = (string) ($todayDetails['Nakshatra']['name'] ?? '');
        if ($todayNakshatra === 'Purva Phalguni') {
            if ($this->tithiPaksha($todayDetails) !== 'Krishna') {
                return true;
            }

            return $this->tithiPhase($todayDetails) !== 1;
        }

        $tomorrowNakshatra = (string) ($tomorrowDetails['Nakshatra']['name'] ?? '');

        return $this->tithiPaksha($todayDetails) === 'Shukla'
            && $this->tithiPhase($todayDetails) === 15
            && $tomorrowNakshatra === 'Purva Phalguni'
            && $this->tithiPaksha($tomorrowDetails) === 'Krishna'
            && $this->tithiPhase($tomorrowDetails) === 1
            && $this->sunSignRuleMatches(10, $tomorrowDetails);
    }

    private function matchesChapcharKutRule(
        CarbonImmutable $date,
        array $todayDetails,
        ?callable $fetchHistoricalSnapshot
    ): bool
    {
        if ($date->month !== 3 || $date->dayOfWeek !== CarbonImmutable::FRIDAY) {
            return false;
        }

        $firstFriday = $date->startOfMonth();
        while ($firstFriday->dayOfWeek !== CarbonImmutable::FRIDAY) {
            $firstFriday = $firstFriday->addDay();
        }

        $firstFridayDetails = $date->isSameDay($firstFriday)
            ? $todayDetails
            : ($fetchHistoricalSnapshot === null ? null : $fetchHistoricalSnapshot($firstFriday));
        if (!is_array($firstFridayDetails)) {
            return false;
        }

        $firstFridayIsAmantaPhalguna = $this->normalizeMonthName((string) (
            $firstFridayDetails['Hindu_Calendar']['Month_Amanta_En']
            ?? $firstFridayDetails['Hindu_Calendar']['Month_Amanta']
            ?? ''
        )) === 'phalguna';

        if ($date->isSameDay($firstFriday)) {
            return !$firstFridayIsAmantaPhalguna;
        }

        return $date->isSameDay($firstFriday->addWeek()) && $firstFridayIsAmantaPhalguna;
    }

    private function matchesBhanuSaptamiRule(CarbonImmutable $date, array $todayDetails, array $tomorrowDetails): bool
    {
        if ($date->dayOfWeek !== CarbonImmutable::SUNDAY || $this->tithiPaksha($todayDetails) !== 'Shukla') {
            return false;
        }

        if ($this->tithiPhase($todayDetails) === 7) {
            return true;
        }

        if ($this->tithiPhase($todayDetails) !== 6 || $this->tithiPhase($tomorrowDetails) !== 7) {
            return false;
        }

        $saptamiStartJd = $this->tithiEndJd($todayDetails);
        $ctx = (array) ($todayDetails['Resolution_Context'] ?? []);
        $sunsetJd = $ctx['sunset_jd'] ?? null;

        return $saptamiStartJd !== null
            && is_numeric($sunsetJd)
            && $saptamiStartJd < (float) $sunsetJd;
    }

    private function matchesAnvadhanPurnimaAnchor(CarbonImmutable $date, array $todayDetails, array $tomorrowDetails): bool
    {
        $rules = [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'description' => 'Monthly Purnima fast selected by the 18 daytime-ghadi Chaturdashi condition',
            'deity' => 'Vishnu/Chandra',
            'allow_adhika' => true,
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'purnima_vrat_18_ghadi_rule' => true,
        ];

        $resolved = $this->ruleEngine->resolveMajorFestival('Anvadhan Purnima Anchor', $rules, $date, $todayDetails, $tomorrowDetails);

        return $resolved !== null && (string) ($resolved['observance_date'] ?? '') === $date->toDateString();
    }

    private function matchesAnvadhanDarshaAnchor(CarbonImmutable $date, array $todayDetails, array $tomorrowDetails): bool
    {
        $rules = [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'description' => 'Darsha Amavasya is the monthly Amavasya observance associated with pitru rites and ancestral remembrance.',
            'deity' => 'Chandra/Pitrus',
            'allow_adhika' => true,
            'karmakala_type' => 'aparahna',
            'darsha_amavasya_aparahna_table' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
        ];

        $resolved = $this->ruleEngine->resolveMajorFestival('Anvadhan Darsha Anchor', $rules, $date, $todayDetails, $tomorrowDetails);

        return $resolved !== null && (string) ($resolved['observance_date'] ?? '') === $date->toDateString();
    }

    private function isAnvadhanLatePurnimaTransitionDay(array $todayDetails, array $tomorrowDetails): bool
    {
        if ($this->tithiPaksha($todayDetails) !== 'Shukla'
            || $this->tithiPhase($todayDetails) !== 14
            || !$this->isAnvadhanPurnimaSunriseDay($tomorrowDetails)) {
            return false;
        }

        $purnimaStartJd = $this->tithiEndJd($todayDetails);
        $purnimaEndJd = $this->tithiEndJd($tomorrowDetails);
        $todayCtx = (array) ($todayDetails['Resolution_Context'] ?? []);
        if ($purnimaStartJd === null || $purnimaEndJd === null || !is_numeric($todayCtx['sunset_jd'] ?? null)) {
            return false;
        }

        if ($this->isAdhikaDay($todayDetails) || $purnimaStartJd >= (float) $todayCtx['sunset_jd']) {
            return false;
        }

        return !$this->intervalCoversFullKarmakala($purnimaStartJd, $purnimaEndJd, $tomorrowDetails, 'aparahna');
    }

    private function isAnvadhanPurnimaSunriseDay(array $details): bool
    {
        return $this->tithiPaksha($details) === 'Shukla'
            && $this->tithiPhase($details) === 15;
    }

    private function shouldMoveAnvadhanDarshaToSunriseDay(array $todayDetails, array $tomorrowDetails): bool
    {
        $amavasyaEndJd = $this->tithiEndJd($tomorrowDetails);
        if (!$this->isAnvadhanDarshaTransitionPair($todayDetails, $tomorrowDetails) || $amavasyaEndJd === null) {
            return false;
        }

        $tomorrowMadhyahna = $this->karmakalaWindowJdFromDetails($tomorrowDetails, 'madhyahna');
        $tomorrowAparahna = $this->karmakalaWindowJdFromDetails($tomorrowDetails, 'aparahna');
        if ($tomorrowMadhyahna === null || $tomorrowAparahna === null) {
            return false;
        }

        return $amavasyaEndJd >= $tomorrowMadhyahna['start_jd']
            && $amavasyaEndJd < $tomorrowAparahna['start_jd'];
    }

    private function shouldPreferAnvadhanDarshaTransitionDay(array $todayDetails, array $tomorrowDetails): bool
    {
        $amavasyaStartJd = $this->tithiEndJd($todayDetails);
        $amavasyaEndJd = $this->tithiEndJd($tomorrowDetails);
        if (!$this->isAnvadhanDarshaTransitionPair($todayDetails, $tomorrowDetails)
            || $amavasyaStartJd === null
            || $amavasyaEndJd === null) {
            return false;
        }

        $todayAparahna = $this->karmakalaWindowJdFromDetails($todayDetails, 'aparahna');
        $tomorrowAparahna = $this->karmakalaWindowJdFromDetails($tomorrowDetails, 'aparahna');
        if ($todayAparahna === null || $tomorrowAparahna === null) {
            return false;
        }

        return $amavasyaStartJd < $todayAparahna['start_jd']
            && $amavasyaEndJd > $tomorrowAparahna['start_jd'];
    }

    private function isAnvadhanDarshaTransitionPair(array $todayDetails, array $tomorrowDetails): bool
    {
        return $this->tithiPaksha($todayDetails) === 'Krishna'
            && $this->tithiPhase($todayDetails) === 14
            && $this->isAnvadhanDarshaSunriseDay($tomorrowDetails);
    }

    private function isAnvadhanDarshaSunriseDay(array $details): bool
    {
        return $this->tithiPaksha($details) === 'Krishna'
            && $this->tithiPhase($details) === 15;
    }
}
