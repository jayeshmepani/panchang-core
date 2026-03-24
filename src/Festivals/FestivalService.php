<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;
use LogicException;

/**
 * Hindu Festival Calculation Service
 * Calculates all major Hindu/Sanatan festivals based on Tithi, Nakshatra, and special rules.
 *
 * CRITICAL: This service requires PanchangService for actual tithi calculations
 * Do NOT use standalone - always use through PanchangService
 */
class FestivalService
{
    public const TITHI_VRATAS = [
        1 => ['vrata' => 'Prathama Vrata', 'deity' => 'Agni', 'benefit' => 'Fire element harmony'],
        3 => ['vrata' => 'Tritiya Vrata (Akshaya)', 'deity' => 'Parvati-Gauri', 'benefit' => 'Eternal merit'],
        4 => ['vrata' => 'Chaturthi Vrata (Vinayaka)', 'deity' => 'Lord Ganesha', 'benefit' => 'Obstacle removal'],
        6 => ['vrata' => 'Shashthi Vrata (Skanda)', 'deity' => 'Lord Kartikeya', 'benefit' => 'Progeny and health'],
        8 => ['vrata' => 'Ashtami Vrata (Durga)', 'deity' => 'Goddess Durga', 'benefit' => 'Protection'],
        9 => ['vrata' => 'Navami Vrata (Rama)', 'deity' => 'Lord Rama', 'benefit' => 'Dharma and righteousness'],
        11 => ['vrata' => 'Ekadashi Vrata (Vishnu)', 'deity' => 'Lord Vishnu', 'benefit' => 'Moksha and purity'],
        13 => ['vrata' => 'Trayodashi Vrata (Pradosha)', 'deity' => 'Lord Shiva', 'benefit' => 'Sin removal'],
        14 => ['vrata' => 'Chaturdashi Vrata (Shivaratri)', 'deity' => 'Lord Shiva', 'benefit' => 'Liberation'],
        15 => ['vrata' => 'Purnima/Amavasya Vrata', 'deity' => 'Various', 'benefit' => 'Ancestor worship'],
    ];

    /**
     * Complete list of Hindu festivals with calculation rules
     * Based on Drik Panchang, traditional texts, and regional variations.
     */
    public const FESTIVALS = [
        // Makar Sankranti family (Solar based - fixed dates)
        'Makar Sankranti' => [
            'type' => 'solar',
            'month' => 1,
            'day' => 14,
            'description' => 'Harvest festival, Sun enters Capricorn',
            'regions' => ['Pan-India'],
        ],
        'Pongal' => [
            'type' => 'solar',
            'month' => 1,
            'day' => 14,
            'description' => 'Tamil harvest festival',
            'regions' => ['Tamil Nadu'],
        ],

        // Vasant Panchami (Saraswati Puja)
        'Vasant Panchami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'karmakala_type' => 'sunrise',
            'description' => 'Worship of Goddess Saraswati, beginning of spring',
            'deity' => 'Saraswati',
        ],

