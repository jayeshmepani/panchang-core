<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\Enums\Masa;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Paksha;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
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
        'Mesha Sankranti' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 0,
            'aliases' => ['Baisakhi', 'Puthandu'],
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
        'Pohela Boishakh' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 0,
            'aliases' => ['Pahela Baishakh'],
            'description' => 'Bengali Solar New Year observed in Bengal tradition',
            'deity' => 'Surya',
            'regions' => ['West Bengal', 'Bangladesh', 'Bengali'],
        ],
        'Pahela Baishakh' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 0,
            'description' => 'Alternate naming tradition of Bengali Solar New Year',
            'deity' => 'Surya',
            'regions' => ['West Bengal', 'Bangladesh', 'Bengali'],
        ],
        'Pana Sankranti' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 0,
            'aliases' => ['Maha Vishuba Sankranti'],
            'description' => 'Odia Solar New Year celebrated with pana offerings',
            'deity' => 'Surya',
            'regions' => ['Odisha'],
        ],
        'Maha Vishuba Sankranti' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 0,
            'description' => 'Traditional Odia naming for Solar New Year Sankranti',
            'deity' => 'Surya',
            'regions' => ['Odisha'],
        ],
        'Mesha Vishu' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 0,
            'description' => 'Mesha transition observance used in regional calendars',
            'deity' => 'Surya',
            'regions' => ['Pan-India'],
        ],
        'Jur Sital' =>
        [
            'type' => 'fixed_date',
            'month' => 4,
            'day' => 15,
            'description' => 'Maithili New Year observance with cooling and water rituals',
            'deity' => 'Surya',
            'regions' => ['Mithila', 'Bihar', 'Nepal'],
        ],
        'Bohag Bihu' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 0,
            'description' => 'Assamese New Year festival cluster of Rongali Bihu',
            'deity' => 'Surya',
            'regions' => ['Assam'],
        ],
        'Magh Bihu (Bhogali Bihu)' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 9,
            'aliases' => ['Bhogali Bihu'],
            'description' => 'Assam harvest festival around Makara transition',
            'deity' => 'Agni/Surya',
            'regions' => ['Assam'],
        ],
        'Kati Bihu (Kongali Bihu)' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 6,
            'aliases' => ['Kongali Bihu'],
            'description' => 'Assam agrarian observance during Kati season',
            'deity' => 'Lakshmi',
            'regions' => ['Assam'],
        ],
        'Sajaibu Cheiraoba' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Manipuri New Year observance aligned with lunar new-year cycle',
            'deity' => 'Govinda',
            'regions' => ['Manipur'],
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
        ],
        'Ganga Sagar Mela' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 9,
            'description' => 'Pilgrimage fair at Gangasagar during Makara Sankranti',
            'deity' => 'Ganga',
            'regions' => ['West Bengal'],
        ],
        'Karadayan Nombu' =>
        [
            'type' => 'fixed_date',
            'month' => 3,
            'day' => 14,
            'description' => 'Tamil vrata observed by married women for family welfare',
            'deity' => 'Parvati/Shiva',
            'regions' => ['Tamil Nadu'],
        ],
        'Aadi Perukku' =>
        [
            'type' => 'fixed_date',
            'month' => 8,
            'day' => 3,
            'description' => 'Tamil river and water prosperity festival in Aadi month',
            'deity' => 'Kaveri/Parvati',
            'regions' => ['Tamil Nadu'],
        ],
        'Panguni Uthiram' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Tamil sacred marriage observance linked to Panguni full moon',
            'deity' => 'Murugan/Parvati/Shiva',
            'regions' => ['Tamil Nadu'],
            'karmakala_type' => 'madhyahna',
        ],
        'Raja Parba Day 1' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 2,
            'description' => 'Beginning of Odisha Raja Parba seasonal observance',
            'deity' => 'Bhudevi',
            'regions' => ['Odisha'],
        ],
        'Raja Parba Day 2' =>
        [
            'type' => 'day_after',
            'parent_festival' => 'Raja Parba Day 1',
            'days_after' => 1,
            'description' => 'Second day of Odisha Raja Parba',
            'deity' => 'Bhudevi',
            'regions' => ['Odisha'],
        ],
        'Raja Parba Day 3' =>
        [
            'type' => 'day_after',
            'parent_festival' => 'Raja Parba Day 1',
            'days_after' => 2,
            'description' => 'Third day of Odisha Raja Parba',
            'deity' => 'Bhudevi',
            'regions' => ['Odisha'],
        ],
        'Nuakhai' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Odisha harvest thanksgiving for new rice crop',
            'deity' => 'Samaleswari',
            'regions' => ['Odisha', 'Chhattisgarh'],
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
        ],
        'Karam Puja' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Karam Devta worship for prosperity and agricultural wellbeing',
            'deity' => 'Karam Devta',
            'regions' => ['Jharkhand', 'Bihar', 'Odisha', 'Chhattisgarh'],
            'karmakala_type' => 'sunrise',
        ],
        'Maha Saptami (Durga Puja)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Durga Puja Maha Saptami observance',
            'deity' => 'Durga',
            'regions' => ['Bengal', 'Odisha', 'Assam'],
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
        ],
        'Kojagari Lakshmi Puja' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Lakshmi worship on Sharad Purnima night (Kojagari)',
            'deity' => 'Lakshmi',
            'regions' => ['Bengal', 'Odisha', 'Assam'],
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
        ],
        'Bathukamma (Saddula)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Telangana floral festival culmination day (Saddula Bathukamma)',
            'deity' => 'Durga',
            'regions' => ['Telangana'],
            'karmakala_type' => 'sunrise',
        ],
        'Bonalu (Ashadha Sunday)' =>
        [
            'type' => 'weekday_in_month',
            'weekday' => 0,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Sunday Bonalu observance in Ashadha month',
            'deity' => 'Durga',
            'regions' => ['Telangana'],
        ],
        'Yaoshang' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Manipuri spring festival beginning on Phalguna Purnima',
            'deity' => 'Krishna',
            'regions' => ['Manipur'],
        ],
        'Chapchar Kut' =>
        [
            'type' => 'fixed_date',
            'month' => 3,
            'day' => 1,
            'description' => 'Mizo spring festival celebrated after forest clearing season',
            'deity' => 'Community Deities',
            'regions' => ['Mizoram'],
        ],
        'Losar' =>
        [
            'type' => 'fixed_date',
            'month' => 2,
            'day' => 28,
            'description' => 'Himalayan new year observance in Tibetan Buddhist traditions',
            'deity' => 'Buddha/Local Deities',
            'regions' => ['Ladakh', 'Sikkim', 'Arunachal Pradesh', 'Himalayan regions'],
        ],
        'Sankashti Chaturthi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 4,
            'description' => 'Monthly Sankashti fast dedicated to Lord Ganesha',
            'deity' => 'Ganesha',
            'regions' => ['Pan-India'],
            'karmakala_type' => 'sunrise',
        ],
        'Lakshmi Puja (Deepavali)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 14,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Deepavali night Lakshmi Puja in Pradosha window',
            'deity' => 'Lakshmi',
            'regions' => ['Pan-India'],
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
        ],
        'Bhai Tika' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Brother-sister ritual of tika and blessings (Nepal tradition)',
            'deity' => 'Yama/Yamuna',
            'regions' => ['Nepal', 'Himalayan regions'],
            'karmakala_type' => 'aparahna',
        ],
        'Nabanna Utsav' =>
        [
            'type' => 'fixed_date',
            'month' => 11,
            'day' => 15,
            'description' => 'Bengal new-rice thanksgiving harvest observance',
            'deity' => 'Lakshmi',
            'regions' => ['West Bengal'],
        ],
        'Rongali Bihu Day 1' =>
        [
            'type' => 'solar_sankranti',
            'rashi' => 0,
            'description' => 'First day of Rongali/Bohag Bihu in Assam; dedicated to cattle worship and agrarian renewal',
            'deity' => 'Gau Mata/Surya',
            'regions' => ['Assam'],
            'aliases' => ['Bohag Bihu Day 1', 'Goru Bihu'],
        ],
        'Rongali Bihu Day 2' =>
        [
            'type' => 'day_after',
            'parent_festival' => 'Rongali Bihu Day 1',
            'days_after' => 1,
            'description' => 'Second day of Rongali/Bohag Bihu; Assamese New Year observance with family blessings and new clothes',
            'deity' => 'Household Deities',
            'regions' => ['Assam'],
            'aliases' => ['Bohag Bihu Day 2', 'Manuh Bihu'],
        ],
        'Rongali Bihu Day 3' =>
        [
            'type' => 'day_after',
            'parent_festival' => 'Rongali Bihu Day 1',
            'days_after' => 2,
            'description' => 'Third day of Rongali/Bohag Bihu; worship of household and village deities',
            'deity' => 'Gosain/Household Deities',
            'regions' => ['Assam'],
            'aliases' => ['Bohag Bihu Day 3', 'Gosai Bihu'],
        ],
        'Rongali Bihu Day 4' =>
        [
            'type' => 'day_after',
            'parent_festival' => 'Rongali Bihu Day 1',
            'days_after' => 3,
            'description' => 'Fourth day of Rongali/Bohag Bihu; visiting relatives and strengthening family ties',
            'deity' => 'Family Ancestors',
            'regions' => ['Assam'],
            'aliases' => ['Bohag Bihu Day 4', 'Kutum Bihu'],
        ],
        'Rongali Bihu Day 5' =>
        [
            'type' => 'day_after',
            'parent_festival' => 'Rongali Bihu Day 1',
            'days_after' => 4,
            'description' => 'Fifth day of Rongali/Bohag Bihu; day of affection, music, dance, and social bonding',
            'deity' => 'Kamadeva',
            'regions' => ['Assam'],
            'aliases' => ['Bohag Bihu Day 5', 'Senehi Bihu'],
        ],
        'Rongali Bihu Day 6' =>
        [
            'type' => 'day_after',
            'parent_festival' => 'Rongali Bihu Day 1',
            'days_after' => 5,
            'description' => 'Sixth day of Rongali/Bohag Bihu; fairs, cultural performances, and community gatherings',
            'deity' => 'Cultural Deities',
            'regions' => ['Assam'],
            'aliases' => ['Bohag Bihu Day 6', 'Mela Bihu'],
        ],
        'Rongali Bihu Day 7' =>
        [
            'type' => 'day_after',
            'parent_festival' => 'Rongali Bihu Day 1',
            'days_after' => 6,
            'description' => 'Seventh and closing day of Rongali/Bohag Bihu',
            'deity' => 'Cultural Traditions',
            'regions' => ['Assam'],
            'aliases' => ['Bohag Bihu Day 7', 'Chera Bihu'],
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
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Largest fair in Kutch at Kakadbhit; honors 72 Yakshas who protected locals; Bhadarva Vad 12-14',
            'deity' => '72 Yakshas',
            'regions' => ['Kutch', 'Gujarat'],
        ],
        'Mota Yaksh Fair Day 2' =>
        [
            'type' => 'day_after',
            'parent_festival' => 'Mota Yaksh Fair (Jakh Bahotera)',
            'days_after' => 1,
            'description' => 'Second day of Mota Yaksh Fair at Kakadbhit (Bhadarva Vad 13)',
            'deity' => '72 Yakshas',
            'regions' => ['Kutch', 'Gujarat'],
        ],
        'Mota Yaksh Fair Day 3' =>
        [
            'type' => 'day_after',
            'parent_festival' => 'Mota Yaksh Fair (Jakh Bahotera)',
            'days_after' => 2,
            'description' => 'Third day of Mota Yaksh Fair at Kakadbhit (Bhadarva Vad 14)',
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
            'description' => 'Ashadha Gupta Navaratri Day 1 (Ghatasthapana). Tantric Mahavidya: Maa Kali. Nava Varahi: Swapna Varahi. Standard Navadurga: Maa Shailputri.',
            'deity' => 'Kali / Shailputri / Swapna Varahi',
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
            'description' => 'Ashadha Gupta Navaratri Day 2. Tantric Mahavidya: Maa Tara. Nava Varahi: Maha Varahi. Standard Navadurga: Maa Brahmacharini.',
            'deity' => 'Tara / Brahmacharini / Maha Varahi',
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
            'description' => 'Ashadha Gupta Navaratri Day 3. Tantric Mahavidya: Tripura Sundari. Nava Varahi: Kroda Varahi. Standard Navadurga: Maa Chandraghanta.',
            'deity' => 'Tripura Sundari / Chandraghanta / Kroda Varahi',
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
            'description' => 'Ashadha Gupta Navaratri Day 4. Tantric Mahavidya: Maa Bhuvaneshwari. Nava Varahi: Vikrita Varahi. Standard Navadurga: Maa Kushmanda.',
            'deity' => 'Bhuvaneshwari / Kushmanda / Vikrita Varahi',
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
            'description' => 'Ashadha Gupta Navaratri Day 5. Tantric Mahavidya: Maa Bhairavi. Nava Varahi: Varahi Panchami. Standard Navadurga: Maa Skandamata.',
            'deity' => 'Bhairavi / Skandamata / Varahi Panchami',
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
            'description' => 'Ashadha Gupta Navaratri Day 6. Tantric Mahavidya: Maa Chinnamasta. Nava Varahi: Ajna Varahi. Standard Navadurga: Maa Katyayani.',
            'deity' => 'Chinnamasta / Katyayani / Ajna Varahi',
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
            'description' => 'Ashadha Gupta Navaratri Day 7. Tantric Mahavidya: Maa Dhumavati. Nava Varahi: Dandini Varahi. Standard Navadurga: Maa Kalaratri.',
            'deity' => 'Dhumavati / Kalaratri / Dandini Varahi',
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
            'description' => 'Ashadha Gupta Navaratri Day 8. Tantric Mahavidya: Maa Bagalamukhi. Nava Varahi: Mrita Sanjivani Varahi. Standard Navadurga: Maa Mahagauri.',
            'deity' => 'Bagalamukhi / Mahagauri / Mrita Sanjivani Varahi',
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
            'description' => 'Ashadha Gupta Navaratri Day 9. Tantric Mahavidya: Maa Matangi. Nava Varahi: Vaishnavi Varahi. Standard Navadurga: Maa Siddhidatri.',
            'deity' => 'Matangi / Siddhidatri / Vaishnavi Varahi',
            'karmakala_type' => 'sunrise',
            'vriddhi_preference' => 'last',
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
            'description' => 'Magha Gupta Navaratri Day 1 (Ghatasthapana). Tantric Mahavidya: Maa Kali. Standard Navadurga: Maa Shailputri.',
            'deity' => 'Kali / Shailputri',
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
            'description' => 'Magha Gupta Navaratri Day 2. Tantric Mahavidya: Maa Tara. Standard Navadurga: Maa Brahmacharini.',
            'deity' => 'Tara / Brahmacharini',
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
            'description' => 'Magha Gupta Navaratri Day 3. Tantric Mahavidya: Tripura Sundari. Standard Navadurga: Maa Chandraghanta.',
            'deity' => 'Tripura Sundari / Chandraghanta',
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
            'description' => 'Magha Gupta Navaratri Day 4. Tantric Mahavidya: Maa Bhuvaneshwari. Standard Navadurga: Maa Kushmanda.',
            'deity' => 'Bhuvaneshwari / Kushmanda',
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
            'description' => 'Magha Gupta Navaratri Day 5. Tantric Mahavidya: Maa Bhairavi (collides with Vasant Panchami). Standard Navadurga: Maa Skandamata.',
            'deity' => 'Bhairavi / Skandamata',
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
            'description' => 'Magha Gupta Navaratri Day 6. Tantric Mahavidya: Maa Chinnamasta. Standard Navadurga: Maa Katyayani.',
            'deity' => 'Chinnamasta / Katyayani',
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
            'description' => 'Magha Gupta Navaratri Day 7. Tantric Mahavidya: Maa Dhumavati. Standard Navadurga: Maa Kalaratri.',
            'deity' => 'Dhumavati / Kalaratri',
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
            'description' => 'Magha Gupta Navaratri Day 8. Tantric Mahavidya: Maa Bagalamukhi. Standard Navadurga: Maa Mahagauri.',
            'deity' => 'Bagalamukhi / Mahagauri',
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
            'description' => 'Magha Gupta Navaratri Day 9. Tantric Mahavidya: Maa Matangi. Standard Navadurga: Maa Siddhidatri.',
            'deity' => 'Matangi / Siddhidatri',
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
        'Ugadi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'aliases' => ['Gudi Padwa'],
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
        'Yamuna Chhath' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Descent of Goddess Yamuna',
            'deity' => 'Yamuna',
        ],
        'Chaiti Chhath' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Summer Chhath festival',
            'deity' => 'Surya',
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
            'karmakala_type' => 'madhyahna',
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
            'karmakala_type' => 'madhyahna',
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
        'Shani Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Birth of Lord Shani',
            'deity' => 'Shani',
        ],
        'Savitri Amavasya' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Savitri Vrat',
            'deity' => 'Savitri',
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
            'allows_adhika' => true,
        ],
        'Nirjala Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Toughest Ekadashi vrat (observed without water) / Gayatri Jayanti',
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
            'kshaya_preference' => 'last',
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
        'Pola' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Thanksgiving festival for farmers and bulls',
            'deity' => 'Shiva',
            'regions' => ['Maharashtra'],
        ],
        'Kushotpatini Amavasya' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Amavasya dedicated to Goddess Kushotpatini',
            'deity' => 'Kushotpatini',
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
        'Goga Pancham' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 5,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Goga Panchami (Nag Panchami - Gujarat)'],
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
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
        ],
        'Randhan Chhath' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 6,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Gujarati Randhan Chhath - cooking all food one day in advance, offering cold food to Sheetala Mata',
            'deity' => 'Sheetala Mata',
            'regions' => ['Gujarat'],
        ],
        'Balarama Jayanti (Hala Shashthi)' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 6,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Birth celebration of Lord Balarama, worship of Haladhara (plough bearer)',
            'deity' => 'Balarama',
        ],
        'Sheetala Satam' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 7,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Sheetala Saptami'],
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
        'Hartalika Teej' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Kevada Trij'],
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
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
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
        'Parsva Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Jal Jhilani Ekadashi'],
            'description' => 'Lord Vishnu changes sides / Swaminarayan Jal Jhilani Utsav',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Parivartini Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Parsva Ekadashi', 'Jal Jhilani Ekadashi', 'Vamana Ekadashi'],
            'description' => 'Parivartini (Parsva) Ekadashi when Lord Vishnu turns side during Chaturmas',
            'deity' => 'Vishnu',
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
            'month_purnimanta' => 'Ashvina',
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
            'month_purnimanta' => 'Phalguna',
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
        'Purnima Shraddha' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Shraddha rituals for those who passed away on Purnima Tithi; start of the ancestor worship period',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Pitru Paksha Begins' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 1,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
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
        'Dussehra' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'aliases' => ['Vijayadashami'],
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
        'Sharad Purnima' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'aliases' => ['Manekthari Punam'],
            'description' => 'Sharad Purnima, known in Gujarat as Manekthari Punam',
            'deity' => 'Lakshmi/Krishna',
            'karmakala_type' => 'nishitha',
        ],
        'Gunatitanand Swami Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Birth anniversary of Gunatitanand Swami',
            'deity' => 'Swaminarayan',
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
        'Kali Puja' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Diwali'],
            'description' => 'Festival of lights / Worship of Goddess Kali',
            'deity' => 'Kali',
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'vriddhi_preference' => 'first',
        ],
        'Govardhan Puja' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Annakut'],
            'description' => 'Annakut (Swaminarayan) / Gujarati New Year',
            'deity' => 'Krishna',
            'karmakala_type' => 'sunrise',
        ],
        'Bestu Varas' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Gujarati New Year',
            'deity' => 'Lakshmi/Ganesha',
            'regions' => ['Gujarat'],
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
            'kshaya_preference' => 'last',
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
            'description' => 'Birth of Lord Kalabhairav (Shiva)',
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
        'Geeta Jayanti' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'aliases' => ['Mokshada Ekadashi'],
            'description' => 'Celebration of Bhagavad Gita / Gateway to Heaven',
            'deity' => 'Krishna',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Mokshada Ekadashi' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'aliases' => ['Geeta Jayanti'],
            'description' => 'Fasting for Mokshada Ekadashi / Geeta Jayanti',
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
            'description' => 'Birth of Dattatreya (Annapurna Jayanti)',
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
            'sun_sign' => 4,
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
        'Vasant Panchami' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'aliases' => ['Shikshapatri Jayanti'],
            'description' => 'Worship of Goddess Saraswati / Shikshapatri presentation',
            'deity' => 'Saraswati',
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
        'Maghi Purnima' =>
        [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'aliases' => ['Masi Magam'],
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
            'month_purnimanta' => 'Chaitra',
            'description' => 'Festival of colors (Dhuleti/Pushpadolotsav)',
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
            'vriddhi_preference' => 'last',
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
        ?array $yesterdayDetails = null,
        ?callable $fetchHistoricalSnapshot = null,
        bool $includeExtraWinners = false
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
            $type = (string) ($rules['type'] ?? 'tithi');

            // Adhika/Nija filtering logic for lunar (tithi) observances.
            // Default behavior is Nija-only unless explicitly marked otherwise.
            $adhikaAllowed = (bool) (($rules['allow_adhika'] ?? false) || ($rules['allows_adhika'] ?? false));
            $adhikaOnly = (bool) ($rules['adhika_only'] ?? false);

            if ($type === 'tithi') {
                if ($isAdhika && !$adhikaAllowed && !$adhikaOnly) {
                    continue; // regular tithi observances suppressed in Adhika month
                }
                if (!$isAdhika && $adhikaOnly) {
                    continue; // Adhika-only festival cannot occur in Nija month
                }
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
                    $festivals[] = $this->buildFestivalPayload($name, $rules, $resolved);
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
                        $festivals[] = $this->buildFestivalPayload($name, $rules, $resolvedYesterday);
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
                    $festivals[] = $this->buildFestivalPayload($name, $rules, $resolved);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                } elseif ($yesterdayDetails !== null && !isset($addedFestivalKeys[$name])) {
                    $resolvedYesterday = $this->ruleEngine->resolveNakshatraBasedFestival($name, $rules, $date->subDay(), $yesterdayDetails, $todayDetails);
                    if ($resolvedYesterday !== null && $resolvedYesterday['observance_date'] === $date->toDateString()) {
                        $festivals[] = $this->buildFestivalPayload($name, $rules, $resolvedYesterday);
                        $festivalMeta[] = [
                            'raw_name' => $name,
                            'adhika_only' => $adhikaOnly,
                            'is_ekadashi' => str_contains($name, 'Ekadashi'),
                        ];
                        $addedFestivalKeys[$name] = true;
                    }
                }
            } elseif ($this->matchesFestivalRules($date, $rules, $tithiNum, $paksha, $todayDetails)) {
                $festivals[] = $this->buildFestivalPayload($name, $rules);
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
                if ($meta['is_ekadashi'] && $meta['adhika_only']) {
                    $hasAdhikaOnlyEkadashi = true;
                    break;
                }
            }

            if ($hasAdhikaOnlyEkadashi) {
                $filteredFestivals = [];
                $filteredMeta = [];
                foreach ($festivals as $idx => $festival) {
                    $meta = $festivalMeta[$idx] ?? ['is_ekadashi' => false, 'adhika_only' => false];
                    $isEkadashi = $meta['is_ekadashi'];
                    $isAdhikaOnly = $meta['adhika_only'];
                    if (!$isEkadashi || $isAdhikaOnly) {
                        $filteredFestivals[] = $festival;
                        $filteredMeta[] = $meta;
                    }
                }
                $festivals = $filteredFestivals;
                $festivalMeta = $filteredMeta;
            }
        }

        // Resolve day_after festivals (e.g. Holi after Holika Dahan)
        // These require checking if the parent festival was observed on a previous date
        $dayAfterFestivals = $this->resolveDayAfterFestivals(
            $date,
            $todayDetails,
            $tomorrowDetails,
            $yesterdayDetails,
            $fetchHistoricalSnapshot,
            $addedFestivalKeys
        );
        foreach ($dayAfterFestivals as $item) {
            $festivals[] = $item['festival'];
            $festivalMeta[] = $item['meta'];
            $addedFestivalKeys[] = $item['key'];
        }

        return $festivals;
    }

    /**
     * Build a complete, localized festival payload while preserving the
     * calculation basis from the registry and resolver decision context.
     */
    public function buildFestivalPayload(string $name, array $rules, ?array $resolved = null): array
    {
        $regions = $rules['regions'] ?? ['Pan-India'];
        $aliases = array_values(array_map(
            static fn (string $alias): string => Localization::translate('Festival', $alias),
            array_map('strval', (array) ($rules['aliases'] ?? []))
        ));
        $deity = $rules['deity'] ?? null;

        $payload = [
            'name' => Localization::translate('Festival', $name),
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
                'deity' => Localization::translate('Deity', $rule['deity']),
                'benefit' => Localization::translate('Benefit', $benefit),
                'paksha' => $paksha,
                'paksha_name' => constant(Paksha::class . '::' . $paksha)->getName(),
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
     * Resolve day_after festivals by checking if the parent festival was observed
     * on previous dates (today, yesterday, or up to 3 days back).
     *
     * Returns array of ['festival' => ..., 'meta' => ..., 'key' => ...] entries.
     */
    private function resolveDayAfterFestivals(
        CarbonImmutable $date,
        array $todayDetails,
        array $tomorrowDetails,
        ?array $yesterdayDetails,
        ?callable $fetchHistoricalSnapshot,
        array $addedFestivalKeys
    ): array {
        $results = [];

        foreach (self::FESTIVALS as $name => $rules) {
            if ((string) ($rules['type'] ?? '') !== 'day_after') {
                continue;
            }

            $parentName = (string) ($rules['parent_festival'] ?? '');
            $daysAfter = (int) ($rules['days_after'] ?? 1);

            if ($parentName === '') {
                continue;
            }

            $parentDate = $date->subDays($daysAfter);
            $parentFound = false;

            // Case 1: daysAfter === 1, use yesterdayDetails directly
            if ($daysAfter === 1 && $yesterdayDetails !== null) {
                $parentFound = $this->checkDayAfterParent($parentName, $yesterdayDetails);
            }
            // Case 2: daysAfter > 1, fetch historical snapshot via callback
            elseif ($daysAfter > 1 && $fetchHistoricalSnapshot !== null) {
                $historicalSnapshot = $fetchHistoricalSnapshot($parentDate);
                if ($historicalSnapshot !== null) {
                    $parentFound = $this->checkDayAfterParent($parentName, $historicalSnapshot);
                }
            }

            if (!$parentFound) {
                continue;
            }

            $key = 'day_after:' . $name . ':' . $date->toDateString();
            if (in_array($key, $addedFestivalKeys, true)) {
                continue;
            }

            $resolved = [
                'festival_name' => $name,
                'standard_date' => $date->toDateString(),
                'observance_date' => $date->toDateString(),
                'observance_note' => sprintf(
                    Localization::translate('String', 'observance_note_day_after'),
                    $daysAfter,
                    Localization::translate('Festival', $parentName)
                ),
                'decision' => [
                    'winning_reason' => 'day_after_parent_festival',
                    'parent_festival' => $parentName,
                    'parent_observance_date' => $parentDate->toDateString(),
                    'days_after' => $daysAfter,
                    'winning_score' => 1000,
                ],
            ];

            $festival = $this->buildFestivalPayload($name, $rules, $resolved);
            $results[] = [
                'festival' => $festival,
                'meta' => ['winning_score' => 1000, 'is_day_after' => true],
                'key' => $key,
            ];
        }

        return $results;
    }

    /**
     * Check if a parent festival would be resolved on a given snapshot.
     * Returns true if the parent's rules match the snapshot's panchang, false otherwise.
     */
    private function checkDayAfterParent(string $parentName, array $snapshot): bool
    {
        $parentRules = self::FESTIVALS[$parentName] ?? null;
        if ($parentRules === null) {
            return false;
        }

        $tithi = $snapshot['Tithi'] ?? null;
        if ($tithi === null) {
            return false;
        }

        $absoluteTithi = (int) ($tithi['index'] ?? 0);
        $paksha = $tithi['paksha'] ?? 'Shukla';
        // Convert absolute tithi to paksha-relative (1-15)
        $relativeTithi = $absoluteTithi > 15 ? $absoluteTithi - 15 : $absoluteTithi;

        // Determine which tithi number to use for matching
        // If parent rule specifies a paksha, use relative tithi; otherwise use absolute
        $rulePaksha = $parentRules['paksha'] ?? null;
        $tithiForMatching = $rulePaksha !== null ? $relativeTithi : $absoluteTithi;

        // For day_after festivals with daysAfter=1, we check if the parent
        // rules match yesterday's snapshot. We use a minimal date check
        // since the actual date matching is done by tithi/paksha/month.
        return $this->matchesFestivalRules(
            CarbonImmutable::now('UTC'),
            $parentRules,
            $tithiForMatching,
            $paksha,
            $snapshot,
        );
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
            'tithi' => $this->formatTithiRule($rules['tithi'] ?? ($resolved['required_tithi'] ?? null), $rules['paksha'] ?? ($resolved['paksha'] ?? null)),
            'paksha' => $rules['paksha'] ?? ($resolved['paksha'] ?? null),
            'paksha_name' => $this->localizedPakshaName($rules['paksha'] ?? ($resolved['paksha'] ?? null)),
            'month' => $this->formatMonthRule($rules),
            'solar_rashi' => $this->formatRashiRule($rules['rashi'] ?? null),
            'nakshatra' => is_string($nakshatraRaw) && $nakshatraRaw !== '' ? $this->localizedNakshatraName($nakshatraRaw) : $nakshatraRaw,
            'nakshatra_key' => $nakshatraRaw,
            'nakshatra_only' => $rules['nakshatra_only'] ?? null,
            'fixed_date' => $this->formatFixedDateRule($rules),
            'weekday' => $this->formatWeekdayRule($rules['weekday'] ?? null),
            'karmakala_type' => $rules['karmakala_type'] ?? ($resolved['karmakala_type'] ?? null),
            'karmakala_type_name' => $this->localizedString($rules['karmakala_type'] ?? ($resolved['karmakala_type'] ?? null)),
            'strict_karmakala' => $rules['strict_karmakala'] ?? null,
            'vriddhi_preference' => $rules['vriddhi_preference'] ?? null,
            'prefer_first_karmakala' => $rules['prefer_first_karmakala'] ?? null,
            'prefer_nakshatra' => $rules['prefer_nakshatra'] ?? null,
            'preferred_nakshatra' => is_string($nakshatraRaw) && $nakshatraRaw !== '' ? $this->localizedNakshatraName($nakshatraRaw) : null,
            'preferred_nakshatra_key' => $rules['nakshatra'] ?? null,
            'adhika' => $this->formatAdhikaRule($rules),
            'relative_day' => $this->formatRelativeDayRule($rules),
            'parent_festival' => is_string($parentFestivalRaw) && $parentFestivalRaw !== '' ? Localization::translate('Festival', $parentFestivalRaw) : null,
            'parent_festival_key' => $parentFestivalRaw,
        ];

        return $this->filterEmptyMetadata($basis);
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
            'weekday_in_month' => 'weekday_in_lunar_month',
            'weekday_tithi' => 'weekday_and_tithi',
            'day_after' => 'relative_day_after_parent_festival',
            default => 'tithi',
        };
    }

    private function formatTithiRule(mixed $ruleTithi, mixed $paksha): ?array
    {
        if ($ruleTithi === null || $ruleTithi === '') {
            return null;
        }

        $numbers = array_values(array_map('intval', is_array($ruleTithi) ? $ruleTithi : [$ruleTithi]));
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
            'names' => array_map([$this, 'safeTithiName'], $absoluteNumbers),
        ]);
    }

    private function safeTithiName(int $absoluteNumber): ?string
    {
        if ($absoluteNumber < 1 || $absoluteNumber > 30) {
            return null;
        }

        return Tithi::from($absoluteNumber)->getName();
    }

    private function formatMonthRule(array $rules): ?array
    {
        if (!isset($rules['month_amanta']) && !isset($rules['month_purnimanta'])) {
            return null;
        }

        $calendarType = strtolower((string) config('panchang.defaults.calendar_type', 'amanta'));
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

        return $decision;
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
                return Masa::from(((int) $number) - 1)->getName();
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

    private function localizedString(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return Localization::translate('String', $value);
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

        // Handle Adhika Masa rules: only allow tithi-based festivals in Adhika Masa if explicitly allowed.
        if ($type === 'tithi') {
            $isAdhika = (bool) ($panchangDetails['Hindu_Calendar']['Is_Adhika'] ?? false);
            $adhikaOnly = (bool) ($rules['adhika_only'] ?? false);
            $allowsAdhika = (bool) (($rules['allows_adhika'] ?? false) || ($rules['allow_adhika'] ?? false));

            if ($isAdhika && !$adhikaOnly && !$allowsAdhika) {
                return false; // Regular lunar festivals are blocked in Adhika Masa
            }
            if (!$isAdhika && $adhikaOnly) {
                return false; // Adhika-only lunar festivals shouldn't appear in Nija Masa
            }
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
        $dynamicPurnimanta = $this->getDynamicPurnimantaName($rules, $calendar);
        $purnimanta = $this->normalizeMonthName($dynamicPurnimanta);
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

    /**
     * Dynamically determine the expected Purnimanta month name based on the festival rule's paksha.
     * This fixes edge cases where the daily snapshot's Purnimanta month (from a Krishna sunrise)
     * mismatches a Shukla festival occurring later that same day.
     */
    private function getDynamicPurnimantaName(array $rules, array $calendar): string
    {
        $basePurnimanta = (string) ($calendar['Month_Purnimanta_En'] ?? $calendar['Month_Purnimanta'] ?? '');

        if (isset($rules['paksha'], $calendar['Amanta_Index'])) {
            $rulePakshas = is_array($rules['paksha']) ? $rules['paksha'] : [$rules['paksha']];
            if (count($rulePakshas) === 1) {
                $rulePaksha = $rulePakshas[0];
                $amantaIdx = (int) $calendar['Amanta_Index'];
                // In Purnimanta, Shukla paksha takes the Amanta month name; Krishna paksha takes the next month.
                $purnimantaIdx = ($rulePaksha === 'Shukla') ? $amantaIdx : ($amantaIdx + 1) % 12;
                $purnimantaDynamic = Masa::from($purnimantaIdx)->getName('en');

                if ((bool) ($calendar['Is_Adhika'] ?? false) && $rulePaksha === 'Shukla') {
                    $purnimantaDynamic .= ' (Adhika)';
                }
                return $purnimantaDynamic;
            }
        }

        return $basePurnimanta;
    }
}
