<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\Localization;
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
        1 => [
            'vrata' => 'Pratipada Vrata',
            'deity' => 'Agni',
            'benefit' => 'Removal of physical ailments; purification of internal fire (Jatharagni)',
            'description' => 'Auspicious for house construction and travel.',
        ],
        2 => [
            'vrata' => 'Dwitiya Vrata',
            'deity' => 'Ashwini Kumaras / Brahma',
            'benefit' => 'Stable health and relief from chronic diseases',
            'description' => 'Favorable for ornaments, music, and Vastu Karma.',
        ],
        3 => [
            'vrata' => 'Tritiya Vrata',
            'deity' => 'Gauri / Parvati',
            'benefit' => 'Marital bliss (Saubhagya) and desired life partner',
            'description' => 'Reading Gauri Kalyanam is highly recommended today.',
        ],
        4 => [
            'vrata' => 'Chaturthi Vrata',
            'deity' => 'Ganesha',
            'benefit' => 'Removal of obstacles (Vighnaharta)',
            'shukla_name' => 'Vinayaka Chaturthi',
            'krishna_name' => 'Sankashti Chaturthi',
            'gujarati_special' => 'Bol Choth (Shravan Krishna 4)',
        ],
        5 => [
            'vrata' => 'Panchami Vrata',
            'deity' => 'Naga Devatas',
            'benefit' => 'Protection from toxins, serpents, and hidden enemies',
            'description' => 'Lending money on this day is traditionally avoided.',
        ],
        6 => [
            'vrata' => 'Shashthi Vrata',
            'deity' => 'Kartikeya / Skanda',
            'benefit' => 'Victory over challenges and protection of children',
            'gujarati_special' => 'Randhan Chhath (Shravan Krishna 6)',
        ],
        7 => [
            'vrata' => 'Saptami Vrata',
            'deity' => 'Surya (Sun God)',
            'benefit' => 'Vitality, eye health, and spiritual radiance',
            'gujarati_special' => 'Shitala Satam (Shravan Krishna 7)',
        ],
        8 => [
            'vrata' => 'Ashtami Vrata',
            'deity' => 'Durga / Shiva (Rudra)',
            'benefit' => 'Strength to overcome enemies and internal fear',
            'shukla_note' => 'Durga Ashtami - Divine Shakti worship.',
            'krishna_note' => 'Krishna Janmashtami (Shravan Krishna 8).',
        ],
        9 => [
            'vrata' => 'Navami Vrata',
            'deity' => 'Sita-Rama / Durga',
            'benefit' => 'Victory of Dharma and divine protection',
            'description' => 'Auspicious for Shakti Upasana and Rama Taraka Mantra.',
        ],
        10 => [
            'vrata' => 'Dashami Vrata',
            'deity' => 'Ashta Dikpalakas',
            'benefit' => 'Success in directions and vehicle-related prosperity',
            'description' => 'Excellent for beginning new business ventures.',
        ],
        11 => [
            'vrata' => 'Ekadashi Vrata',
            'deity' => 'Vishnu',
            'benefit' => 'Moksha (Liberation) and extreme mental peace',
            'description' => 'Strict fasting (Nirjala or Phalahari) to purify past karmas.',
            'gujarati_special' => 'Gauri Vrat begins (Ashad Shukla 11).',
        ],
        12 => [
            'vrata' => 'Dwadashi Vrata',
            'deity' => 'Vishnu / Hanuman',
            'benefit' => 'Global welfare and absolute stability',
            'description' => 'Tulsi Puja is particularly auspicious.',
        ],
        13 => [
            'vrata' => 'Trayodashi Vrata',
            'deity' => 'Kama / Shiva (Pradosha)',
            'benefit' => 'Marital happiness and fulfillment of worldly desires',
            'name' => 'Pradosh Vrat (Twilight Worship)',
        ],
        14 => [
            'vrata' => 'Chaturdashi Vrata',
            'deity' => 'Shiva / Rudra',
            'benefit' => 'Dissolution of negative habits and deep transformation',
            'krishna_name' => 'Masik Shivaratri',
        ],
        15 => [
            'vrata' => 'Purnima / Amavasya Vrata',
            'deity' => 'Chandra (Purnima) / Pitrus (Amavasya)',
            'purnima_benefit' => 'Emotional balance and Satyanarayan blessings',
            'amavasya_benefit' => 'Ancestral peace and karmic balancing',
            'gujarati_special' => 'Kokila Vrat (Ashad Purnima)',
        ],
    ];

    /**
     * Complete list of Hindu festivals with calculation rules
     * Based on traditional texts and regional variations.
     */
    public const FESTIVALS = [
        'Mesha Sankranti (Baisakhi / Puthandu)' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 0,
            'description' => 'Solar New Year',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Vrishabha Sankranti' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 1,
            'description' => 'Sun enters Vrishabha',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Mithuna Sankranti' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 2,
            'description' => 'Sun enters Mithuna',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Karka Sankranti' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 3,
            'description' => 'Sun enters Karka',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Simha Sankranti' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 4,
            'description' => 'Sun enters Simha',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Kanya Sankranti (Vishwakarma Puja)' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 5,
            'description' => 'Worship of divine architect Vishwakarma',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
            'deity' => 'Vishwakarma',
        ],
        'Tula Sankranti' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 6,
            'description' => 'Sun enters Tula',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Vrischika Sankranti' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 7,
            'description' => 'Sun enters Vrischika',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Dhanu Sankranti' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 8,
            'description' => 'Sun enters Dhanu',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Makara Sankranti (Pongal)' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 9,
            'description' => 'Harvest festival, Sun enters Makara',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
            'deity' => 'Surya',
        ],
        'Kumbha Sankranti' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 10,
            'description' => 'Sun enters Kumbha',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Meena Sankranti' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 11,
            'description' => 'Sun enters Meena',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Vasi Uttarayan' =>
        [
            'type' => 'fixed_date',
            'month' => 1,
            'day' => 15,
            'description' => 'Second day of the kite festival in Gujarat',
            'regions' => ['Gujarat'],
        ],
        'Lohri' =>
        [
            'type' => 'fixed_date',
            'month' => 1,
            'day' => 13,
            'description' => 'Punjabi harvest festival; bonfire celebration marking end of winter solstice',
            'deity' => 'Agni/Surya',
            'regions' => ['Punjab', 'Haryana', 'Delhi', 'North India'],
        ],

        'Cheti Chand' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Sindhi Hindu New Year; birth of Jhulelal (Ishta-Devta)',
            'deity' => 'Jhulelal/Varuna',
            'regions' => ['Sindhi', 'Gujarat', 'Rajasthan'],
        ],
        'Vishu' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 0,
            'description' => 'Kerala Hindu New Year; first day of Medam month; Vishukkani arrangement',
            'deity' => 'Krishna/Vishnu',
            'regions' => ['Kerala', 'Tamil Nadu', 'Karnataka'],
        ],

        'Shastriji Maharaj Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Birth anniversary of Shastriji Maharaj, founder of BAPS; coincides with Vasant Panchami',
            'deity' => 'Swaminarayan',
        ],

        'Pramukh Varni Din' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Day when Pramukh Swami Maharaj was appointed administrative president of BAPS (1950)',
            'deity' => 'Swaminarayan',
        ],

        'Ashadhi Bij' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Kutchi New Year; marks beginning of monsoon and kharif crop season; traditional weather prediction',
            'deity' => 'Varuna/Indra',
            'regions' => ['Kutch', 'Gujarat'],
        ],

        'Ravechi Mata Fair' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Ancient fair at Ravechi Mata Temple (Rapar, Kutch); thousands of Rabari and Ahir devotees gather',
            'deity' => 'Ravechi Mata',
            'regions' => ['Kutch', 'Gujarat'],
        ],

        'Tarnetar Fair' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Saurashtra\'s famous matchmaking fair at Trinetreshwar Mahadev Temple; Bhadarva Sud 4-6',
            'deity' => 'Shiva',
            'regions' => ['Saurashtra', 'Gujarat'],
        ],
        'Tarnetar Fair Day 2' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Second day of Tarnetar Fair (Bhadarva Sud 5)',
            'deity' => 'Shiva',
            'regions' => ['Saurashtra', 'Gujarat'],
        ],
        'Tarnetar Fair Day 3' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Third day of Tarnetar Fair (Bhadarva Sud 6)',
            'deity' => 'Shiva',
            'regions' => ['Saurashtra', 'Gujarat'],
        ],

        'Mota Yaksh Fair (Jakh Bahotera)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Largest fair in Kutch at Kakadbhit; honors 72 Yakshas who protected locals; Bhadarva Sud 12-14',
            'deity' => '72 Yakshas',
            'regions' => ['Kutch', 'Gujarat'],
        ],
        'Mota Yaksh Fair Day 2' =>
        [
            'type' => 'day_after',
            'parent_festival' => 'Mota Yaksh Fair (Jakh Bahotera)',
            'days_after' => 1,
            'description' => 'Second day of Mota Yaksh Fair at Kakadbhit (Bhadarva Sud 13)',
            'deity' => '72 Yakshas',
            'regions' => ['Kutch', 'Gujarat'],
        ],
        'Mota Yaksh Fair Day 3' =>
        [
            'type' => 'day_after',
            'parent_festival' => 'Mota Yaksh Fair (Jakh Bahotera)',
            'days_after' => 2,
            'description' => 'Third day of Mota Yaksh Fair at Kakadbhit (Bhadarva Sud 14)',
            'deity' => '72 Yakshas',
            'regions' => ['Kutch', 'Gujarat'],
        ],

        'Dada Mekan Fair (Dhrang Mela)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 14,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Fair at Dhrang village honoring saint Mekan Dada who saved lost travelers in Rann of Kutch',
            'deity' => 'Mekan Dada',
            'regions' => ['Kutch', 'Gujarat'],
        ],

        'Rang Panchami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Fifth day of Holi celebrations; continuation of color play; especially celebrated in Maharashtra and MP',
            'deity' => 'Krishna',
            'regions' => ['Maharashtra', 'Madhya Pradesh', 'North India'],
        ],

        'Chaitra (Vasant) Navaratri Day 1 (Shailaputri Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Chaitra (Vasant) Navaratri Day 1 (Ghatasthapana): worship of Shailaputri (Daughter of the Mountain). Ghatasthapana is preferred before Madhyahna while Pratipada prevails.',
            'deity' => 'Durga/Shailaputri',
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'ghatasthapana_preference' => 'first_one_third_of_day_then_abhijit',
            'night_prohibited' => true,
            'navratri_type' => 'chaitra',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Chaitra (Vasant) Navaratri Day 2 (Brahmacharini Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Chaitra (Vasant) Navaratri - Worship of Brahmacharini (The Ascetic)',
            'deity' => 'Durga/Brahmacharini',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'chaitra',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Chaitra (Vasant) Navaratri Day 3 (Chandraghanta Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Chaitra (Vasant) Navaratri - Worship of Chandraghanta (Bearer of the Moon-Bell)',
            'deity' => 'Durga/Chandraghanta',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'chaitra',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Chaitra (Vasant) Navaratri Day 4 (Kushmanda Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Chaitra (Vasant) Navaratri - Worship of Kushmanda (Creator of the Cosmic Egg)',
            'deity' => 'Durga/Kushmanda',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'chaitra',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Chaitra (Vasant) Navaratri Day 5 (Skandamata Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Chaitra (Vasant) Navaratri - Worship of Skandamata (Mother of Skanda)',
            'deity' => 'Durga/Skandamata',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'chaitra',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Chaitra (Vasant) Navaratri Day 6 (Katyayani Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Chaitra (Vasant) Navaratri - Worship of Katyayani (The Warrior Goddess)',
            'deity' => 'Durga/Katyayani',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'chaitra',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Chaitra (Vasant) Navaratri Day 7 (Kalaratri Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Chaitra (Vasant) Navaratri - Worship of Kalaratri (The Fierce Night) / Maha Saptami',
            'deity' => 'Durga/Kalaratri',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'chaitra',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Chaitra (Vasant) Navaratri Day 8 (Mahagauri Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Chaitra (Vasant) Navaratri - Worship of Mahagauri (The Great White One) / Maha Ashtami',
            'deity' => 'Durga/Mahagauri',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'chaitra',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Chaitra (Vasant) Navaratri Day 9 (Siddhidatri Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Chaitra (Vasant) Navaratri - Worship of Siddhidatri (Giver of Supernatural Powers) / Maha Navami',
            'deity' => 'Durga/Siddhidatri',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'chaitra',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Ashvina Sharad Navaratri Day 1 (Shailaputri Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Ashvina Sharad Navaratri Day 1 (Ghatasthapana): worship of Shailaputri (Daughter of the Mountain). Ghatasthapana is preferred before Madhyahna while Pratipada prevails.',
            'deity' => 'Durga/Shailaputri',
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'ghatasthapana_preference' => 'first_one_third_of_day_then_abhijit',
            'night_prohibited' => true,
            'navratri_type' => 'sharad',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Ashvina Sharad Navaratri Day 2 (Brahmacharini Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Ashvina Sharad Navaratri - Worship of Brahmacharini (The Ascetic)',
            'deity' => 'Durga/Brahmacharini',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'sharad',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Ashvina Sharad Navaratri Day 3 (Chandraghanta Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Ashvina Sharad Navaratri - Worship of Chandraghanta (Bearer of the Moon-Bell)',
            'deity' => 'Durga/Chandraghanta',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'sharad',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Ashvina Sharad Navaratri Day 4 (Kushmanda Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Ashvina Sharad Navaratri - Worship of Kushmanda (Creator of the Cosmic Egg)',
            'deity' => 'Durga/Kushmanda',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'sharad',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Ashvina Sharad Navaratri Day 5 (Skandamata Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Ashvina Sharad Navaratri - Worship of Skandamata (Mother of Skanda)',
            'deity' => 'Durga/Skandamata',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'sharad',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Ashvina Sharad Navaratri Day 6 (Katyayani Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Ashvina Sharad Navaratri - Worship of Katyayani (The Warrior Goddess)',
            'deity' => 'Durga/Katyayani',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'sharad',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Ashvina Sharad Navaratri Day 7 (Kalaratri Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Ashvina Sharad Navaratri - Worship of Kalaratri (The Fierce Night) / Maha Saptami',
            'deity' => 'Durga/Kalaratri',
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
            'prefer_first_karmakala' => true,
            'navratri_type' => 'sharad',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Durga Ashtami (Mahagauri Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Ashvina Sharad Navaratri - Worship of Mahagauri (The Great White One) / Maha Ashtami',
            'deity' => 'Durga/Mahagauri',
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
            'prefer_first_karmakala' => true,
            'navratri_type' => 'sharad',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Maha Navami (Siddhidatri Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Ashvina Sharad Navaratri - Worship of Siddhidatri (Giver of Supernatural Powers) / Maha Navami',
            'deity' => 'Durga/Siddhidatri',
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
            'prefer_first_karmakala' => true,
            'navratri_type' => 'sharad',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Ashadha Gupt Navaratri Day 1 (Ghatasthapana)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Ashadha Gupta Navaratri Day 1 (Ghatasthapana). Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'ghatasthapana_preference' => 'first_one_third_of_day_then_abhijit',
            'night_prohibited' => true,
            'navratri_type' => 'ashadha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Ashadha Gupt Navaratri Day 2' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Ashadha Gupta Navaratri Day 2. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'ashadha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Ashadha Gupt Navaratri Day 3' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Ashadha Gupta Navaratri Day 3. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'ashadha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Ashadha Gupt Navaratri Day 4' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Ashadha Gupta Navaratri Day 4. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'ashadha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Ashadha Gupt Navaratri Day 5' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Ashadha Gupta Navaratri Day 5. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'ashadha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Ashadha Gupt Navaratri Day 6' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Ashadha Gupta Navaratri Day 6. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'ashadha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Ashadha Gupt Navaratri Day 7' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Ashadha Gupta Navaratri Day 7. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'ashadha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Ashadha Gupt Navaratri Day 8' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Ashadha Gupta Navaratri Day 8. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'ashadha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Ashadha Gupt Navaratri Day 9' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Ashadha Gupta Navaratri Day 9. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'ashadha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Ashadha Gupt Navaratri Parana (Dashami)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Ashadha Gupta Navaratri Parana on Dashami after Navami completion (Nirnaya-Sindhu rule family).',
            'deity' => 'Devi',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'ashadha_gupta',
            'parana_rule' => 'navami_over_and_dashami_prevails',
        ],
        'Magha Gupt Navaratri Day 1 (Ghatasthapana)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Magha Gupta Navaratri Day 1 (Ghatasthapana). Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'ghatasthapana_preference' => 'first_one_third_of_day_then_abhijit',
            'night_prohibited' => true,
            'navratri_type' => 'magha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Magha Gupt Navaratri Day 2' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Magha Gupta Navaratri Day 2. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'magha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Magha Gupt Navaratri Day 3' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Magha Gupta Navaratri Day 3. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'magha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Magha Gupt Navaratri Day 4' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Magha Gupta Navaratri Day 4. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'magha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Magha Gupt Navaratri Day 5' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Magha Gupta Navaratri Day 5. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'magha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Magha Gupt Navaratri Day 6' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Magha Gupta Navaratri Day 6. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'magha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Magha Gupt Navaratri Day 7' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Magha Gupta Navaratri Day 7. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'magha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Magha Gupt Navaratri Day 8' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Magha Gupta Navaratri Day 8. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'magha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Magha Gupt Navaratri Day 9' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Magha Gupta Navaratri Day 9. Worship sequence is lineage-dependent (Navadurga/Mahavidya/custom).',
            'deity' => 'Devi (lineage-specific)',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'magha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Magha Gupt Navaratri Parana (Dashami)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Magha Gupta Navaratri Parana on Dashami after Navami completion (Nirnaya-Sindhu rule family).',
            'deity' => 'Devi',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'magha_gupta',
            'parana_rule' => 'navami_over_and_dashami_prevails',
        ],
        'Sheetala Ashtami (Basoda)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Worship of Goddess Sheetala for protection from diseases',
            'deity' => 'Sheetala Mata',
        ],
        'Papmochani Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Fasting for Papmochani Ekadashi (Liberation from Sins)',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Ugadi / Gudi Padwa' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Lunar New Year / First day of Chaitra',
            'deity' => 'Brahma',
            'karmakala_type' => 'sunrise',
        ],
        'Gangaur' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Worship of Gauri',
            'deity' => 'Parvati',
        ],
        'Yamuna Chhath / Chaiti Chhath' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Descent of Goddess Yamuna / Summer Chhath',
            'deity' => 'Surya/Yamuna',
        ],
        'Rama Navami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Birth anniversary of Lord Rama, seventh avatar of Vishnu',
            'deity' => 'Rama',
            'fasting' => true,
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
        ],
        'Swaminarayan Jayanti (Hari-Nom)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Birth of Bhagwan Swaminarayan; celebrated with assemblies, cultural programs, and devotional singing',
            'deity' => 'Swaminarayan',
            'fasting' => true,
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
        ],
        'Kamada Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Fasting for Kamada Ekadashi (Fulfiller of Desires)',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Hanuman Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Birth celebration of Lord Hanuman',
            'deity' => 'Hanuman',
            'karmakala_type' => 'sunrise',
        ],
        'Varuthini Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Fasting for Varuthini Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Parashurama Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth of Lord Parashurama',
            'deity' => 'Parashurama',
        ],
        'Akshaya Tritiya' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Most auspicious for new beginnings',
            'deity' => 'Vishnu/Lakshmi',
            'karmakala_type' => 'sunrise',
        ],
        'Adi Shankaracharya Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth anniversary of Adi Shankaracharya',
            'deity' => 'Shiva/Shankaracharya',
        ],
        'Ganga Saptami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Rebirth of River Ganga',
            'deity' => 'Ganga',
        ],
        'Sita Navami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth anniversary of Goddess Sita',
            'deity' => 'Sita',
        ],
        'Mohini Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Fasting for Mohini Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Narasimha Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 14,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Appearance day of Lord Narasimha',
            'deity' => 'Narasimha',
            'fasting' => true,
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
        ],
        'Buddha Purnima' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Vaishakha Purnima — Hindu observance of Sugata Buddha (9th avatar of Vishnu, distinct from Gautama Buddha of Buddhism) and Kurma Jayanti (birth of Lord Vishnu\'s tortoise avatar). The Hindu Buddha-avatar is described in Bhagavata Purana 1.3.24 and Agni Purana as appearing in Kikata/Gaya to delude demons from Vedic rituals (Bhagavata view) or to teach compassion and end animal sacrifice (Jayadeva\'s Gita Govinda view). Puri Shankaracharya Swami Nischalananda Saraswati and scholars recognize these as two different persons: the Puranic Sugata Buddha (Brahmin varna, upheld Vedas, ancient timeline) vs. Gautama Buddha (Kshatriya Shakya clan, Lumbini birth ~563 BCE, rejected Vedas). Also coincides with Kurma Jayanti and is the most auspicious day for Satyanarayan Puja. Regional variation: Odisha and Bengal traditions replace Buddha with Balarama as the 9th avatar in Dashavatara.',
            'deity' => 'Sugata Buddha/Kurma/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Narsinh Mehta Janma Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth anniversary of Adi Kavi Narsinh Mehta',
            'deity' => 'Krishna/Narsinh Mehta',
        ],
        'Apara Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Fasting for Apara Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Yogi Maharaj Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Birth of Brahmaswarup Yogi Maharaj',
            'deity' => 'Swaminarayan',
        ],
        'Shani Jayanti / Savitri Amavasya' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Birth of Lord Shani / Savitri Vrat',
            'deity' => 'Shani/Savitri',
        ],
        'Jamai Shashti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Bengali festival dedicated to son-in-laws',
            'deity' => 'Shashti',
        ],
        'Mahesh Navami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Worship of Lord Shiva by Maheshwari community',
            'deity' => 'Shiva',
        ],
        'Ganga Dussehra' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Descent of Mother Ganga to Earth',
            'deity' => 'Ganga',
            'karmakala_type' => 'sunrise',
        ],
        'Nirjala Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Toughest Ekadashi vrat (observed without water] / Gayatri Jayanti',
            'deity' => 'Vishnu/Gayatri',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Vat Purnima' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Vat Savitri observed by Purnimanta calendar',
            'deity' => 'Savitri/Brahma',
            'fasting' => true,
        ],
        'Yogini Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Fasting for Yogini Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Jagannath Rath Yatra' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Chariot festival of Lord Jagannath',
            'deity' => 'Jagannath',
            'karmakala_type' => 'sunrise',
        ],
        'Devshayani Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Chaturmas begins, Lord Vishnu sleeps',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Gauri Vrat (Molakat) Begins' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => '5-day fast by young girls (without salt)',
            'deity' => 'Gauri/Parvati',
        ],
        'Jaya Parvati Vrat Begins' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 13,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => '5-day fast for marital bliss and good husband',
            'deity' => 'Jaya/Parvati',
        ],
        'Guru Purnima' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Honoring spiritual teachers and Vyasa',
            'deity' => 'Vyasa',
            'karmakala_type' => 'sunrise',
        ],
        'Kamika Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Shravana',
            'description' => 'Fasting for Kamika Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Hariyali Teej' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Monsoon festival welcoming the rain',
            'deity' => 'Parvati/Shiva',
        ],
        'Nag Panchami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Worship of serpent deities',
            'deity' => 'Nagas',
        ],
        'Kalki Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Birth of Kalki Avatar',
            'deity' => 'Kalki',
        ],
        'Tulsidas Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Birth anniversary of Goswami Tulsidas',
            'deity' => 'Rama/Tulsidas',
        ],
        'Shravana Putrada Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Fasting to be blessed with a son',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Avani Avittam (Yajur Upakarma)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Yajur Upakarma (Avani Avittam): sacred thread renewal and Vedic recommitment',
            'deity' => 'Hayagriva',
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
        ],
        'Raksha Bandhan' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Brother-sister bond festival; tie Rakhi during auspicious Purnima window',
            'deity' => 'Vishnu/Indra',
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
        ],
        'Pola / Kushotpatini Amavasya' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Thanksgiving festival for farmers and bulls',
            'deity' => 'Shiva',
        ],
        'Aja Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Fasting for Aja Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Goga Pancham / Goga Panchami (Nag Panchami - Gujarat)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 5,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Gujarati Nag Panchami honoring Goga Ji / Goga Maharaj and serpent deities',
            'deity' => 'Goga Ji / Goga Bapa',
            'regions' => ['Gujarat'],
        ],
        'Shravana Somvar (Monday Fasting)' =>
        [
            'type' => 'weekday_in_month',
            'weekday' => 1,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Auspicious Monday of Shravana month dedicated to Lord Shiva',
            'deity' => 'Shiva',
            'fasting' => true,
        ],
        'Kajari Teej' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 3,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Kajari Teej festival',
            'deity' => 'Parvati/Shiva',
        ],
        'Bol Choth' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Worship of cows and calves (Bahula Chaturthi)',
            'deity' => 'Krishna/Cows',
        ],
        'Randhan Chhath / Balarama Jayanti (Hala Shashthi)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 6,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Gujarati Randhan Chhath and the wider Hala Shashthi / Balarama Jayanti observance on Krishna Shashthi',
            'deity' => 'Balarama',
        ],
        'Sheetala Satam / Sheetala Saptami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 7,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Sheetala Mata observance, known in Gujarat as Sheetala Satam, marked by cold-food offerings and prayers for health',
            'deity' => 'Sheetala Mata',
            'fasting' => true,
        ],
        'Krishna Janmashtami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Birth celebration of Lord Krishna',
            'deity' => 'Krishna',
            'fasting' => true,
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
            'vriddhi_preference' => 'last',
        ],
        'Hartalika Teej / Kevada Trij' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Hartalika Teej, known in Gujarat as Kevada Trij, observed by women in honor of Parvati and Shiva',
            'deity' => 'Parvati/Shiva',
        ],
        'Varaha Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Appearance day of Lord Varaha (Boar Avatar)',
            'deity' => 'Vishnu/Varaha',
        ],
        'Ganesh Chaturthi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Birth anniversary of Lord Ganesha',
            'deity' => 'Ganesha',
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
        ],
        'Rishi Panchami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Worship of the Saptarishis',
            'deity' => 'Saptarishis',
            'karmakala_type' => 'madhyahna',
        ],
        'Radha Ashtami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Birth anniversary of Radha Rani',
            'deity' => 'Radha',
            'karmakala_type' => 'madhyahna',
        ],
        'Parsva Ekadashi / Jal Jhilani Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Lord Vishnu changes sides / Swaminarayan Jal Jhilani Utsav',
            'deity' => 'Vishnu/Swaminarayan',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Mahant Swami Maharaj Janma Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 9,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Physical birth anniversary of Mahant Swami Maharaj (13 September 1933, Bhadarva Vad 9)',
            'deity' => 'Swaminarayan',
        ],
        'Mahant Swami Maharaj Parshadi Diksha Din (Official Jayanti)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 1,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Official BAPS celebration of Mahant Swami Maharaj Jayanti on Parshadi Diksha Din (2 February 1957, Maha Vad 1)',
            'deity' => 'Swaminarayan',
        ],
        'Vamana Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Birth of Vamana Avatar',
            'deity' => 'Vamana',
        ],
        'Anant Chaturdashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 14,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Worship of Lord Ananta; Ganesh Visarjan',
            'deity' => 'Vishnu/Ganesha',
            'karmakala_type' => 'sunrise',
        ],
        'Pitru Paksha Begins' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Beginning of the fortnight of ancestors',
            'deity' => 'Pitrus',
        ],
        'Indira Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Fasting for Indira Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Mahalaya Amavasya' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Final day of Pitru Paksha (Sarvapitri Amavasya)',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Lalita Panchami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Worship of Goddess Lalita Tripurasundari during Navaratri',
            'deity' => 'Lalita',
        ],
        'Dussehra / Vijayadashami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Victory of Lord Rama over Ravana / End of Sharad Navaratri',
            'deity' => 'Rama/Durga',
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
            'target_window' => 'aparahna',
            'fallback_support' => 'vijaya_muhurta',
            'navratri_type' => 'sharad',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Papankusha Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Fasting for Papankusha Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Sharad Purnima / Manekthari Punam / Gunatitanand Swami Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Sharad Purnima, known in Gujarat as Manekthari Punam, and the birth anniversary of Gunatitanand Swami',
            'deity' => 'Lakshmi/Krishna/Swaminarayan',
            'karmakala_type' => 'nishitha',
        ],
        'Valmiki Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Birth anniversary of Sage Valmiki',
            'deity' => 'Valmiki',
        ],
        'Rama Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Fasting for Rama Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Karva Chauth' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Married women fast for husband long life',
            'deity' => 'Parvati/Shiva',
            'fasting' => true,
        ],
        'Ahoi Ashtami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Mothers fast for wellbeing of children',
            'deity' => 'Ahoi Mata',
            'fasting' => true,
            'karmakala_type' => 'pradosha',
        ],
        'Vagh Baras (Govatsa Dwadashi)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Worship of cows and calves',
            'deity' => 'Krishna/Cows',
            'karmakala_type' => 'pradosha',
        ],
        'Dhanteras' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 13,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Worship of Lakshmi and Dhanvantari',
            'deity' => 'Lakshmi/Dhanvantari',
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
        ],
        'Kali Chaudas (Naraka Chaturdashi)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 14,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Worship of Goddess Kali and victory over Narakasura',
            'deity' => 'Kali/Hanuman/Krishna',
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
        ],
        'Kali Puja / Diwali' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Festival of lights / Worship of Goddess Kali',
            'deity' => 'Lakshmi/Kali/Ganesha',
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'vriddhi_preference' => 'first',
        ],
        'Govardhan Puja / Bestu Varas / Annakut' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Annakut (Swaminarayan) / Gujarati New Year',
            'deity' => 'Krishna',
            'karmakala_type' => 'sunrise',
        ],
        'Bhai Dooj' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Brother-sister relationship celebration',
            'deity' => 'Yama/Yamuna',
            'karmakala_type' => 'aparahna',
        ],
        'Labh Pancham' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Auspicious day for businesses in Gujarat',
            'deity' => 'Lakshmi/Ganesha',
        ],
        'Skanda Shashti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Six-day festival dedicated to Lord Murugan, celebrating his victory over demon Surapadman',
            'deity' => 'Murugan/Skanda',
            'fasting' => true,
            'regions' => ['Tamil Nadu', 'Kerala', 'Karnataka', 'Andhra Pradesh', 'Telangana'],
        ],
        'Chhath Puja (Sandhya Arghya)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Major day (3rd of 4) of Chhath Puja - evening arghya to Surya; festival runs Kartika Shukla 4-7',
            'deity' => 'Surya/Chhathi Maiya',
            'fasting' => true,
            'regions' => ['Bihar', 'Jharkhand', 'Eastern UP', 'Nepal'],
            'karmakala_type' => 'aparahna',
        ],
        'Jalaram Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Birth anniversary of Saint Jalaram Bapa of Virpur',
            'deity' => 'Rama/Jalaram Bapa',
        ],
        'Gopashtami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Worship and celebration of cows',
            'deity' => 'Kamadhenu/Krishna',
        ],
        'Jagaddhatri Puja' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Worship of Goddess Jagaddhatri in Bengal',
            'deity' => 'Jagaddhatri',
        ],
        'Pramukh Swami Maharaj Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Birth anniversary of Pramukh Swami Maharaj (7 December 1921, Magshar Sud 8, VS 1978)',
            'deity' => 'Swaminarayan',
        ],
        'Devutthana (Prabodhini) Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Prabodhini Ekadashi / End of Chaturmas',
            'deity' => 'Vishnu/Swaminarayan',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Tulsi Vivah' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Ceremonial marriage of Tulsi to Vishnu',
            'deity' => 'Tulsi/Vishnu',
        ],
        'Vaikuntha Chaturdashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 14,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Sacred day for both Vishnu and Shiva worshippers',
            'deity' => 'Vishnu/Shiva',
            'karmakala_type' => 'nishitha',
        ],
        'Dev Diwali (Tripurari Purnima)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Festival of Varanasi celebrating Shiva\'s victory over Tripurasura; ghats illuminated with lamps',
            'deity' => 'Shiva',
            'regions' => ['Varanasi', 'Uttar Pradesh'],
            'karmakala_type' => 'pradosha',
        ],
        'Karthigai Deepam' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Tamil festival of lights; Maha Deepam lit at Tiruvannamalai',
            'deity' => 'Shiva/Karthikeya',
            'regions' => ['Tamil Nadu', 'Kerala', 'Karnataka', 'Andhra Pradesh', 'Telangana'],
            'karmakala_type' => 'pradosha',
        ],
        'Utpanna Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Fasting for Utpanna Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Vachanamrut Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Commemoration of the Vachanamrut',
            'deity' => 'Swaminarayan',
        ],
        'Kalabhairav Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Birth of Lord Kalabhairav (Shiva]',
            'deity' => 'Kalabhairav',
        ],
        'Vivah Panchami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Wedding anniversary of Rama and Sita',
            'deity' => 'Rama/Sita',
        ],
        'Geeta Jayanti / Mokshada Ekadashi / Vaikunta Ekadasi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Celebration of Bhagavad Gita / Gateway to Heaven',
            'deity' => 'Krishna/Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Dattatreya Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Birth of Dattatreya (Annapurna Jayanti]',
            'deity' => 'Dattatreya/Annapurna',
            'karmakala_type' => 'pradosha',
        ],
        'Saphala Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Pausha',
            'description' => 'Fasting for Saphala Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Pausha Putrada Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Pausha',
            'description' => 'Fasting for Pausha Putrada Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Shakambhari Purnima' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Pausha',
            'description' => 'Culmination of Shakambhari Navaratri; worship of Goddess Shakambhari who nourished the world with vegetables and fruits',
            'deity' => 'Shakambhari/Devi',
            'regions' => ['Rajasthan', 'Uttar Pradesh', 'Madhya Pradesh', 'Bihar'],
        ],
        'Thai Poosam' =>
        [
            'nakshatra_only' => true,
            'nakshatra' => 'Pushya',
            'requires_purnima' => true,
            'allowed_months_amanta' => ['Pausha', 'Magha'],
            'description' => 'Tamil festival dedicated to Lord Murugan; observed when Pushya nakshatra coincides with Purnima in Tamil month Thai (Jan-Feb)',
            'deity' => 'Murugan',
            'regions' => ['Tamil Nadu', 'Kerala', 'Karnataka', 'Andhra Pradesh', 'Telangana', 'Sri Lanka', 'Malaysia', 'Singapore'],
        ],
        'Onam (Thiruvonam)' =>
        [
            'nakshatra_only' => true,
            'nakshatra' => 'Shravana',
            'allowed_months_amanta' => ['Shravana', 'Bhadrapada'],
            'description' => 'Kerala harvest festival; Thiruvonam nakshatra (Shravana) in Malayalam month Chingam (Aug-Sep); marks King Mahabali\'s annual visit',
            'deity' => 'Vishnu/Mahabali',
            'regions' => ['Kerala', 'Tamil Nadu', 'Karnataka'],
            'karmakala_type' => 'madhyahna',
        ],
        'Gunatitanand Swami Diksha Day' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Pausha',
            'description' => 'Diksha day of Gunatitanand Swami',
            'deity' => 'Swaminarayan',
        ],
        'Shattila Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Magha',
            'description' => 'Fasting for Shattila Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Mauni Amavasya' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Magha',
            'description' => 'Day of rigorous silence and holy bath',
            'deity' => 'Vishnu',
            'fasting' => true,
        ],
        'Vasant Panchami / Shikshapatri Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Worship of Goddess Saraswati / Shikshapatri presentation',
            'deity' => 'Saraswati/Swaminarayan',
            'karmakala_type' => 'sunrise',
        ],
        'Ratha Saptami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Surya Jayanti',
            'deity' => 'Surya',
        ],
        'Bhishma Ashtami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Anniversary of Bhishma Pitamah departure',
            'deity' => 'Bhishma',
        ],
        'Jaya Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Fasting for Jaya Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Maghi Purnima / Masi Magam' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Kumbh Mela bathing day / Tamil holy bathing day',
            'deity' => 'Ganga/Shiva',
        ],
        'Pushpadolotsav (Dhuleti)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 1,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Festival of colors (Dhuleti/Pushpadolotsav]',
            'deity' => 'Krishna/Swaminarayan',
            'karmakala_type' => 'sunrise',
        ],
        'Vijaya Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Fasting for Vijaya Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Maha Shivaratri' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 14,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Great night of Lord Shiva',
            'deity' => 'Shiva',
            'fasting' => true,
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
            'vriddhi_preference' => 'last',
        ],
        'Phulera Dooj' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Festival of flowers',
            'deity' => 'Krishna',
        ],
        'Amalaki Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Fasting for Amalaki Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Holika Dahan' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Bonfire ceremony, victory of good over evil',
            'deity' => 'Vishnu/Hiranyakashipu',
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'vriddhi_preference' => 'first',
        ],
        'Bhagatji Maharaj Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Birth of Bhagatji Maharaj (Swaminarayan Sect)',
            'deity' => 'Swaminarayan',
        ],
        'Holi' =>
        [
            'type' => 'day_after',
            'parent_festival' => 'Holika Dahan',
            'days_after' => 1,
            'description' => 'Festival of colors',
            'deity' => 'Krishna',
        ],
        'Varalakshmi Vratam' =>
        [
            'type' => 'weekday_tithi',
            'paksha' => 'Shukla',
            'tithi' =>
            [
                0 => 12,
                1 => 13,
                2 => 14,
                3 => 15,
            ],
            'weekday' => 5,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Worship of Goddess Lakshmi for prosperity',
            'deity' => 'Lakshmi',
            'fasting' => true,
        ],
        'Padmini Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'description' => 'Special Ekadashi occurring ONLY during Adhik Maas',
            'deity' => 'Vishnu',
            'fasting' => true,
            'adhika_only' => true,
        ],
        'Parama Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'description' => 'Special Ekadashi occurring ONLY during Adhik Maas Krishna Paksha',
            'deity' => 'Vishnu',
            'fasting' => true,
            'adhika_only' => true,
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
    public function __construct(
        private readonly FestivalRuleEngine $ruleEngine
    ) {
    }

    /**
     * Get festivals for a specific date using actual panchang data
     * This is the PRIMARY method - uses real tithi from PanchangService.
     */
    public function resolveFestivalsForDate(
        CarbonImmutable $date,
        array $todayDetails,
        array $tomorrowDetails,
        ?array $yesterdayDetails = null
    ): array
    {
        $festivals = [];
        $festivalMeta = [];
        $addedFestivalKeys = [];
        $tithi = $todayDetails['Tithi'] ?? null;

        if (!$tithi) {
            return [];
        }

        $tithiNum = (int) ($tithi['index'] ?? 0);
        $paksha = $tithi['paksha'] ?? 'Shukla';

        foreach (self::FESTIVALS as $name => $rules) {
            $calendar = $todayDetails['Hindu_Calendar'] ?? [];
            $isAdhika = (bool) ($calendar['Is_Adhika'] ?? false);
            $isKshaya = (bool) ($calendar['Is_Kshaya'] ?? false);

            // Adhik Maas filtering logic
            $adhikaAllowed = (bool) ($rules['allow_adhika'] ?? false);
            $adhikaOnly = (bool) ($rules['adhika_only'] ?? false);

            // During Adhika months, only skip festivals that explicitly require
            // a non-Adhika month. Most festivals should still resolve if their
            // month name matches (ignoring the Adhika suffix).
            if ($isAdhika && !$adhikaAllowed && !$adhikaOnly) {
                // Check if this festival has a month requirement that matches
                // current calendar mode when stripping Adhika suffixes.
                $calendarType = strtolower((string) ($calendar['Calendar_Type'] ?? config('panchang.defaults.calendar_type', 'amanta')));
                $amantaCleanNorm = $this->normalizeMonthName(str_replace(' (Adhika)', '', (string) ($calendar['Month_Amanta_En'] ?? '')));
                $purnimantaCleanNorm = $this->normalizeMonthName(str_replace(' (Adhika)', '', (string) ($calendar['Month_Purnimanta_En'] ?? '')));
                $monthAmantaRule = $this->normalizeMonthName((string) ($rules['month_amanta'] ?? ''));
                $monthPurnimantaRule = $this->normalizeMonthName((string) ($rules['month_purnimanta'] ?? ''));
                $hasMonthRule = ($monthAmantaRule !== '' || $monthPurnimantaRule !== '');
                if ($hasMonthRule) {
                    $monthMatches = false;
                    if ($calendarType === 'purnimanta') {
                        if ($monthPurnimantaRule !== '') {
                            $monthMatches = ($monthPurnimantaRule === $purnimantaCleanNorm);
                        } elseif ($monthAmantaRule !== '') {
                            $monthMatches = ($monthAmantaRule === $amantaCleanNorm);
                        }
                    } else {
                        if ($monthAmantaRule !== '') {
                            $monthMatches = ($monthAmantaRule === $amantaCleanNorm);
                        } elseif ($monthPurnimantaRule !== '') {
                            $monthMatches = ($monthPurnimantaRule === $purnimantaCleanNorm);
                        }
                    }
                    if ($monthMatches) {
                        // Month matches during Adhika - allow this festival
                        // (it belongs to this month, just happens to fall during Adhika)
                    } else {
                        continue; // Month doesn't match, skip
                    }
                }
                // If no month requirement, allow the festival during Adhika
            } elseif ($adhikaOnly && !$isAdhika) {
                continue;
                // Skip Adhik-only festivals during normal months
            }

            $isClassical = self::usesClassicalResolver($rules);
            $isNakshatra = (bool) ($rules['nakshatra_only'] ?? false);

            // Check Hindu month match for tithi-based festivals (respect configured calendar type)
            if ((isset($rules['month_amanta']) || isset($rules['month_purnimanta']))
                && !$this->monthRuleMatches($rules, (array) ($todayDetails['Hindu_Calendar'] ?? []))) {
                continue; // Skip this festival for this month
            }

            if ($isClassical) {
                $resolved = $this->ruleEngine->resolveMajorFestival($name, $rules, $date, $todayDetails, $tomorrowDetails);
                if ($resolved !== null && $resolved['observance_date'] === $date->toDateString() && !isset($addedFestivalKeys[$name])) {
                    $regions = $rules['regions'] ?? ['Pan-India'];
                    $festivals[] = [
                        'name' => Localization::translate('Festival', $name),
                        'description' => $rules['description'],
                        'deity' => Localization::translate('Deity', $rules['deity'] ?? ''),
                        'fasting' => $rules['fasting'] ?? false,
                        'regions' => array_map(fn ($r) => Localization::translate('Region', $r), $regions),
                        'observance_note' => $resolved['observance_note'] ?? null,
                        'rules_applied' => $resolved['decision'] ?? [],
                    ];
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                } elseif ($yesterdayDetails !== null && !isset($addedFestivalKeys[$name])) {
                    // Back-fill festivals whose resolved observance date is today but
                    // whose tithi decision was derived from yesterday->today.
                    $resolvedYesterday = $this->ruleEngine->resolveMajorFestival($name, $rules, $date->subDay(), $yesterdayDetails, $todayDetails);
                    if ($resolvedYesterday !== null && $resolvedYesterday['observance_date'] === $date->toDateString()) {
                        $regions = $rules['regions'] ?? ['Pan-India'];
                        $festivals[] = [
                            'name' => Localization::translate('Festival', $name),
                            'description' => $rules['description'],
                            'deity' => Localization::translate('Deity', $rules['deity'] ?? ''),
                            'fasting' => $rules['fasting'] ?? false,
                            'regions' => array_map(fn ($r) => Localization::translate('Region', $r), $regions),
                            'observance_note' => $resolvedYesterday['observance_note'] ?? null,
                            'rules_applied' => $resolvedYesterday['decision'] ?? [],
                        ];
                        $festivalMeta[] = [
                            'raw_name' => $name,
                            'adhika_only' => $adhikaOnly,
                            'is_ekadashi' => str_contains($name, 'Ekadashi'),
                        ];
                        $addedFestivalKeys[$name] = true;
                    }
                }
            } elseif ($isNakshatra) {
                // Handle nakshatra-based festivals
                $resolved = $this->ruleEngine->resolveNakshatraBasedFestival($name, $rules, $date, $todayDetails, $tomorrowDetails);
                if ($resolved !== null && $resolved['observance_date'] === $date->toDateString() && !isset($addedFestivalKeys[$name])) {
                    $regions = $rules['regions'] ?? ['Pan-India'];
                    $festivals[] = [
                        'name' => Localization::translate('Festival', $name),
                        'description' => $rules['description'],
                        'deity' => Localization::translate('Deity', $rules['deity'] ?? ''),
                        'fasting' => $rules['fasting'] ?? false,
                        'regions' => array_map(fn ($r) => Localization::translate('Region', $r), $regions),
                        'observance_note' => $resolved['observance_note'] ?? null,
                        'rules_applied' => $resolved['decision'] ?? [],
                    ];
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                } elseif ($yesterdayDetails !== null && !isset($addedFestivalKeys[$name])) {
                    $resolvedYesterday = $this->ruleEngine->resolveNakshatraBasedFestival($name, $rules, $date->subDay(), $yesterdayDetails, $todayDetails);
                    if ($resolvedYesterday !== null && $resolvedYesterday['observance_date'] === $date->toDateString()) {
                        $regions = $rules['regions'] ?? ['Pan-India'];
                        $festivals[] = [
                            'name' => Localization::translate('Festival', $name),
                            'description' => $rules['description'],
                            'deity' => Localization::translate('Deity', $rules['deity'] ?? ''),
                            'fasting' => $rules['fasting'] ?? false,
                            'regions' => array_map(fn ($r) => Localization::translate('Region', $r), $regions),
                            'observance_note' => $resolvedYesterday['observance_note'] ?? null,
                            'rules_applied' => $resolvedYesterday['decision'] ?? [],
                        ];
                        $festivalMeta[] = [
                            'raw_name' => $name,
                            'adhika_only' => $adhikaOnly,
                            'is_ekadashi' => str_contains($name, 'Ekadashi'),
                        ];
                        $addedFestivalKeys[$name] = true;
                    }
                }
            } elseif ($this->matchesFestivalRules($date, $rules, $tithiNum, $paksha, $todayDetails)) {
                $regions = $rules['regions'] ?? ['Pan-India'];
                $festivals[] = [
                    'name' => Localization::translate('Festival', $name),
                    'description' => $rules['description'],
                    'deity' => Localization::translate('Deity', $rules['deity'] ?? ''),
                    'fasting' => $rules['fasting'] ?? false,
                    'regions' => array_map(fn ($r) => Localization::translate('Region', $r), $regions),
                ];
                $festivalMeta[] = [
                    'raw_name' => $name,
                    'adhika_only' => $adhikaOnly,
                    'is_ekadashi' => str_contains($name, 'Ekadashi'),
                ];
                $addedFestivalKeys[$name] = true;
            }
        }

        // During Adhika Maas, if special Adhika Ekadashi(s) are present on a date,
        // suppress regular Ekadashi labels for that same date to avoid double tagging.
        if ((bool) (($todayDetails['Hindu_Calendar']['Is_Adhika'] ?? false)) && $festivals !== []) {
            $hasAdhikaOnlyEkadashi = false;
            foreach ($festivalMeta as $meta) {
                if (($meta['is_ekadashi'] ?? false) && ($meta['adhika_only'] ?? false)) {
                    $hasAdhikaOnlyEkadashi = true;
                    break;
                }
            }

            if ($hasAdhikaOnlyEkadashi) {
                $filteredFestivals = [];
                $filteredMeta = [];
                foreach ($festivals as $idx => $festival) {
                    $meta = $festivalMeta[$idx] ?? ['is_ekadashi' => false, 'adhika_only' => false];
                    $isEkadashi = (bool) ($meta['is_ekadashi'] ?? false);
                    $isAdhikaOnly = (bool) ($meta['adhika_only'] ?? false);
                    if (!$isEkadashi || $isAdhikaOnly) {
                        $filteredFestivals[] = $festival;
                        $filteredMeta[] = $meta;
                    }
                }
                $festivals = $filteredFestivals;
                $festivalMeta = $filteredMeta;
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
            $benefit = $rule['benefit'] ?? '';
            if ($idx === 15) {
                $benefit = $paksha === 'Shukla' ? $rule['purnima_benefit'] : $rule['amavasya_benefit'];
            }
            $out[] = [
                'name' => Localization::translate('Vrata', $rule['vrata']),
                'deity' => $rule['deity'],
                'benefit' => $benefit,
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
        if (isset($rules['weekday']) && $date->dayOfWeek !== $rules['weekday']) {
            return false;
        }

        // Check Hindu month match for tithi-based rules
        if ((isset($rules['month_amanta']) || isset($rules['month_purnimanta']))
            && !$this->monthRuleMatches($rules, (array) ($panchangDetails['Hindu_Calendar'] ?? []))) {
            return false;
        }

        // Check fixed Gregorian dates
        if (in_array((string) ($rules['type'] ?? ''), ['fixed_date', 'solar'], true) && isset($rules['month'], $rules['day']) && ((int) $rules['month'] !== $date->month || (int) $rules['day'] !== $date->day)) {
            return false;
        }

        // Check solar sankranti (transit into specific rashi)
        if (($rules['type'] ?? '') === 'solar_sankranti' && isset($rules['rashi'])) {
            $sankrantiRashi = $panchangDetails['Resolution_Context']['sankranti_rashi'] ?? null;
            if ($sankrantiRashi === null || (int) $rules['rashi'] !== $sankrantiRashi) {
                return false;
            }
        }

        // Check nakshatra match (if specified)
        if (isset($rules['nakshatra'], $panchangDetails['Nakshatra']['name']) && $panchangDetails['Nakshatra']['name'] !== $rules['nakshatra']) {
            return false;
        }

        // Check weekday_in_month (e.g., Shravan Somvar)
        if (($rules['type'] ?? '') === 'weekday_in_month' && isset($rules['weekday'])) {
            $calendar = (array) ($panchangDetails['Hindu_Calendar'] ?? []);
            if (!$this->monthRuleMatches($rules, $calendar)) {
                return false;
            }
        }

        return true;
    }

    /** Normalize Sanskrit month names for robust matching across ASCII and diacritic forms. */
    private function normalizeMonthName(string $month): string
    {
        $month = trim($month);
        if ($month === '') {
            return '';
        }

        // Strip parenthetical suffixes like "(Adhika)", "(Kshaya)"
        $month = preg_replace('/\s*\(.*?\)\s*/', '', $month) ?? $month;

        $transliterated = strtr($month, [
            'Ā' => 'A', 'ā' => 'a',
            'Ī' => 'I', 'ī' => 'i',
            'Ū' => 'U', 'ū' => 'u',
            'Ṛ' => 'Ri', 'ṛ' => 'ri',
            'Ṝ' => 'Ri', 'ṝ' => 'ri',
            'Ḷ' => 'Li', 'ḷ' => 'li',
            'Ḍ' => 'D', 'ḍ' => 'd',
            'Ṭ' => 'T', 'ṭ' => 't',
            'Ṅ' => 'N', 'ṅ' => 'n',
            'Ñ' => 'N', 'ñ' => 'n',
            'Ṇ' => 'N', 'ṇ' => 'n',
            'Ś' => 'Sh', 'ś' => 'sh',
            'Ṣ' => 'Sh', 'ṣ' => 'sh',
            'Ḥ' => 'H', 'ḥ' => 'h',
            'ṁ' => 'm', 'ṃ' => 'm',
        ]);

        $asciiOnly = preg_replace('/[^A-Za-z]/', '', $transliterated) ?? '';

        return strtolower($asciiOnly);
    }

    /** Match month rule against active calendar type (amanta/purnimanta). */
    private function monthRuleMatches(array $rules, array $calendar): bool
    {
        $amanta = $this->normalizeMonthName((string) ($calendar['Month_Amanta_En'] ?? $calendar['Month_Amanta'] ?? ''));
        $purnimanta = $this->normalizeMonthName((string) ($calendar['Month_Purnimanta_En'] ?? $calendar['Month_Purnimanta'] ?? ''));
        $ruleAmanta = isset($rules['month_amanta']) ? $this->normalizeMonthName((string) $rules['month_amanta']) : '';
        $rulePurnimanta = isset($rules['month_purnimanta']) ? $this->normalizeMonthName((string) $rules['month_purnimanta']) : '';
        $calendarType = strtolower((string) ($calendar['Calendar_Type'] ?? config('panchang.defaults.calendar_type', 'amanta')));

        if ($calendarType === 'purnimanta') {
            if ($rulePurnimanta !== '') {
                return $rulePurnimanta === $purnimanta;
            }
            if ($ruleAmanta !== '') {
                return $ruleAmanta === $amanta;
            }
            return true;
        }

        // Default: amanta
        if ($ruleAmanta !== '') {
            return $ruleAmanta === $amanta;
        }
        if ($rulePurnimanta !== '') {
            return $rulePurnimanta === $purnimanta;
        }

        return true;
    }
}