        // Maha Shivaratri
        'Maha Shivaratri' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 14,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Phalguna',
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
            'vriddhi_preference' => 'last',
            'description' => 'Great night of Lord Shiva',
            'deity' => 'Shiva',
            'fasting' => true,
        ],

        // Holi family
        'Holika Dahan' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15, // Purnima
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'vriddhi_preference' => 'first',
            'description' => 'Bonfire ceremony, victory of good over evil',
            'deity' => 'Vishnu/Hiranyakashipu',
        ],
        'Holi' => [
            'type' => 'day_after',
            'parent_festival' => 'Holika Dahan',
            'days_after' => 1,
            'description' => 'Festival of colors',
            'deity' => 'Krishna',
        ],

        // Rama Navami
        'Rama Navami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'description' => 'Birth anniversary of Lord Rama',
            'deity' => 'Rama',
        ],

        // Hanuman Jayanti
        'Hanuman Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15, // Purnima
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'karmakala_type' => 'sunrise',
            'description' => 'Birth celebration of Lord Hanuman',
            'deity' => 'Hanuman',
        ],

        // Akshaya Tritiya
        'Akshaya Tritiya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'karmakala_type' => 'sunrise',
            'description' => 'Most auspicious for new beginnings and investments',
            'deity' => 'Vishnu/Lakshmi',
        ],

        // Guru Purnima
        'Guru Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15, // Purnima
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'karmakala_type' => 'sunrise',
            'description' => 'Honoring spiritual teachers and gurus',
            'deity' => 'Vyasa',
        ],

        // Raksha Bandhan
        'Raksha Bandhan' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15, // Purnima
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
            'description' => 'Brother-sister bond celebration',
            'deity' => null,
        ],

        // Varalakshmi Vratam
        'Varalakshmi Vratam' => [
            'type' => 'weekday_tithi',
            'paksha' => 'Shukla',
            'tithi' => [12, 13, 14, 15], // Can be any of these
            'weekday' => 5, // Friday
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Worship of Goddess Lakshmi for prosperity',
            'deity' => 'Lakshmi',
            'fasting' => true,
        ],

        // Krishna Janmashtami
        'Krishna Janmashtami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'nakshatra' => 'Rohini', // Preferred
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
            'prefer_nakshatra' => true,
            'vriddhi_preference' => 'last',
            'description' => 'Birth celebration of Lord Krishna',
            'deity' => 'Krishna',
            'fasting' => true,
        ],

        // Ganesh Chaturthi
        'Ganesh Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'description' => 'Birth anniversary of Lord Ganesha',
            'deity' => 'Ganesha',
        ],

        // Vishwakarma Puja
        'Vishwakarma Puja' => [
            'type' => 'solar',
            'month' => 9,
            'day' => 17,
            'description' => 'Worship of divine architect',
            'deity' => 'Vishwakarma',
        ],

        // Navaratri family
        'Navaratri Begins' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1, // Pratipada
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'karmakala_type' => 'sunrise',
            'description' => 'Nine nights of Goddess Durga worship begins',
            'deity' => 'Durga',
        ],
        'Durga Puja' => [
            'type' => 'tithi',
            'paksha' => 'Shukla',
            'tithi' => [6, 7, 8, 9, 10], // Shashthi to Dashami
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Grand celebration of Goddess Durga',
            'deity' => 'Durga',
        ],
        'Dussehra' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10, // Dashami
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
            'description' => 'Victory of Lord Rama over Ravana',
            'deity' => 'Rama/Durga',
        ],

        // Karva Chauth
        'Karva Chauth' => [
            'type' => 'tithi',
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Married women fast for husband\'s long life',
            'deity' => null,
            'fasting' => true,
        ],

        // Diwali family
        'Dhanteras' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 13, // Trayodashi
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'description' => 'Worship of Lakshmi and Dhanvantari',
            'deity' => 'Lakshmi/Dhanvantari',
        ],
        'Diwali' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15, // Amavasya
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'vriddhi_preference' => 'first',
            'description' => 'Festival of lights',
            'deity' => 'Lakshmi/Ganesha',
            'major' => true,
        ],
        'Bhai Dooj' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2, // Dwitiya
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'karmakala_type' => 'aparahna',
            'description' => 'Brother-sister relationship celebration',
            'deity' => null,
        ],

        // Chhath Puja
        'Chhath Puja' => [
            'type' => 'tithi',
            'paksha' => 'Shukla',
            'tithi' => 6, // Shashthi
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Sun God worship',
            'deity' => 'Surya',
            'fasting' => true,
            'regions' => ['Bihar', 'Uttar Pradesh', 'Jharkhand'],
        ],

        // Geeta Jayanti
        'Geeta Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11, // Ekadashi
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'karmakala_type' => 'sunrise',
            'description' => 'Celebration of Bhagavad Gita',
            'deity' => 'Krishna',
        ],
    ];

    /** Month name mapping */
    public const MONTHS = [
        'Chaitra' => 1,
        'Vaishakha' => 2,
        'Jyeshtha' => 3,
        'Ashadha' => 4,
        'Shravana' => 5,
        'Bhadrapada' => 6,
        'Ashvina' => 7,
        'Kartika' => 8,
        'Margashirsha' => 9,
        'Pausha' => 10,
        'Magha' => 11,
        'Phalguna' => 12,
    ];
    /**
     * Get festivals for a specific date using actual panchang data
     * This is the PRIMARY method - uses real tithi from PanchangService.
     */
    public function getFestivalsForDate(CarbonImmutable $date, array $panchangDetails): array
    {
        $festivals = [];
        $tithi = $panchangDetails['Tithi'] ?? null;
        $nakshatra = $panchangDetails['Nakshatra'] ?? null;

        if (!$tithi) {
            return [];
        }

        $tithiNum = (int) ($tithi['index'] ?? 0);
        $paksha = $tithi['paksha'] ?? 'Shukla';

        foreach (self::FESTIVALS as $name => $rules) {
            if ($this->matchesFestivalRules($date, $rules, $tithiNum, $paksha, $panchangDetails)) {
                $festivals[] = [
                    'name' => $name,
                    'description' => $rules['description'],
                    'deity' => $rules['deity'] ?? null,
                    'fasting' => $rules['fasting'] ?? false,
                    'regions' => $rules['regions'] ?? ['Pan-India'],
                ];
            }
        }

        return $festivals;
    }

    public static function usesClassicalResolver(array $rules): bool
    {
        return (string) ($rules['resolver'] ?? '') === 'classical';
    }

    /** Daily Sanatan observances from tithi-based vrata prescriptions. */
    public function getDailyObservances(array $panchangDetails): array
    {
        $out = [];
        $tithi = $panchangDetails['Tithi'] ?? [];
        $idx = (int) ($tithi['index'] ?? 0);
        $paksha = (string) ($tithi['paksha'] ?? '');

        if ($idx > 15) {
            $idx -= 15;
        }

        if (isset(self::TITHI_VRATAS[$idx])) {
            $rule = self::TITHI_VRATAS[$idx];
            $out[] = [
                'name' => $rule['vrata'],
                'deity' => $rule['deity'],
                'benefit' => $rule['benefit'],
                'paksha' => $paksha,
            ];
        }

        return $out;
    }

    /**
     * Year-wide aggregation is intentionally blocked at this layer.
     * Use date-wise festival computation through PanchangService.
     */
    public function getFestivalsForYear(int $year, string $pakshaSystem = 'Amanta'): array
    {
        throw new LogicException('Year-wide festival calculation is intentionally disabled in FestivalService. Use date-wise calculation via PanchangService.');
    }

    /**
     * Check if date matches festival rules using ACTUAL panchang data
     * NO PLACEHOLDERS - uses real tithi, nakshatra from PanchangService.
     */
    private function matchesFestivalRules(
        CarbonImmutable $date,
        array $rules,
        int $tithiNum,
        string $paksha,
        array $panchangDetails
    ): bool {
        $type = (string) ($rules['type'] ?? 'tithi');

        // Dependent festivals (e.g. Holi after Holika Dahan) are resolved by orchestration layer.
        if ($type === 'day_after') {
            return false;
        }

        // Check tithi match
        if (isset($rules['tithi'])) {
            $ruleTithis = is_array($rules['tithi']) ? $rules['tithi'] : [$rules['tithi']];
            if (!in_array($tithiNum, $ruleTithis, true)) {
                return false;
            }
        }

        // Check paksha match
        if (isset($rules['paksha'])) {
            $rulePakshas = is_array($rules['paksha']) ? $rules['paksha'] : [$rules['paksha']];
            if (!in_array($paksha, $rulePakshas, true)) {
                return false;
            }
        }

        // Check weekday match
        if (isset($rules['weekday'])) {
            if ($date->dayOfWeek !== $rules['weekday']) {
                return false;
            }
        }

        // Check Hindu month match for tithi-based rules
        if (isset($rules['month_amanta']) || isset($rules['month_purnimanta'])) {
            $calendar = $panchangDetails['Hindu_Calendar'] ?? [];
            $amanta = (string) ($calendar['Month_Amanta'] ?? '');
            $purnimanta = (string) ($calendar['Month_Purnimanta'] ?? '');
            $monthMatch = false;

            if (isset($rules['month_amanta']) && strcasecmp((string) $rules['month_amanta'], $amanta) === 0) {
                $monthMatch = true;
            }
            if (isset($rules['month_purnimanta']) && strcasecmp((string) $rules['month_purnimanta'], $purnimanta) === 0) {
                $monthMatch = true;
            }

            if (!$monthMatch) {
                return false;
            }
        }

        // Check fixed solar dates
        if (($rules['type'] ?? '') === 'solar' && isset($rules['month'], $rules['day'])) {
            if ((int) $rules['month'] !== (int) $date->month || (int) $rules['day'] !== (int) $date->day) {
                return false;
            }
        }

        // Check nakshatra match (if specified)
        if (isset($rules['nakshatra'], $panchangDetails['Nakshatra']['name'])) {
            if ($panchangDetails['Nakshatra']['name'] !== $rules['nakshatra']) {
                return false;
            }
        }

        return true;
    }
}
