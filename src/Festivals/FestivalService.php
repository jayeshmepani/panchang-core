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
        'Asha Dashami Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Fasting dedicated to Goddess Asha/Parvati for fulfillment of desires',
            'deity' => 'Parvati',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Kokila Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Dedicated to Goddess Sati/Parvati in the form of a cuckoo (Kokila)',
            'deity' => 'Parvati',
            'fasting' => true,
            'regions' => ['Gujarat', 'Maharashtra'],
            'karmakala_type' => 'sunrise',
        ],
        'Andal Jayanthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'nakshatra' => 'Purva Phalguni',
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Shravana',
            'aliases' => ['Aadi Pooram'],
            'description' => 'Birth anniversary of Andal (Aadi month, Pooram nakshatra)',
            'deity' => 'Andal',
            'regions' => ['Tamil Nadu'],
            'karmakala_type' => 'sunrise',
            'prefer_nakshatra' => true,
        ],
        'Durva Ganapati Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Worship of Lord Ganesha with Durva grass',
            'deity' => 'Ganesha',
            'karmakala_type' => 'sunrise',
        ],
        'Malayalam New Year' => [
            'type' => 'solar_sankranti',
            'rashi' => 4,
            'aliases' => ['Chingam 1'],
            'description' => 'First day of the Malayalam calendar (Chingam month)',
            'deity' => 'Vishnu',
            'regions' => ['Kerala'],
        ],
        'Bahula Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Worship of cows, associated with Lord Krishna',
            'deity' => 'Krishna/Kamadhenu',
            'karmakala_type' => 'sunrise',
        ],
        'Hala Shashthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 6,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Hal Chhath', 'Har Chhath', 'Lalahi Chhath'],
            'description' => 'Regional vrata for the longevity, health and wellbeing of children, observed in connection with Lord Balarama and Chhathi traditions',
            'deity' => 'Balarama/Chhathi Mata',
            'regions' => ['Bihar', 'Nepal', 'North India'],
            'karmakala_type' => 'sunrise',
        ],
        'Kali Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Birth anniversary of Goddess Kali (Mahavidya)',
            'deity' => 'Kali',
            'karmakala_type' => 'midnight',
        ],
        'Vindhyavasini Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Appearance day of Goddess Vindhyavasini',
            'deity' => 'Vindhyavasini (Durga)',
            'karmakala_type' => 'sunrise',
        ],
        'Pithori Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Worship of Goddess Durga (Pithori) by mothers for children',
            'deity' => 'Durga',
            'karmakala_type' => 'sunrise',
        ],
        'Vrishabhotsava' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Worship of bulls (Nandi) on Shravana Amavasya',
            'deity' => 'Nandi/Shiva',
            'karmakala_type' => 'sunrise',
        ],
        'Lalita Saptami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Worship of Goddess Lalita/Gauri',
            'deity' => 'Lalita (Gauri)',
            'karmakala_type' => 'sunrise',
        ],
        'Durva Ashtami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Dharo Atham', 'Dharo Aatham'],
            'description' => 'Durva Ashtami, also known as Dharo Atham, is a Bhadrapada Shukla Ashtami observance associated with sacred durva grass and auspicious worship.',
            'deity' => 'Ganesha/Shiva',
            'karmakala_type' => 'sunset',
            'durva_ashtami_purvaviddha_table' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Dashavatara Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Fasting dedicated to the ten avatars of Lord Vishnu',
            'deity' => 'Vishnu (Dashavatara)',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Bhuvaneshvari Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Birth anniversary of Goddess Bhuvaneshvari (Mahavidya)',
            'deity' => 'Bhuvaneshvari',
            'karmakala_type' => 'sunrise',
        ],
        'Shashthi Shraddha' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 6,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Pitru Paksha Shraddha for those who died on Shashthi tithi',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Saptami Shraddha' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 7,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Pitru Paksha Shraddha for those who died on Saptami tithi',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Ashtami Shraddha' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Pitru Paksha Shraddha for those who died on Ashtami tithi',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Magha Shraddha' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'nakshatra' => 'Magha',
            'paksha' => 'Krishna',
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Auspicious Pitru Paksha Shraddha falling under Magha Nakshatra',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
            'prefer_nakshatra' => true,
        ],
        'Chaturdashi Shraddha' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 14,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Pitru Paksha Shraddha for those who died an unnatural death',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Maharaja Agrasen Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Birth anniversary of legendary King Agrasen',
            'deity' => 'Agrasen',
            'karmakala_type' => 'sunrise',
        ],
        'Kapardisha Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Dedicated to Kapardisha (Shiva)',
            'deity' => 'Shiva',
            'karmakala_type' => 'sunrise',
        ],
        'Upang Lalita Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Fasting dedicated to Goddess Lalita during Navratri',
            'deity' => 'Lalita (Durga)',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Aparajita Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Worship of Goddess Aparajita (invincible form) on Vijayadashami',
            'deity' => 'Aparajita (Durga)',
            'karmakala_type' => 'sunrise',
        ],
        'Madhvacharya Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Birth anniversary of Sri Madhvacharya',
            'deity' => 'Madhvacharya',
            'karmakala_type' => 'sunrise',
        ],
        'Padmanabha Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Dedicated to Lord Padmanabha (Vishnu)',
            'deity' => 'Vishnu (Padmanabha)',
            'karmakala_type' => 'sunrise',
        ],
        'Atla Tadde' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 3,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Traditional festival of Andhra Pradesh for married women (similar to Karwa Chauth)',
            'deity' => 'Gauri',
            'regions' => ['Andhra Pradesh'],
            'karmakala_type' => 'sunrise',
        ],
        'Radha Kunda Snan' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Auspicious holy dip at Radha Kunda on Ahoi Ashtami midnight',
            'deity' => 'Radha/Krishna',
            'regions' => ['Uttar Pradesh (Vrindavan)'],
            'karmakala_type' => 'midnight',
        ],
        'Kamala Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Birth anniversary of Goddess Kamala (Mahavidya)',
            'deity' => 'Kamala (Lakshmi)',
            'karmakala_type' => 'sunrise',
        ],
        'Kansa Vadh' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Commemoration of the slaying of King Kansa by Lord Krishna',
            'deity' => 'Krishna',
            'karmakala_type' => 'sunrise',
        ],
        'Guruvayur Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Special Ekadashi observed at Guruvayur Temple',
            'deity' => 'Guruvayurappan (Krishna)',
            'regions' => ['Kerala'],
            'karmakala_type' => 'sunrise',
        ],
        'Vishweshwara Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 14,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Vaikuntha Chaturdashi fasting dedicated to Lord Shiva/Vishnu',
            'deity' => 'Shiva/Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Manikarnika Snan' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 14,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Holy dip at Manikarnika Ghat, Varanasi',
            'deity' => 'Shiva',
            'regions' => ['Uttar Pradesh (Varanasi)'],
            'karmakala_type' => 'sunrise',
        ],
        'Pushkara Snana' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Auspicious holy dip during Kartika Purnima',
            'deity' => 'Brahma/Vishnu/Shiva',
            'karmakala_type' => 'sunrise',
        ],
        'Bala Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Birth anniversary of Goddess Bala Tripura Sundari',
            'deity' => 'Bala Tripura Sundari',
            'karmakala_type' => 'sunrise',
        ],
        'Krichchhra Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Rigorous fasting dedicated to Lord Ganesha',
            'deity' => 'Ganesha',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Bhairavi Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Birth anniversary of Goddess Bhairavi (Mahavidya)',
            'deity' => 'Bhairavi',
            'karmakala_type' => 'sunrise',
        ],
        'First Shravan Somwar Vrat' => [
            'type' => 'nth_weekday_in_month',
            'weekday' => 1,
            'nth' => 1,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'First Monday fasting in the month of Shravana',
            'deity' => 'Shiva',
            'fasting' => true,
        ],
        'Second Shravan Somwar Vrat' => [
            'type' => 'nth_weekday_in_month',
            'weekday' => 1,
            'nth' => 2,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Second Monday fasting in the month of Shravana',
            'deity' => 'Shiva',
            'fasting' => true,
        ],
        'Third Shravan Somwar Vrat' => [
            'type' => 'nth_weekday_in_month',
            'weekday' => 1,
            'nth' => 3,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Third Monday fasting in the month of Shravana',
            'deity' => 'Shiva',
            'fasting' => true,
        ],
        'Fourth Shravan Somwar Vrat' => [
            'type' => 'nth_weekday_in_month',
            'weekday' => 1,
            'nth' => 4,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Fourth Monday fasting in the month of Shravana',
            'deity' => 'Shiva',
            'fasting' => true,
        ],
        'First Mangala Gauri Vrat' => [
            'type' => 'nth_weekday_in_month',
            'weekday' => 2,
            'nth' => 1,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'First Tuesday fasting in the month of Shravana dedicated to Goddess Gauri',
            'deity' => 'Gauri',
            'fasting' => true,
        ],
        'Second Mangala Gauri Vrat' => [
            'type' => 'nth_weekday_in_month',
            'weekday' => 2,
            'nth' => 2,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Second Tuesday fasting in the month of Shravana dedicated to Goddess Gauri',
            'deity' => 'Gauri',
            'fasting' => true,
        ],
        'Third Mangala Gauri Vrat' => [
            'type' => 'nth_weekday_in_month',
            'weekday' => 2,
            'nth' => 3,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Third Tuesday fasting in the month of Shravana dedicated to Goddess Gauri',
            'deity' => 'Gauri',
            'fasting' => true,
        ],
        'Fourth Mangala Gauri Vrat' => [
            'type' => 'nth_weekday_in_month',
            'weekday' => 2,
            'nth' => 4,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Fourth Tuesday fasting in the month of Shravana dedicated to Goddess Gauri',
            'deity' => 'Gauri',
            'fasting' => true,
        ],
        'Ravivar Vrat' => [
            'type' => 'weekday',
            'weekday' => 0,
            'description' => 'Weekly Sunday fast dedicated to Lord Surya',
            'deity' => 'Sun',
            'fasting' => true,
            'aliases' => ['Sunday Vrat', 'Navagraha Weekday Fasting'],
        ],
        'Somwar Vrat' => [
            'type' => 'weekday',
            'weekday' => 1,
            'description' => 'Weekly Monday fast dedicated to Lord Shiva',
            'deity' => 'Shiva',
            'fasting' => true,
            'aliases' => ['Monday Vrat', 'Deities Weekdays Fasting'],
        ],
        'Mangalwar Vrat' => [
            'type' => 'weekday',
            'weekday' => 2,
            'description' => 'Weekly Tuesday fast dedicated to Hanuman and Mangala',
            'deity' => 'Hanuman',
            'fasting' => true,
            'aliases' => ['Tuesday Vrat'],
        ],
        'Budhwar Vrat' => [
            'type' => 'weekday',
            'weekday' => 3,
            'description' => 'Weekly Wednesday fast dedicated to Lord Vishnu and Budha',
            'deity' => 'Vishnu',
            'fasting' => true,
            'aliases' => ['Wednesday Vrat'],
        ],
        'Guruvar Vrat' => [
            'type' => 'weekday',
            'weekday' => 4,
            'description' => 'Weekly Thursday fast dedicated to Brihaspati and Lord Vishnu',
            'deity' => 'Vishnu',
            'fasting' => true,
            'aliases' => ['Brihaspativar Vrat', 'Thursday Vrat'],
        ],
        'Shukravar Vrat' => [
            'type' => 'weekday',
            'weekday' => 5,
            'description' => 'Weekly Friday fast dedicated to Goddess Lakshmi and Shukra',
            'deity' => 'Lakshmi',
            'fasting' => true,
            'aliases' => ['Friday Vrat'],
        ],
        'Shanivar Vrat' => [
            'type' => 'weekday',
            'weekday' => 6,
            'description' => 'Weekly Saturday fast dedicated to Lord Shani',
            'deity' => 'Shani',
            'fasting' => true,
            'aliases' => ['Saturday Vrat'],
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
            'aliases' => ['Baisakhi', 'Puthandu', 'Mesha Vishu'],
            'description' => 'Solar New Year',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Vrishabha Sankranti' => [
            'type' => 'solar_sankranti',
            'rashi' => 1,
            'description' => 'Sun enters Vrishabha',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Mithuna Sankranti' => [
            'type' => 'solar_sankranti',
            'rashi' => 2,
            'description' => 'Sun enters Mithuna',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Karka Sankranti' => [
            'type' => 'solar_sankranti',
            'rashi' => 3,
            'description' => 'Sun enters Karka',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Simha Sankranti' => [
            'type' => 'solar_sankranti',
            'rashi' => 4,
            'description' => 'Sun enters Simha',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Kanya Sankranti (Vishwakarma Puja)' => [
            'type' => 'solar_sankranti',
            'rashi' => 5,
            'description' => 'Worship of divine architect Vishwakarma',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
            'deity' => 'Vishwakarma',
        ],
        'Tula Sankranti' => [
            'type' => 'solar_sankranti',
            'rashi' => 6,
            'description' => 'Sun enters Tula',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Vrischika Sankranti' => [
            'type' => 'solar_sankranti',
            'rashi' => 7,
            'description' => 'Sun enters Vrischika',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Dhanu Sankranti' => [
            'type' => 'solar_sankranti',
            'rashi' => 8,
            'description' => "Dhanu Sankranti marks the Sun's entry into sidereal Sagittarius and the beginning of Dhanurmasa observances.",
            'regions' =>
            [
                0 => 'Pan-India',
            ],
            'aliases' => ['Dhanurmas Festival Begins', 'Thakorji Thal Vahela Begins', 'Early Thal Begins'],
            'sect_specific' => true,
            'ritual_profile' => 'dhanurmas_satsangi',
            'resolution_policy' => [
                'dhanurmas_period' => true,
                'early_thal_begins' => true,
                'period_end' => 'Makara Sankranti (Pongal)',
            ],
            'ritual_layers' => [
                'dhanurmas_daily_seva',
                'early_thakorji_thal',
            ],
            'source_refs' => ['Satsangi Jeevan 4.59'],
        ],
        'Makara Sankranti (Pongal)' => [
            'type' => 'solar_sankranti',
            'rashi' => 9,
            'aliases' => [
                'Uttarayan',          // Gujarat
                'Pongal',             // Tamil Nadu
                'Khichdi',            // North India / Uttar Pradesh
                'Til Sankranti',      // Rajasthan / Maharashtra
                'Ghughuti',           // Uttarakhand
                'Makar Puja',         // Odisha / Bengal
                'Maghi',              // Punjab
                'Sakraat',            // Haryana
            ],
            'description' => "Makara Sankranti marks the Sun's entry into sidereal Capricorn and is observed with holy bathing, worship, charity and seasonal offerings.",
            'after_sunset_next_day_punya_rule' => true,
            'source_refs' => ['Satsangi Jeevan 4.59'],
            'regions' => [
                0 => 'Pan-India',
            ],
            'deity' => 'Surya',
        ],
        'Mattu Pongal' => [
            'type' => 'day_after',
            'parent_festival' => 'Makara Sankranti (Pongal)',
            'days_after' => 1,
            'description' => 'Day of Pongal dedicated to the worship and thanksgiving of cattle',
            'deity' => 'Nandi/Cattle',
            'regions' => ['Tamil Nadu'],
        ],
        'Kumbha Sankranti' => [
            'type' => 'solar_sankranti',
            'rashi' => 10,
            'description' => 'Sun enters Kumbha',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Meena Sankranti' => [
            'type' => 'solar_sankranti',
            'rashi' => 11,
            'description' => 'Sun enters Meena',
            'regions' =>
            [
                0 => 'Pan-India',
            ],
        ],
        'Vasi Uttarayan' => [
            'type' => 'fixed_date',
            'month' => 1,
            'day' => 15,
            'description' => 'Second day of the kite festival in Gujarat',
            'regions' => ['Gujarat'],
        ],
        'Lohri' => [
            'type' => 'fixed_date',
            'month' => 1,
            'day' => 13,
            'description' => 'Punjabi harvest festival; bonfire celebration marking end of winter solstice',
            'deity' => 'Agni/Surya',
            'regions' => ['Punjab', 'Haryana', 'Delhi', 'North India'],
        ],

        'Cheti Chand' => [
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
        'Vishu' => [
            'type' => 'solar_sankranti',
            'rashi' => 0,
            'description' => 'Kerala Hindu New Year; first day of Medam month; Vishukkani arrangement',
            'deity' => 'Krishna/Vishnu',
            'regions' => ['Kerala', 'Tamil Nadu', 'Karnataka'],
        ],
        'Pohela Boishakh' => [
            'type' => 'solar_sankranti',
            'rashi' => 0,
            'aliases' => ['Pahela Baishakh'],
            'description' => 'Bengali Solar New Year observed in Bengal tradition; alternate naming tradition of Bengali Solar New Year',
            'deity' => 'Surya',
            'regions' => ['West Bengal', 'Bangladesh', 'Bengali'],
        ],
        'Pana Sankranti' => [
            'type' => 'solar_sankranti',
            'rashi' => 0,
            'aliases' => ['Maha Vishuba Sankranti'],
            'description' => 'Odia Solar New Year celebrated with pana offerings; traditional naming for Solar New Year Sankranti',
            'deity' => 'Surya',
            'regions' => ['Odisha'],
        ],

        'Jur Sital' => [
            'type' => 'fixed_date',
            'month' => 4,
            'day' => 15,
            'description' => 'Maithili New Year observance with cooling and water rituals',
            'deity' => 'Surya',
            'regions' => ['Mithila', 'Bihar', 'Nepal'],
        ],
        'Kati Bihu (Kongali Bihu)' => [
            'type' => 'solar_sankranti',
            'rashi' => 6,
            'aliases' => ['Kongali Bihu'],
            'description' => 'Assam agrarian observance during Kati season',
            'deity' => 'Lakshmi',
            'regions' => ['Assam'],
        ],
        'Sajaibu Cheiraoba' => [
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
        'Ganga Sagar Mela' => [
            'type' => 'solar_sankranti',
            'rashi' => 9,
            'description' => 'Pilgrimage fair at Gangasagar during Makara Sankranti',
            'deity' => 'Ganga',
            'regions' => ['West Bengal'],
        ],
        'Karadayan Nombu' => [
            'type' => 'fixed_date',
            'month' => 3,
            'day' => 14,
            'description' => 'Tamil vrata observed by married women for family welfare',
            'deity' => 'Parvati/Shiva',
            'regions' => ['Tamil Nadu'],
        ],
        'Aadi Perukku' => [
            'type' => 'fixed_date',
            'month' => 8,
            'day' => 3,
            'description' => 'Tamil river and water prosperity festival in Aadi month',
            'deity' => 'Kaveri/Parvati',
            'regions' => ['Tamil Nadu'],
        ],
        'Panguni Uthiram' => [
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
        'Raja Parba Day 1' => [
            'type' => 'solar_sankranti',
            'rashi' => 2,
            'description' => 'Beginning of Odisha Raja Parba seasonal observance',
            'deity' => 'Bhudevi',
            'regions' => ['Odisha'],
        ],
        'Raja Parba Day 2' => [
            'type' => 'day_after',
            'parent_festival' => 'Raja Parba Day 1',
            'days_after' => 1,
            'description' => 'Second day of Odisha Raja Parba',
            'deity' => 'Bhudevi',
            'regions' => ['Odisha'],
        ],
        'Raja Parba Day 3' => [
            'type' => 'day_after',
            'parent_festival' => 'Raja Parba Day 1',
            'days_after' => 2,
            'description' => 'Third day of Odisha Raja Parba',
            'deity' => 'Bhudevi',
            'regions' => ['Odisha'],
        ],
        'Nuakhai' => [
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
        'Karam Puja' => [
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
        'Maha Saptami (Durga Puja)' => [
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
        'Bathukamma (Saddula)' => [
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
        'Bonalu (Ashadha Sunday)' => [
            'type' => 'weekday_in_month',
            'weekday' => 0,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Sunday Bonalu observance in Ashadha month',
            'deity' => 'Durga',
            'regions' => ['Telangana'],
        ],
        'Yaoshang' => [
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
        'Chapchar Kut' => [
            'type' => 'fixed_date',
            'month' => 3,
            'day' => 1,
            'description' => 'Mizo spring festival celebrated after forest clearing season',
            'deity' => 'Community Deities',
            'regions' => ['Mizoram'],
        ],
        'Losar' => [
            'type' => 'fixed_date',
            'month' => 2,
            'day' => 28,
            'description' => 'Himalayan new year observance in Tibetan Buddhist traditions',
            'deity' => 'Buddha/Local Deities',
            'regions' => ['Ladakh', 'Sikkim', 'Arunachal Pradesh', 'Himalayan regions'],
        ],
        'Sankashti Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'paksha' => 'Krishna',
            'tithi' => 4,
            'description' => 'Monthly Sankashti Chaturthi is a Krishna Paksha Chaturthi vrata dedicated to Lord Ganesha, traditionally associated with moonrise worship.',
            'deity' => 'Ganesha',
            'aliases' => ['Angarak Sankashti Chaturthi', 'Angarki Sankashti Chaturthi'],
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'sankashti_truth_table' => true,
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'locator' => 'Sankashti Chaturthi section',
                    'supports' => 'Generic Krishna Paksha Chaturthi vrata is selected by moonrise-vyapini Chaturthi.',
                ],
            ],
        ],
        'Vinayaki Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'description' => 'Monthly Vinayaki Chaturthi is a Shukla Paksha Chaturthi vrata dedicated to Lord Ganesha and auspicious beginnings.',
            'deity' => 'Ganesha',
            'fasting' => true,
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'vinayaki_chaturthi_truth_table' => true,
            'previous_tithi_vedha_tolerated' => true,
            'prefer_full_karmakala_coverage' => true,
            'gujarati_special_case' => 'prefer_full_madhyahna_chaturthi_coverage_over_partial_previous_overlap',
            'excluded_months_amanta' => ['Bhadrapada'],
            'excluded_months_purnimanta' => ['Bhadrapada'],
            'resolution_policy' => [
                'monthly_excluding_annual' => true,
                'inherit_ganapati_pradurbhava' => true,
                'madhyahna_vyapini' => true,
                'tritiya_viddha_preferred' => true,
            ],
            'source_refs' => ['Satsangi Jeevan 4.56'],
        ],
        'Lambodara Sankashti Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Lambodara Sankashti Chaturthi',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'sankashti_truth_table' => true,
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Magha',
            'aliases' => ['Sankashti Chaturthi', 'Lambodara Sankashti'],
            'description' => 'Sankashti Chaturthi of Pausha/Magha month',
            'deity' => 'Ganesha',
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Generic Sankashti Chaturthi section',
                    'supports' => 'The named monthly Sankashti identity inherits the generic moonrise-vyapini Sankashti Chaturthi rule.',
                ],
            ],
        ],
        'Dwijapriya Sankashti Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Dwijapriya Sankashti Chaturthi',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'sankashti_truth_table' => true,
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Phalguna',
            'aliases' => ['Sankashti Chaturthi', 'Dwijapriya Sankashti'],
            'description' => 'Sankashti Chaturthi of Magha/Phalguna month',
            'deity' => 'Ganesha',
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Generic Sankashti Chaturthi section',
                    'supports' => 'The named monthly Sankashti identity inherits the generic moonrise-vyapini Sankashti Chaturthi rule.',
                ],
            ],
        ],
        'Bhalachandra Sankashti Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Bhalachandra Sankashti Chaturthi',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'sankashti_truth_table' => true,
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Chaitra',
            'aliases' => ['Sankashti Chaturthi', 'Bhalachandra Sankashti'],
            'description' => 'Sankashti Chaturthi of Phalguna/Chaitra month',
            'deity' => 'Ganesha',
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Generic Sankashti Chaturthi section',
                    'supports' => 'The named monthly Sankashti identity inherits the generic moonrise-vyapini Sankashti Chaturthi rule.',
                ],
            ],
        ],
        'Vikata Sankashti Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Vikata Sankashti Chaturthi',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'sankashti_truth_table' => true,
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Vaishakha',
            'aliases' => ['Sankashti Chaturthi', 'Vikata Sankashti'],
            'description' => 'Sankashti Chaturthi of Chaitra/Vaishakha month',
            'deity' => 'Ganesha',
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Generic Sankashti Chaturthi section',
                    'supports' => 'The named monthly Sankashti identity inherits the generic moonrise-vyapini Sankashti Chaturthi rule.',
                ],
            ],
        ],
        'Ekadanta Sankashti Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Ekadanta Sankashti Chaturthi',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'sankashti_truth_table' => true,
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Jyeshtha',
            'aliases' => ['Sankashti Chaturthi', 'Ekadanta Sankashti'],
            'description' => 'Sankashti Chaturthi of Vaishakha/Jyeshtha month',
            'deity' => 'Ganesha',
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Generic Sankashti Chaturthi section',
                    'supports' => 'The named monthly Sankashti identity inherits the generic moonrise-vyapini Sankashti Chaturthi rule.',
                ],
            ],
        ],
        'Krishnapingala Sankashti Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Krishnapingala Sankashti Chaturthi',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'sankashti_truth_table' => true,
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Ashadha',
            'aliases' => ['Sankashti Chaturthi', 'Krishnapingala Sankashti'],
            'description' => 'Sankashti Chaturthi of Jyeshtha/Ashadha month',
            'deity' => 'Ganesha',
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Generic Sankashti Chaturthi section',
                    'supports' => 'The named monthly Sankashti identity inherits the generic moonrise-vyapini Sankashti Chaturthi rule.',
                ],
            ],
        ],
        'Gajanana Sankashti Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Gajanana Sankashti Chaturthi',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'sankashti_truth_table' => true,
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Shravana',
            'aliases' => ['Sankashti Chaturthi', 'Gajanana Sankashti'],
            'description' => 'Sankashti Chaturthi of Ashadha/Shravana month',
            'deity' => 'Ganesha',
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Generic Sankashti Chaturthi section',
                    'supports' => 'The named monthly Sankashti identity inherits the generic moonrise-vyapini Sankashti Chaturthi rule.',
                ],
            ],
        ],
        'Heramba Sankashti Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Heramba Sankashti Chaturthi',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'sankashti_truth_table' => true,
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Sankashti Chaturthi', 'Heramba Sankashti'],
            'description' => 'Sankashti Chaturthi of Shravana/Bhadrapada month',
            'deity' => 'Ganesha',
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Generic Sankashti Chaturthi section',
                    'supports' => 'The named monthly Sankashti identity inherits the generic moonrise-vyapini Sankashti Chaturthi rule.',
                ],
            ],
        ],
        'Vakratunda Sankashti Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Vakratunda Sankashti Chaturthi',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'sankashti_truth_table' => true,
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Sankashti Chaturthi', 'Vakratunda Sankashti'],
            'description' => 'Sankashti Chaturthi of Ashvina/Kartika month',
            'deity' => 'Ganesha',
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Generic Sankashti Chaturthi section',
                    'supports' => 'The named monthly Sankashti identity inherits the generic moonrise-vyapini Sankashti Chaturthi rule.',
                ],
            ],
        ],
        'Ganadhipa Sankashti Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Ganadhipa Sankashti Chaturthi',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'sankashti_truth_table' => true,
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Margashirsha',
            'aliases' => ['Sankashti Chaturthi', 'Ganadhipa Sankashti'],
            'description' => 'Sankashti Chaturthi of Kartika/Margashirsha month',
            'deity' => 'Ganesha',
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Generic Sankashti Chaturthi section',
                    'supports' => 'The named monthly Sankashti identity inherits the generic moonrise-vyapini Sankashti Chaturthi rule.',
                ],
            ],
        ],
        'Akhuratha Sankashti Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Akhuratha Sankashti Chaturthi',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'sankashti_truth_table' => true,
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Pausha',
            'aliases' => ['Sankashti Chaturthi', 'Akhuratha Sankashti'],
            'description' => 'Sankashti Chaturthi of Margashirsha/Pausha month',
            'deity' => 'Ganesha',
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Generic Sankashti Chaturthi section',
                    'supports' => 'The named monthly Sankashti identity inherits the generic moonrise-vyapini Sankashti Chaturthi rule.',
                ],
            ],
        ],
        'Vibhuvana Sankashti Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Vibhuvana Sankashti Chaturthi',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'sankashti_truth_table' => true,
            'paksha' => 'Krishna',
            'tithi' => 4,
            'allow_adhika' => true,
            'adhika_only' => true,
            'aliases' => ['Vibhuvana Sankashti', 'Sankashti Chaturthi'],
            'description' => 'Sankashti Chaturthi falling during an Adhika Maas (intercalary month)',
            'deity' => 'Ganesha',
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Generic Sankashti Chaturthi section',
                    'supports' => 'The Adhika Maas Sankashti identity inherits the generic moonrise-vyapini Sankashti Chaturthi rule.',
                ],
            ],
        ],
        'Vinayaka Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Ganesha Jayanti', 'Ganesh Chaturthi', 'Siddhivinayaka Chaturthi'],
            'description' => 'Monthly fast dedicated to Lord Ganesha during the waxing moon',
            'deity' => 'Ganesha',
            'regions' => ['Pan-India'],
            'karmakala_type' => 'madhyahna',
        ],
        'Pradosh Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'pradosh_vrat',
            'paksha' => 'Both',
            'tithi' => 13,
            'description' => 'Bi-monthly evening fasting dedicated to Lord Shiva and Parvati',
            'deity' => 'Shiva/Parvati',
            'regions' => ['Pan-India'],
            'fasting' => true,
            'allow_adhika' => true,
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'pradosh_truth_table' => true,
            'weekday_classifier_after_resolution' => true,
            'document_status' => 'generic_family_rule_weekday_classifier',
        ],
        'Vratni Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'description' => 'Monthly Purnima fast selected by the 18 daytime-ghadi Chaturdashi condition',
            'deity' => 'Vishnu/Chandra',
            'regions' => ['Pan-India'],
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'purnima_vrat_18_ghadi_rule' => true,
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'locator' => 'Monthly Purnima Vrat rule',
                    'supports' => 'Generic Vratni Purnima follows the 18 daytime-ghadi Chaturdashi condition rather than plain sunrise-only Purnima.',
                ],
            ],
        ],
        'Masik Shivaratri' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 14,
            'description' => 'Monthly night fasting dedicated to Lord Shiva',
            'deity' => 'Shiva',
            'regions' => ['Pan-India'],
            'fasting' => true,
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
            'mahashivaratri_truth_table' => true,
            'excluded_months_amanta' => ['Magha'],
            'excluded_months_purnimanta' => ['Phalguna'],
        ],
        'Lakshmi Puja (Deepavali)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Diwali Lakshmi Puja'],
            'description' => 'Deepavali night Lakshmi Puja in Pradosha window',
            'deity' => 'Lakshmi',
            'regions' => ['Pan-India'],
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
        ],

        'Nabanna Utsav' => [
            'type' => 'fixed_date',
            'month' => 11,
            'day' => 15,
            'description' => 'Bengal new-rice thanksgiving harvest observance',
            'deity' => 'Lakshmi',
            'regions' => ['West Bengal'],
        ],
        'Rongali Bihu Day 1' => [
            'type' => 'solar_sankranti',
            'rashi' => 0,
            'description' => 'First day of Rongali/Bohag Bihu in Assam; dedicated to cattle worship and agrarian renewal',
            'deity' => 'Gau Mata/Surya',
            'regions' => ['Assam'],
            'aliases' => ['Bohag Bihu', 'Bohag Bihu Day 1', 'Goru Bihu'],
        ],
        'Rongali Bihu Day 2' => [
            'type' => 'day_after',
            'parent_festival' => 'Rongali Bihu Day 1',
            'days_after' => 1,
            'description' => 'Second day of Rongali/Bohag Bihu; Assamese New Year observance with family blessings and new clothes',
            'deity' => 'Household Deities',
            'regions' => ['Assam'],
            'aliases' => ['Bohag Bihu Day 2', 'Manuh Bihu'],
        ],
        'Rongali Bihu Day 3' => [
            'type' => 'day_after',
            'parent_festival' => 'Rongali Bihu Day 1',
            'days_after' => 2,
            'description' => 'Third day of Rongali/Bohag Bihu; worship of household and village deities',
            'deity' => 'Gosain/Household Deities',
            'regions' => ['Assam'],
            'aliases' => ['Bohag Bihu Day 3', 'Gosai Bihu'],
        ],
        'Rongali Bihu Day 4' => [
            'type' => 'day_after',
            'parent_festival' => 'Rongali Bihu Day 1',
            'days_after' => 3,
            'description' => 'Fourth day of Rongali/Bohag Bihu; visiting relatives and strengthening family ties',
            'deity' => 'Family Ancestors',
            'regions' => ['Assam'],
            'aliases' => ['Bohag Bihu Day 4', 'Kutum Bihu'],
        ],
        'Rongali Bihu Day 5' => [
            'type' => 'day_after',
            'parent_festival' => 'Rongali Bihu Day 1',
            'days_after' => 4,
            'description' => 'Fifth day of Rongali/Bohag Bihu; day of affection, music, dance, and social bonding',
            'deity' => 'Kamadeva',
            'regions' => ['Assam'],
            'aliases' => ['Bohag Bihu Day 5', 'Senehi Bihu'],
        ],
        'Rongali Bihu Day 6' => [
            'type' => 'day_after',
            'parent_festival' => 'Rongali Bihu Day 1',
            'days_after' => 5,
            'description' => 'Sixth day of Rongali/Bohag Bihu; fairs, cultural performances, and community gatherings',
            'deity' => 'Cultural Deities',
            'regions' => ['Assam'],
            'aliases' => ['Bohag Bihu Day 6', 'Mela Bihu'],
        ],
        'Rongali Bihu Day 7' => [
            'type' => 'day_after',
            'parent_festival' => 'Rongali Bihu Day 1',
            'days_after' => 6,
            'description' => 'Seventh and closing day of Rongali/Bohag Bihu',
            'deity' => 'Cultural Traditions',
            'regions' => ['Assam'],
            'aliases' => ['Bohag Bihu Day 7', 'Chera Bihu'],
        ],

        'Shastriji Maharaj Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Birth anniversary of Shastriji Maharaj, founder of BAPS; coincides with Vasant Panchami',
            'deity' => 'Swaminarayan',
        ],

        'Pramukh Varni Din' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Day when Pramukh Swami Maharaj was appointed administrative president of BAPS (1950)',
            'deity' => 'Swaminarayan',
        ],

        'Ashadhi Bij' => [
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

        'Ravechi Mata Fair' => [
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

        'Tarnetar Fair' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => "Saurashtra's famous matchmaking fair at Trinetreshwar Mahadev Temple; Bhadarva Sud 4-6",
            'deity' => 'Shiva',
            'regions' => ['Saurashtra', 'Gujarat'],
        ],
        'Tarnetar Fair Day 2' => [
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
        'Tarnetar Fair Day 3' => [
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

        'Mota Yaksh Fair (Jakh Bahotera)' => [
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
        'Mota Yaksh Fair Day 2' => [
            'type' => 'day_after',
            'parent_festival' => 'Mota Yaksh Fair (Jakh Bahotera)',
            'days_after' => 1,
            'description' => 'Second day of Mota Yaksh Fair at Kakadbhit (Bhadarva Vad 13)',
            'deity' => '72 Yakshas',
            'regions' => ['Kutch', 'Gujarat'],
        ],
        'Mota Yaksh Fair Day 3' => [
            'type' => 'day_after',
            'parent_festival' => 'Mota Yaksh Fair (Jakh Bahotera)',
            'days_after' => 2,
            'description' => 'Third day of Mota Yaksh Fair at Kakadbhit (Bhadarva Vad 14)',
            'deity' => '72 Yakshas',
            'regions' => ['Kutch', 'Gujarat'],
        ],

        'Dada Mekan Fair (Dhrang Mela)' => [
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

        'Rang Panchami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 5,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Chaitra',
            'aliases' => ['Rangpanchami', 'Dev Holi', 'Dev Panchami', 'Ranga Panchami'],
            'description' => 'Rang Panchami is the joyful fifth-day continuation of Holi, celebrated with colours and devotional remembrance of Radha-Krishna.',
            'deity' => 'Krishna / Radha',
            'regions' => ['North India', 'Maharashtra', 'Gujarat', 'Madhya Pradesh', 'Rajasthan'],
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'require_sunrise_vyapini' => true,
            'ritual_profile' => 'rang_panchami_gulal',
        ],

        'Chaitra (Vasant) Navaratri Day 1 (Shailaputri Puja)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'aliases' => ['Chaitra Navratri Ghatasthapana'],
            'description' => 'The first day of Chaitra Navaratri begins the spring worship of Devi with Ghatasthapana and Shailaputri Puja.',
            'deity' => 'Durga/Shailaputri',
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'require_sunrise_vyapini' => true,
            'navratri_pratipada_table' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'ghatasthapana_preference' => 'first_one_third_of_day_then_abhijit',
            'night_prohibited' => true,
            'navratri_type' => 'chaitra',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Chaitra (Vasant) Navaratri Day 2 (Brahmacharini Puja)' => [
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
        'Chaitra (Vasant) Navaratri Day 3 (Chandraghanta Puja)' => [
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
        'Chaitra (Vasant) Navaratri Day 4 (Kushmanda Puja)' => [
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
        'Chaitra (Vasant) Navaratri Day 5 (Skandamata Puja)' => [
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
        'Chaitra (Vasant) Navaratri Day 6 (Katyayani Puja)' => [
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
        'Chaitra (Vasant) Navaratri Day 7 (Kalaratri Puja)' => [
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
        'Chaitra (Vasant) Navaratri Day 8 (Mahagauri Puja)' => [
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
        'Chaitra (Vasant) Navaratri Day 9 (Siddhidatri Puja)' => [
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
        'Ashvina Sharad Navaratri Day 1 (Shailaputri Puja)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'aliases' => ['Sharad Navratri Ghatasthapana'],
            'description' => 'The first day of Sharad Navaratri begins the autumn worship of Devi with Ghatasthapana and Shailaputri Puja.',
            'deity' => 'Durga/Shailaputri',
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'require_sunrise_vyapini' => true,
            'navratri_pratipada_table' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'ghatasthapana_preference' => 'first_one_third_of_day_then_abhijit',
            'night_prohibited' => true,
            'navratri_type' => 'sharad',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Ashvina Sharad Navaratri Day 2 (Brahmacharini Puja)' => [
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
        'Ashvina Sharad Navaratri Day 3 (Chandraghanta Puja)' => [
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
        'Ashvina Sharad Navaratri Day 4 (Kushmanda Puja)' => [
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
        'Ashvina Sharad Navaratri Day 5 (Skandamata Puja)' => [
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
        'Ashvina Sharad Navaratri Day 6 (Katyayani Puja)' => [
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
        'Ashvina Sharad Navaratri Day 7 (Kalaratri Puja)' => [
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
        'Durga Ashtami (Mahagauri Puja)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'aliases' => ['Durga Ashtami'],
            'description' => 'Ashvina Sharad Navaratri - Worship of Mahagauri (The Great White One) / Maha Ashtami',
            'deity' => 'Durga/Mahagauri',
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
            'prefer_first_karmakala' => true,
            'navratri_type' => 'sharad',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Maha Navami (Siddhidatri Puja)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'aliases' => ['Maha Navami'],
            'description' => 'Ashvina Sharad Navaratri - Worship of Siddhidatri (Giver of Supernatural Powers) / Maha Navami',
            'deity' => 'Durga/Siddhidatri',
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
            'prefer_first_karmakala' => true,
            'navratri_type' => 'sharad',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
        ],
        'Ashadha Gupt Navaratri Day 1 (Ghatasthapana)' => [
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
        'Ashadha Gupt Navaratri Day 2' => [
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
        'Ashadha Gupt Navaratri Day 3' => [
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
        'Ashadha Gupt Navaratri Day 4' => [
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
        'Ashadha Gupt Navaratri Day 5' => [
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
        'Ashadha Gupt Navaratri Day 6' => [
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
        'Ashadha Gupt Navaratri Day 7' => [
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
        'Ashadha Gupt Navaratri Day 8' => [
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
        'Ashadha Gupt Navaratri Day 9' => [
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
            'prefer_growth_before_score' => true,
            'navratri_type' => 'ashadha_gupta',
            'worship_profile' => 'gupta_mahavidya_custom',
            'deity_schedule_source' => 'lineage_map_or_user_custom_map',
        ],
        'Ashadha Gupt Navaratri Parana (Dashami)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Parana for Ashadha Gupta Navaratri is performed after the completion of the Navami observance.',
            'deity' => 'Devi',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'ashadha_gupta',
            'parana_rule' => 'navami_over_and_dashami_prevails',
        ],
        'Magha Gupt Navaratri Day 1 (Ghatasthapana)' => [
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
        'Magha Gupt Navaratri Day 2' => [
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
        'Magha Gupt Navaratri Day 3' => [
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
        'Magha Gupt Navaratri Day 4' => [
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
        'Magha Gupt Navaratri Day 5' => [
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
        'Magha Gupt Navaratri Day 6' => [
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
        'Magha Gupt Navaratri Day 7' => [
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
        'Magha Gupt Navaratri Day 8' => [
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
        'Magha Gupt Navaratri Day 9' => [
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
        'Magha Gupt Navaratri Parana (Dashami)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Parana for Magha Gupta Navaratri is performed after the completion of the Navami observance.',
            'deity' => 'Devi',
            'karmakala_type' => 'sunrise',
            'navratri_type' => 'magha_gupta',
            'parana_rule' => 'navami_over_and_dashami_prevails',
        ],
        'Papmochani Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'ekadashi_vrat',
            'name_classifier' => 'Papmochani Ekadashi',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Chaitra',
            'aliases' => ['Papavimocani Ekadashi'],
            'description' => 'Fasting for Papmochani Ekadashi (Liberation from Sins)',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'arunodaya',
            'strict_dashami_vedha' => true,
            'ekadashi_nirnay_table' => true,
        ],
        'Ugadi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'aliases' => ['Gudi Padwa', 'Samvatsara Prarambha', 'Chaitra Samvatsara Prarambh'],
            'description' => 'Chaitra Samvatsara Prarambh marks the beginning of the lunar year and is observed with Brahma worship, new-year rites and auspicious household traditions.',
            'deity' => 'Brahma',
            'karmakala_type' => 'sunrise',
            'allow_adhika' => true,
            'require_sunrise_vyapini' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'strict_karmakala' => true,
            'resolution_policy' => [
                'samvatsar_prarambh' => true,
                'adhika_vs_shuddha_distinction' => true,
            ],
            'calendar_rule' => [
                'tithi' => 1,
                'month_amanta' => 'Chaitra',
                'month_purnimanta' => 'Chaitra',
                'paksha' => 'Shukla',
            ],
            'astronomy_rule' => [
                'vyapini' => 'sunrise',
                'anchor' => 'chandra_samvatsara_prarambh',
            ],
            'ritual_layers' => [
                'samvatsara_arambh',
                'gudi_utsav',
                'brahma_puja',
                'new_year_rituals',
                'varshesh_consideration',
            ],
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Gangaur' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'aliases' => ['Gauri Tritiya', 'Gauri Teej', 'Saubhagya Gauri Tritiya'],
            'description' => "Gauri Tritiya is a women's vrata dedicated to Gauri or Parvati for marital auspiciousness and family wellbeing.",
            'deity' => 'Gauri / Parvati',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'vriddhi_preference' => 'last',
            'kshaya_preference' => 'first',
            'gauri_tritiya_parayuta_table' => true,
            'resolution_policy' => [
                'chaturthi_viddha_parayuta' => true,
                'vriddhi_preference' => 'last',
                'kshaya_preference' => 'first',
            ],
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'locator' => 'Gauri Tritiya section',
                    'supports' => 'Gauri Tritiya is sunrise-vyapini, but parayuta or Chaturthi-yukta Tritiya is preferred when available.',
                ],
            ],
        ],
        'Yamuna Chhath' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Descent of Goddess Yamuna',
            'deity' => 'Yamuna',
            'fasting' => true,
            'require_sunrise_vyapini' => true,
            'location_sensitive' => true,
        ],
        'Chaiti Chhath' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Summer Chhath festival',
            'deity' => 'Surya',
        ],
        'Rama Navami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'rama_navami',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Birth anniversary of Lord Rama, seventh avatar of Vishnu',
            'deity' => 'Rama',
            'fasting' => true,
            // SJ 4.60.24–27: madhyahna-vyapini, Ashtami-vedha-free; both/neither → para (second).
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'require_madhyahna_vyapini' => true,
            'ashtami_viddha_rejection' => true,
            'previous_tithi_vedha_tolerated' => true,
            'kshaya_accept_previous_tithi_vedha' => true,
            // Historical appearance yoga only; date rule does not require Punarvasu (4.60.26).
            'nakshatra' => 'Punarvasu',
            'prefer_nakshatra' => false,
            'prefer_nakshatra_window' => false,
            'ritual_profile' => 'ramnavami_satsangi',
            'require_karmakala_match' => true,
            'vriddhi_preference' => 'last',
            'kshaya_preference' => 'first',
            'dual_day_rule' => 'both_or_neither_madhyahna_choose_para_navami_reject_ashtami_vedha',
            'source_refs' => ['Satsangi Jeevan 4.60'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.60.24-27',
                    'supports' => 'Take Ashtami-vedha-free madhyahna-vyapini Navami; if both or neither day is madhyahna-vyapini, take the later Navami; kshaya accepts purva-yuk Ashtami-joined day.',
                ],
            ],
        ],
        'Swaminarayan Jayanti (Hari-Nom)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Swaminarayan Jayanti celebrates the divine appearance of Bhagwan Swaminarayan on Chaitra Shukla Navami.',
            'deity' => 'Swaminarayan',
            'source_refs' => ['Satsangi Jeevan 5.61'],
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'tradition_profile' => 'Swaminarayan/Satsangi Jeevan sunrise-vyapini Navami',
            'ritual_profile' => 'swaminarayan_jayanti_night',
        ],
        'Kamada Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'aliases' => ['Kamana Ekadashi', 'Phalda Ekadashi'],
            'description' => 'Fasting for Kamada Ekadashi (Fulfiller of Desires)',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Mahavir Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 13,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Birth anniversary of Lord Mahavira, 24th Tirthankara; National Holiday',
            'deity' => 'Mahavira',
            'regions' => ['Pan-India'],
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
        ],
        'Varuthini Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Vaishakha',
            'aliases' => ['Baruthani Ekadashi'],
            'description' => 'Fasting for Varuthini Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Parashurama Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth celebration of Lord Parashurama with tradition-aware observance routing',
            'deity' => 'Parashurama',
            'strict_karmakala' => true,
            'traditions' => [
                // SJ 4.60.46: date = purvahna-vyapini Tritiya; both days → para/second.
                // SJ 4.60.47: ritual mahapuja is at madhyahna.
                'satsangi' => [
                    'variant_name' => 'Parashurama Jayanti',
                    'aliases' => ['Parashurama Jayanti (Swaminarayan/Satsangi)', 'Parashuram Jayanti'],
                    'karmakala_type' => 'purvahna',
                    'ritual_kala_type' => 'madhyahna',
                    'require_karmakala_match' => true,
                    'vriddhi_preference' => 'last',
                    'kshaya_preference' => 'first',
                    'dual_day_rule' => 'both_purvahna_vyapini_choose_para_tritiya',
                    'ritual_profile' => 'parashurama_jayanti_satsangi',
                    'source_refs' => ['Satsangi Jeevan 4.60'],
                    'source_evidence' => [
                        [
                            'kind' => 'date_rule',
                            'source' => 'Satsangi Jeevan',
                            'locator' => '4.60.46',
                            'supports' => 'Take purvahna-vyapini Vaishakha Shukla Tritiya; when both days have purvahna vyapti, take the later Tritiya.',
                        ],
                        [
                            'kind' => 'ritual_rule',
                            'source' => 'Satsangi Jeevan',
                            'locator' => '4.60.47',
                            'supports' => 'Mahapuja of Vasudeva as Parashurama is performed at madhyahna; meal follows worship.',
                        ],
                    ],
                ],
                'pradosha' => [
                    'variant_name' => 'Parashurama Jayanti (Pradosha Tradition)',
                    'fasting' => true,
                    'karmakala_type' => 'pradosha',
                    'vriddhi_preference' => 'last',
                ],
            ],
        ],
        // SJ 4.60.50–52: Chandan Yatra begins on the Akshaya Tritiya / Parashurama Jayanti day
        // and continues daily until Snanyatra. Date is linked, not independently calculated.
        'Chandan Yatra Begins' => [
            'type' => 'day_after',
            'parent_festival' => 'Parashurama Jayanti',
            'days_after' => 0,
            'aliases' => ['Chandan Yatra', 'Chandanotsav Begins'],
            'description' => 'Chandan Yatra begins on the Parashurama Jayanti / Akshaya Tritiya day and continues with daily chandan-vastra and cooling worship until Snanyatra.',
            'deity' => 'Krishna / Vasudeva',
            'sect_specific' => true,
            'ritual_profile' => 'chandan_yatra_satsangi',
            'ritual_layers' => ['chandan_vastra', 'ushiira_fan', 'aamras_naivedya'],
            'resolution_policy' => [
                'linked_period_start' => true,
                'period_end_festival' => 'Snanyatra',
            ],
            'source_refs' => ['Satsangi Jeevan 4.60'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.60.50-52',
                    'supports' => 'From this day (Akshaya Tritiya / Parashurama Jayanti) until Snanyatra, daily chandan-scented garments and cooling services are offered; date is not calculated separately.',
                ],
            ],
        ],
        'Akshaya Tritiya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'aliases' => ['Akshaya Tritiya (Lakshmi-Narayana)'],
            'description' => 'Akshaya Tritiya is an auspicious Vaishakha Shukla Tritiya associated with imperishable merit, charity, worship and the beginning of Chandan Yatra.',
            'deity' => 'Vishnu/Lakshmi',
            'karmakala_type' => 'purvahna',
            'akshaya_tritiya_purvahna_table' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'resolution_policy' => [
                'purvahna_vyapini' => true,
                'both_days_purvahna_second_day_muhurta_threshold' => 3,
                'vriddhi_preference' => 'first',
                'kshaya_preference' => 'first',
            ],
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu', 'Satsangi Jeevan 4.60'],
        ],
        'Adi Shankaracharya Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth anniversary of Adi Shankaracharya',
            'deity' => 'Shiva/Shankaracharya',
        ],
        'Ganga Saptami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'aliases' => ['Gangotpatte', 'Gangotpatti'],
            'description' => 'Ganga Saptami, also called Gangotpatti, commemorates the sacred appearance of Mother Ganga and is observed with worship of the river goddess.',
            'deity' => 'Ganga',
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'resolution_policy' => [
                'madhyahna_vyapini' => true,
            ],
            'calendar_rule' => [
                'tithi' => 7,
                'month_amanta' => 'Vaishakha',
                'month_purnimanta' => 'Vaishakha',
                'paksha' => 'Shukla',
            ],
            'astronomy_rule' => [
                'vyapini' => 'madhyahna',
                'kala' => 'madhyahna',
            ],
            'ritual_layers' => [
                'ganga_puja',
                'snan',
                'gangavataran_remembrance',
            ],
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Sita Navami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth anniversary of Goddess Sita',
            'deity' => 'Sita',
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
        ],
        'Mohini Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'aliases' => ['Laxmi Narayan Ekadashi'],
            'description' => 'Fasting for Mohini Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Narasimha Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'narasimha_jayanti',
            'paksha' => 'Shukla',
            'tithi' => 14,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Appearance day of Lord Narasimha',
            'deity' => 'Narasimha',
            'fasting' => true,
            // SJ 4.60.54–55: shuddha Chaturdashi free of Trayodashi vedha; two pure → first; kshaya accepts viddha; ritual at pradosha.
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'require_karmakala_match' => true,
            'trayodashi_viddha_rejection' => true,
            'kshaya_accept_previous_tithi_vedha' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'dual_day_rule' => 'two_shuddha_chaturdashi_choose_first_kshaya_accept_trayodashi_viddha',
            'nakshatra' => 'Swati',
            // Historical appearance yoga (Monday + Swati); date rule is shuddha Chaturdashi (4.60.54).
            'prefer_nakshatra' => false,
            'prefer_weekdays' => [1],
            'ritual_profile' => 'narasimha_jayanti',
            'fasting_guidance_key' => 'capable_full_fast_incapable_falahar_no_grains',
            'source_refs' => ['Satsangi Jeevan 4.60'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.60.53-54',
                    'supports' => 'Take Trayodashi-vedha-free shuddha Vaishakha Shukla Chaturdashi; if two pure days, take the first; on kshaya accept Trayodashi-viddha.',
                ],
                [
                    'kind' => 'ritual_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.60.55',
                    'supports' => 'Worship of Krishna as Narasimha is performed at pradosha/evening; grain meal is not taken that day.',
                ],
            ],
        ],
        'Narsinh Mehta Janma Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth anniversary of Adi Kavi Narsinh Mehta',
            'deity' => 'Krishna/Narsinh Mehta',
        ],
        'Apara Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Jyeshtha',
            'aliases' => ['Achala Ekadashi'],
            'description' => 'Fasting for Apara Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Yogi Maharaj Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Birth of Brahmaswarup Yogi Maharaj',
            'deity' => 'Swaminarayan',
        ],
        'Jamai Shashti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Bengali festival dedicated to son-in-laws',
            'deity' => 'Shashti',
        ],
        'Mahesh Navami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Worship of Lord Shiva by Maheshwari community',
            'deity' => 'Shiva',
        ],
        'Ganga Dussehra' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'ganga_dussehra',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Ganga Dussehra commemorates the descent of Mother Ganga to earth and is observed for purification, worship and sacred bathing.',
            'deity' => 'Ganga',
            // SJ 4.61.1–3: Jyeshtha Shukla Dashami; dual-day choose Hasta yoga day; adhika month preferred over nija.
            'karmakala_type' => 'sunrise',
            'ritual_kala_type' => 'madhyahna',
            'nakshatra' => 'Hasta',
            'prefer_nakshatra' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'dual_day_rule' => 'choose_dashami_day_having_hasta_nakshatra',
            'allow_adhika' => true,
            'allows_adhika' => true,
            'prefer_adhika' => true,
            'aliases' => ['Gangavatar', 'Ganga Avataran', 'Dasahara', 'Ganga Dashahara'],
            'ritual_profile' => 'gangavatar_dasahara_satsangi',
            'source_refs' => ['Satsangi Jeevan 4.61'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.61.1-3',
                    'supports' => 'Jyeshtha Shukla Dashami; when two Dashami days are possible, choose the day with Hasta nakshatra; in adhika Jyeshtha celebrate in the adhika month, not the later nija month.',
                ],
                [
                    'kind' => 'ritual_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.61.6-7',
                    'supports' => 'Ganga puja is performed in madhyahna; meal follows after the worship.',
                ],
            ],
        ],
        'Nirjala Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'aliases' => ['Pandava Ekadashi', 'Bhima Ekadashi', 'Bhimseni Ekadashi'],
            'description' => 'Toughest Ekadashi vrat (observed without water) / Gayatri Jayanti',
            'deity' => 'Vishnu/Gayatri',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Vat Purnima' => [
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
        'Yogini Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Ashadha',
            'aliases' => ['Anasara Ekadashi', 'Khalilagi Ekadashi'],
            'description' => 'Fasting for Yogini Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'kshaya_preference' => 'last',
        ],
        'Jagannath Rath Yatra' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => "Rath Yatra celebrates Lord Jagannath's chariot procession with Balabhadra and Subhadra.",
            'deity' => 'Jagannath',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'allow_adhika' => true,
        ],
        'Bahuda Yatra' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => "Return journey of Lord Jagannath's chariots to the main temple",
            'deity' => 'Jagannath',
            'regions' => ['Odisha'],
            'karmakala_type' => 'sunrise',
        ],
        'Devshayani Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'aliases' => ['Shayani Ekadashi', 'Ashadhi Ekadashi', 'Harishayani Ekadashi', 'Prathama Ekadashi', 'Toli Ekadashi', 'Devpodhi Ekadashi'],
            'description' => 'Chaturmas begins, Lord Vishnu sleeps',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Kachchhi Halari Ashadhi Varsharambh' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'aliases' => ['Kachchhi Nutan Varsh', 'Halari Nutan Varsh', 'Ashadhi Beej Varsharambh'],
            'description' => 'Kachchhi and Halari Ashadhi Varsharambh marks the traditional new-year beginning for these regional communities.',
            'deity' => 'Lakshmi/Ganesha',
            'regions' => ['Kutch', 'Halar', 'Gujarat'],
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'resolution_policy' => [
                'new_year_ashadha' => true,
            ],
            'source_evidence' => [
                [
                    'kind' => 'heading_only',
                    'locator' => 'Kachchhi and Halari Ashadhi Varsharambh heading',
                    'supports' => 'The observance identity and tithi are listed; the sunrise-vyapini handling is inherited from the general Pratipada/new-year style resolver.',
                ],
            ],
        ],
        'Pandharpur Yatra' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'aliases' => ['Pandharpur Wari', 'Ashadhi Wari', 'Ashadhi Ekadashi Yatra'],
            'description' => 'Pandharpur Yatra, also known as Ashadhi Wari, is the devotional pilgrimage to Lord Vithoba of Pandharpur.',
            'deity' => 'Vitthala/Vishnu',
            'regions' => ['Maharashtra', 'Pandharpur'],
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Pandharpur Yatra heading and Ekadashi Vrat section',
                    'supports' => 'The heading identifies the observance with Ashadha Shukla Ekadashi; date selection inherits Ekadashi vrata logic.',
                ],
            ],
        ],
        'Shaka Vrata Begins' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Shaka Vrata begins the Chaturmas discipline of abstaining from leafy vegetables.',
            'deity' => 'Vishnu',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'fasting' => true,
            'fasting_guidance_key' => 'chaturmas_no_shak',
            'resolution_policy' => [
                'chaturmas_start' => true,
                'prohibition' => 'leafy vegetables',
                'end_with_chaturmas' => true,
            ],
            'chaturmas_vrata' => true,
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Shaka Vrata heading and Ekadashi Vrat section',
                    'supports' => 'The heading identifies the observance with Ashadha Shukla Ekadashi; date selection inherits Ekadashi vrata logic.',
                ],
            ],
        ],
        'Dahi Vrata Begins' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Dahi Vrata begins the Chaturmas discipline of abstaining from curd or yogurt.',
            'deity' => 'Vishnu',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'fasting' => true,
            'chaturmas_vrata' => true,
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Dahi Vrat heading and Ekadashi Vrat section',
                    'supports' => 'The heading identifies the observance with Shravana Shukla Ekadashi; date selection inherits Ekadashi vrata logic.',
                ],
            ],
        ],
        'Dudh Vrata Begins' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Dudh Vrata begins the Chaturmas discipline of abstaining from milk.',
            'deity' => 'Vishnu',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'fasting' => true,
            'chaturmas_vrata' => true,
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Dudh Vrat heading and Ekadashi Vrat section',
                    'supports' => 'The heading identifies the observance with Bhadrapada Shukla Ekadashi; date selection inherits Ekadashi vrata logic.',
                ],
            ],
        ],
        'Dwidala Vrata Begins' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'aliases' => ['Dwidal Vrata Begins'],
            'description' => 'Dwidala Vrata begins the Chaturmas discipline of abstaining from pulses and split legumes.',
            'deity' => 'Vishnu',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'fasting' => true,
            'chaturmas_vrata' => true,
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Dwidala Vrat heading and Ekadashi Vrat section',
                    'supports' => 'The heading identifies the observance with Ashvina Shukla Ekadashi; date selection inherits Ekadashi vrata logic.',
                ],
            ],
        ],
        'Gauri Vrat (Molakat) Begins' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => '5-day fast by young girls (without salt)',
            'deity' => 'Gauri/Parvati',
        ],
        'Jaya Parvati Vrat Begins' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 13,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => '5-day fast for marital bliss and good husband',
            'deity' => 'Jaya/Parvati',
        ],
        'Shri Satyanarayana Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'description' => 'Satyanarayana Puja and fasting on the full moon evening',
            'deity' => 'Vishnu',
            'fasting' => true,
            'allow_adhika' => true,
            'karmakala_type' => 'sunset',
            'strict_karmakala' => true,
            'forbid_previous_tithi_at' => 'madhyahna',
        ],
        'Pausha Purnima Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Pausha',
            'aliases' => ['Paush Purnima Vrat'],
            'description' => 'Pausha Purnima fasting and upavasa observance',
            'deity' => 'Sun/Moon',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'purnima_vrat_18_ghadi_rule' => true,
        ],
        'Pausha Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Pausha',
            'aliases' => ['Shakambhari Jayanti', 'Shakambhari Purnima', 'Poshi Poonam', 'Poshi Purnima'],
            'description' => 'Auspicious full moon day for Shakambhari Jayanti and holy dip',
            'deity' => 'Sun/Moon',
            'fasting' => false,
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
        ],
        'Maghi Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'aliases' => ['Magha Purnima', 'Magha Purnima Vrat', 'Guru Ravidas Jayanti', 'Lalita Jayanti'],
            'description' => 'End of Magha snan, birth of Guru Ravidas',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'vriddhi_preference' => 'first',
        ],
        'Phalguna Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'aliases' => ['Phalguna Purnima Vrat', 'Dol Purnima', 'Chaitanya Mahaprabhu Jayanti', 'Lakshmi Jayanti', 'Vasanta Purnima', 'Gaura Purnima'],
            'description' => 'Full moon of Phalguna month',
            'deity' => 'Vishnu/Lakshmi',
            'fasting' => true,
            'karmakala_type' => 'sunset',
            'strict_karmakala' => true,
            'forbid_previous_tithi_at' => 'madhyahna',
        ],
        'Chaitra Purnima Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Chaitra Purnima fasting and upavasa observance',
            'deity' => 'Vishnu/Hanuman',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'purnima_vrat_18_ghadi_rule' => true,
        ],
        'Chaitra Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'aliases' => ['Hanuman Jayanti', 'Hanuman Jayanti (North Indian)'],
            'description' => 'Chaitra full moon day and Hanuman Jayanti in many traditions',
            'deity' => 'Hanuman',
            'fasting' => false,
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
        ],
        'Chaitri Dolotsav' => [
            // SJ 4.60.34–36: Chaitra Shukla Vimala Ekadashi; date inherits Ekadashi-vrat nirnay.
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'ekadashi_vrat',
            'name_classifier' => 'Vimala Ekadashi',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'aliases' => ['Vimala Ekadashi Dolotsav', 'Chaitra Sud 11 Vishnu Dolotsav', 'Chaitri Hindola', 'Vimala Ekadashi'],
            'description' => 'Chaitri Dolotsav is the Chaitra Shukla Vimala Ekadashi swing festival; date follows the Ekadashi-vrat rule, with two-ghadi hindola worship as the special ritual.',
            'deity' => 'Vishnu/Krishna/Radha',
            'fasting' => true,
            'karmakala_type' => 'arunodaya',
            'strict_dashami_vedha' => true,
            'ekadashi_nirnay_table' => true,
            'ritual_profile' => 'dolotsav_hindola_satsangi',
            'ritual_layers' => [
                'swing_dolotsav',
                'maha_puja',
                'two_ghadi_hindola',
                'krishna_hindola',
                'radha_vishnu_puja',
            ],
            'sect_specific' => true,
            'tradition_profile' => 'Satsangi Jeevan 4.60 Dolotsav (Chaitra Vimala Ekadashi)',
            'source_refs' => ['Satsangi Jeevan 4.60'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.60.34-36',
                    'supports' => 'Chaitra Shukla Vimala Ekadashi; speciality is two-ghadi dola worship after mahapuja; remaining method is the general Ekadashi-vrat rule.',
                ],
            ],
        ],
        'Vaishakh Snan Prarambh' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'aliases' => ['Vaishakh Snan Begins', 'Chaitra Purnima Snan Start'],
            'description' => 'Vaishakh Snan Prarambh begins the month-long Vaishakha bathing observance from Chaitra Purnima.',
            'deity' => 'Vishnu',
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'require_sunrise_vyapini' => true,
            'ritual_profile' => 'vaishakh_snan_period',
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Vaishakh Snan Samapt' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'aliases' => ['Vaishakh Snan Ends', 'Vaishakh Purnima Snan Samapt'],
            'description' => 'Vaishakh Snan Samapt marks the completion of the Vaishakha bathing observance on Vaishakha Purnima.',
            'deity' => 'Vishnu',
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'require_sunrise_vyapini' => true,
            'ritual_profile' => 'vaishakh_snan_period',
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Kartika Snan Prarambh' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'aliases' => ['Kartik Snan Begins'],
            'description' => 'Kartika Snan Prarambh begins the sacred Kartika bathing observance from Ashvina Purnima.',
            'deity' => 'Vishnu',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'ritual_profile' => 'kartik_snan_period',
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Kartika Snan Samapt' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Kartik Snan Ends'],
            'description' => 'Kartika Snan Samapt marks the completion of the Kartika bathing observance on Kartika Purnima.',
            'deity' => 'Vishnu',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'ritual_profile' => 'kartik_snan_period',
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Magha Snan Prarambh' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Pausha',
            'aliases' => ['Magha Snan Begins'],
            'description' => 'Magha Snan Prarambh begins the sacred Magha bathing observance from Pausha Purnima.',
            'deity' => 'Vishnu',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'ritual_profile' => 'magha_snan_period',
            'source_refs' => ['Satsangi Jeevan 4.19'],
        ],
        'Magha Snan Samapt' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'aliases' => ['Magha Snan Ends'],
            'description' => 'Magha Snan Samapt marks the completion of the Magha bathing observance on Magha Purnima.',
            'deity' => 'Vishnu',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'ritual_profile' => 'magha_snan_period',
            'source_refs' => ['Satsangi Jeevan 4.19'],
        ],
        'Vaishakha Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'aliases' => ['Buddha Purnima', 'Chitra Pournami', 'Vaishakha Purnima Vrat'],
            'description' => 'Vaishakha Purnima vrata associated with Buddha Purnima and related lunar observances',
            'deity' => 'Vishnu/Buddha',
            'fasting' => true,
            'karmakala_type' => 'sunset',
            'strict_karmakala' => true,
            'forbid_previous_tithi_at' => 'madhyahna',
        ],
        'Jyeshtha Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'aliases' => ['Vat Purnima'],
            'description' => 'Full moon of Jyeshtha month',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunset',
            'strict_karmakala' => true,
            'forbid_previous_tithi_at' => 'madhyahna',
        ],
        'Ashadha Purnima Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Ashadha Purnima fasting and upavasa observance',
            'deity' => 'Vyasa',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'purnima_vrat_18_ghadi_rule' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Vratni Poonam section',
                    'supports' => 'This Purnima Vrat inherits the generic Purnima-vrat 18 daytime-ghadi Chaturdashi condition; the sunrise field is only the resolver anchor for that table.',
                ],
            ],
        ],
        'Ashadha Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'aliases' => ['Guru Purnima', 'Vyasa Puja'],
            'description' => 'Full moon of Ashadha month, honoring spiritual teachers',
            'deity' => 'Vyasa',
            'fasting' => false,
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
        ],
        'Chaturmasa Begins' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'aliases' => ['Devashayana Kala Begins', 'Chaturmas Prarambh'],
            'description' => 'Beginning of Chaturmasa on Ashadha Shukla Ekadashi Vrat day',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Chaturmasa Prarambh heading under Ashadha Shukla Ekadashi Vrat',
                    'supports' => 'Chaturmasa begins with the Ashadha Shukla Ekadashi vrat observance.',
                ],
            ],
        ],
        'Shravana Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'aliases' => ['Shravana Purnima Vrat', 'Raksha Bandhan', 'Rakshabandhan', 'Rakshabandh', 'Narali Purnima', 'Hayagriva Jayanti', 'Gayatri Jayanti'],
            'description' => 'Full moon of Shravana month, including Raksha Bandhan and related regional observances',
            'deity' => 'Vishnu/Gayatri',
            'fasting' => true,
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
            'avoid_bhadra_mukha' => true,
            'prefer_bhadra_puchha' => true,
            'raksha_bandhan_truth_table' => true,
        ],
        'Bhadrapada Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Bhadrapada Purnima Vrat'],
            'description' => 'Beginning of Pitru Paksha for some traditions',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunset',
            'strict_karmakala' => true,
            'forbid_previous_tithi_at' => 'madhyahna',
        ],
        'Ashvina Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'aliases' => ['Ashwina Purnima', 'Ashwina Purnima Vrat', 'Kojagara Puja'],
            'description' => 'Harvest festival and Lakshmi worship',
            'deity' => 'Lakshmi',
            'fasting' => true,
            'karmakala_type' => 'sunset',
            'strict_karmakala' => true,
            'forbid_previous_tithi_at' => 'madhyahna',
        ],
        'Kartika Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Kartika Purnima Vrat', 'Dev Deepavali', 'Tripuri Purnima'],
            'description' => 'Full moon of Kartika month',
            'deity' => 'Shiva/Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunset',
            'strict_karmakala' => true,
            'forbid_previous_tithi_at' => 'madhyahna',
        ],
        'Chaturmasa Ends' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Devashayana Kala Ends', 'Chaturmas Samapt'],
            'description' => 'Conclusion of Chaturmasa on sunrise-vyapini Kartika Shukla Dwadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'locator' => 'Chaturmasa Samapt section',
                    'supports' => 'Chaturmasa ends on udaya-vyapini Kartika Shukla Dwadashi, with completion after Ekadashi parana.',
                ],
            ],
        ],
        'Margashirsha Purnima Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'aliases' => ['Margashirsha Purnima'],
            'description' => 'Margashirsha Purnima fasting and upavasa observance',
            'deity' => 'Vishnu/Chandra',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'purnima_vrat_18_ghadi_rule' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Vratni Poonam section',
                    'supports' => 'This generic Purnima Vrat inherits the Purnima-vrat 18 daytime-ghadi Chaturdashi condition. Dattatreya Jayanti is handled separately with Pradosha-vyapini Margashirsha Purnima.',
                ],
            ],
        ],
        'Hari Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'description' => 'Monthly Shri Hari Jayanti is the Shukla Navami remembrance of Bhagwan Swaminarayan outside the annual Chaitra celebration.',
            'deity' => 'Swaminarayan/Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'excluded_months_amanta' => ['Chaitra'],
            'excluded_months_purnimanta' => ['Chaitra'],
            'resolution_policy' => [
                'monthly_excluding_annual' => true,
                'inherit_swaminarayan_navami' => true,
            ],
            'calendar_rule' => [
                'tithi' => 9,
                'paksha' => 'Shukla',
            ],
            'astronomy_rule' => [
                'vyapini' => 'sunrise',
            ],
            'tradition_profile' => 'Monthly Shri Hari Jayanti inheriting the Swaminarayan Jayanti (Hari-Nom) sunrise-vyapini Navami rule outside Chaitra',
            'ritual_profile' => 'hari_jayanti_monthly',
            'ritual_layers' => [
                'hari_jayanti_puja',
                'swaminarayan_navami',
                'monthly_sud9',
            ],
            'source_refs' => ['Satsangi Jeevan 5.61'],
        ],
        'Naga Panchami (Telugu)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Worship of Nagas celebrated in Telugu and South Indian traditions',
            'deity' => 'Naga Devatas',
            'regions' => ['Andhra Pradesh', 'Telangana', 'Karnataka'],
            'karmakala_type' => 'sunrise',
        ],
        'Vivekananda Jayanti (Samvat)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 7,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Magha',
            'description' => 'Birth anniversary of Swami Vivekananda according to the lunar calendar',
            'deity' => 'Vivekananda',
            'karmakala_type' => 'sunrise',
        ],
        'Magha Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Phalguna',
            'aliases' => ['Mauni Amavasya'],
            'description' => 'Auspicious Amavasya in the month of Magha',
            'deity' => 'Pitrus/Vishnu',
            'karmakala_type' => 'aparahna',
        ],
        'Chaitra Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Amavasya falling in the month of Chaitra',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Vaishakha Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Jyeshtha',
            'aliases' => ['Shani Jayanti', 'Vat Savitri Vrat'],
            'description' => 'Amavasya falling in the month of Vaishakha; coincides with Shani Jayanti',
            'deity' => 'Pitrus/Shani',
            'karmakala_type' => 'aparahna',
        ],
        'Jyeshtha Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Amavasya falling in the month of Jyeshtha',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Ashadha Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Shravana',
            'aliases' => ['Deep Puja', 'Divaso'],
            'description' => 'Amavasya falling in the month of Ashadha; observed as Deep Puja in some regions',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Shravana Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Hariyali Amavasya', 'Pithori Amavasya', 'Aadi Amavasai'],
            'description' => 'Amavasya falling in the month of Shravana; celebrated as Hariyali Amavasya',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Bhadrapada Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'aliases' => ['Mahalaya Amavasya', 'Sarva Pitru Amavasya'],
            'description' => 'Amavasya falling in the month of Bhadrapada; conclusion of Pitru Paksha',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Ashwina Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Diwali', 'Deepavali'],
            'description' => 'Amavasya falling in the month of Ashvina; celebrated as Diwali',
            'deity' => 'Lakshmi/Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Kartika Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Amavasya falling in the month of Kartika',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Margashirsha Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Pausha',
            'description' => 'Amavasya falling in the month of Margashirsha',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Adhik Masik Krishna Janmashtami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'description' => 'Monthly Janmashtami occurring in Adhika Maas',
            'deity' => 'Krishna',
            'adhika_only' => true,
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
            'janmashtami_truth_table' => true,
        ],
        'Adhika Bhanu Saptami' => [
            'type' => 'tithi',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'weekday' => 0,
            'description' => 'Bhanu Saptami occurring in Adhika Maas',
            'deity' => 'Surya',
            'adhika_only' => true,
        ],
        'Adhika Chandra Darshana' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'description' => 'Chandra Darshana occurring in Adhika Maas',
            'deity' => 'Chandra',
            'adhika_only' => true,
            'karmakala_type' => 'chandra_darshana_visibility',
            'chandra_darshana_visibility' => true,
            'chandra_darshana_visibility_model' => 'classical_sthula_chandra_darshana_9_muhurta',
            'chandra_darshana_visibility_basis' => 'classical_textual_rule_sthula_chandra_darshana',
            'location_sensitive' => true,
        ],
        'Adhika Darsha Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'description' => 'Darsha Amavasya occurring in Adhika Maas',
            'deity' => 'Pitrus',
            'adhika_only' => true,
            'karmakala_type' => 'aparahna',
            'darsha_amavasya_aparahna_table' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Darsha Amavasya section',
                    'supports' => 'Adhika Darsha Amavasya inherits the generic Darsha Amavasya aparahna-vyapini rule.',
                ],
            ],
        ],
        'Adhika Kalashtami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'description' => 'Kalashtami occurring in Adhika Maas',
            'deity' => 'Bhairava',
            'adhika_only' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Adhika Masik Durgashtami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'description' => 'Masik Durgashtami is a monthly Shukla Ashtami observance dedicated to Devi and her protective grace.',
            'deity' => 'Durga',
            'adhika_only' => true,
            'karmakala_type' => 'sunrise',
            'durgashtami_paraviddha_table' => true,
            'resolution_policy' => [
                'navami_viddha_paraviddha' => true,
                'ashtami_vedha_muhurta_threshold' => 3,
                'vriddhi_preference' => 'first',
                'kshaya_preference' => 'first',
            ],
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Adhika Masik Shivaratri' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 14,
            'description' => 'Masik Shivaratri occurring in Adhika Maas',
            'deity' => 'Shiva',
            'adhika_only' => true,
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
            'mahashivaratri_truth_table' => true,
        ],
        'Adhika Purnima Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'description' => 'Purnima Vrat occurring in Adhika Maas',
            'deity' => 'Vishnu',
            'adhika_only' => true,
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'purnima_vrat_18_ghadi_rule' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Vratni Poonam section',
                    'supports' => 'Adhika Purnima Vrat inherits the generic Purnima-vrat 18 daytime-ghadi Chaturdashi condition; it is not a standalone plain sunrise rule.',
                ],
            ],
        ],
        'Adhika Skanda Sashti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'description' => 'Skanda Sashti occurring in Adhika Maas',
            'deity' => 'Skanda',
            'adhika_only' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Jyeshtha Adhika Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Purnima occurring in Jyeshtha Adhika Maas',
            'deity' => 'Vishnu',
            'adhika_only' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Phalguna Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Amavasya falling in the month of Phalguna',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
        ],
        'Adhika Ramalakshmana Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'description' => 'Ramalakshmana Dwadashi occurring in Adhika Maas',
            'deity' => 'Rama/Lakshmana',
            'adhika_only' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Adhika Krishna Ramalakshmana Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'description' => 'Krishna Ramalakshmana Dwadashi occurring in Adhika Maas',
            'deity' => 'Rama/Lakshmana',
            'adhika_only' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Akal Bodhon' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Untimely awakening of Goddess Durga (Sharad Navratri)',
            'deity' => 'Durga',
            'regions' => ['Bengal'],
            'karmakala_type' => 'pradosha',
        ],
        'Bilva Nimantran' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Invitation to Goddess Durga via the Bilva tree',
            'deity' => 'Durga',
            'regions' => ['Bengal'],
            'karmakala_type' => 'pradosha',
        ],
        'Kalparambha' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Ritual beginning of Durga Puja',
            'deity' => 'Durga',
            'regions' => ['Bengal'],
            'karmakala_type' => 'sunrise',
        ],
        'Navpatrika Puja' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Worship of nine leaves/plants representing Durga',
            'deity' => 'Durga',
            'regions' => ['Bengal'],
            'karmakala_type' => 'sunrise',
        ],
        'Sandhi Puja' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8, // Strictly occurs at junction of 8th and 9th tithi
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Most sacred window of Durga Puja at the junction of Ashtami and Navami',
            'deity' => 'Durga (Chamunda)',
            'regions' => ['Bengal'],
            'karmakala_type' => 'sunrise', // Logic for Sandhi Puja is usually exact time, but tagging on Ashtami
            'strict_karmakala' => true,
            'ritual_profile' => 'ashtami_navami_sandhi_interval',
        ],
        'Saraswati Avahan' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'nakshatra' => 'Mula',
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Invocation of Goddess Saraswati during Sharad Navratri',
            'deity' => 'Saraswati',
            'karmakala_type' => 'sunrise',
            'prefer_nakshatra' => true,
        ],
        'Saraswati Balidan' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'nakshatra' => 'Purva Ashadha',
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Offering ritual for Goddess Saraswati',
            'deity' => 'Saraswati',
            'karmakala_type' => 'sunrise',
            'prefer_nakshatra' => true,
        ],
        'Saraswati Visarjan' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'nakshatra' => 'Shravana',
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Immersion of Goddess Saraswati',
            'deity' => 'Saraswati',
            'karmakala_type' => 'sunrise',
            'prefer_nakshatra' => true,
        ],
        'Durga Balidan' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Sacrificial offering to Goddess Durga',
            'deity' => 'Durga',
            'regions' => ['Bengal'],
            'karmakala_type' => 'sunrise',
        ],
        'Durga Visarjan' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Immersion of Goddess Durga idols',
            'deity' => 'Durga',
            'regions' => ['Bengal'],
            'karmakala_type' => 'sunrise',
        ],
        'Krishna Kurma Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Magha',
            'description' => 'Krishna Paksha appearance day of Kurma avatar',
            'deity' => 'Vishnu (Kurma)',
            'karmakala_type' => 'sunrise',
        ],
        'Krishna Bhishma Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Krishna Paksha day dedicated to Bhishma Pitamaha',
            'deity' => 'Bhishma',
            'karmakala_type' => 'sunrise',
        ],
        'Krishna Narasimha Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Krishna Paksha appearance day of Narasimha avatar',
            'deity' => 'Vishnu (Narasimha)',
            'karmakala_type' => 'sunrise',
        ],
        'Krishna Vamana Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Krishna Paksha appearance day of Vamana avatar',
            'deity' => 'Vishnu (Vamana)',
            'karmakala_type' => 'sunrise',
        ],
        'Krishna Parashurama Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Krishna Paksha appearance day of Parashurama avatar',
            'deity' => 'Vishnu (Parashurama)',
            'karmakala_type' => 'sunrise',
        ],
        'Krishna Ramalakshmana Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Krishna Paksha appearance day of Rama and Lakshmana',
            'deity' => 'Rama/Lakshmana',
            'karmakala_type' => 'sunrise',
        ],
        'Krishna Vasudeva Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Shravana',
            'description' => 'Krishna Paksha appearance day of Vasudeva (Krishna)',
            'deity' => 'Krishna',
            'karmakala_type' => 'sunrise',
        ],
        'Krishna Damodara Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Krishna Paksha appearance day of Damodara (Krishna)',
            'deity' => 'Krishna',
            'karmakala_type' => 'sunrise',
        ],
        'Krishna Kalki Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Krishna Paksha appearance day of Kalki avatar',
            'deity' => 'Vishnu (Kalki)',
            'karmakala_type' => 'sunrise',
        ],
        'Krishna Padmanabha Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Krishna Paksha appearance day of Padmanabha (Vishnu)',
            'deity' => 'Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Krishna Yogeshwara Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Krishna Paksha appearance day of Yogeshwara (Krishna)',
            'deity' => 'Krishna',
            'karmakala_type' => 'sunrise',
        ],
        'Krishna Matsya Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Pausha',
            'description' => 'Krishna Paksha appearance day of Matsya avatar',
            'deity' => 'Vishnu (Matsya)',
            'karmakala_type' => 'sunrise',
        ],
        'Damodara Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Appearance day of Damodara (Krishna)',
            'deity' => 'Krishna',
            'karmakala_type' => 'sunrise',
        ],
        'Chitragupta Puja' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Worship of Lord Chitragupta, the keeper of records',
            'deity' => 'Chitragupta',
            'karmakala_type' => 'sunrise',
        ],
        'Dyuta Krida' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Traditional ritual gambling day during Diwali',
            'deity' => 'Shiva/Parvati',
            'karmakala_type' => 'sunrise',
        ],
        'Labh Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Auspicious day for concluding Diwali holidays and restarting business',
            'deity' => 'Ganesha/Lakshmi',
            'regions' => ['Gujarat'],
            'karmakala_type' => 'sunrise',
        ],
        'Labh Panchami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Labh Pancham', 'Saubhagya Panchami'],
            'description' => 'Auspicious day for opening new accounts and business ventures',
            'deity' => 'Lakshmi',
            'regions' => ['Gujarat'],
            'karmakala_type' => 'sunrise',
        ],
        'Matsya Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'matsya_jayanti',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Birth anniversary of Matsya avatar of Lord Vishnu',
            'deity' => 'Vishnu',
            'fasting' => true,
            // SJ 4.60.17: sunrise-vyapini Tritiya; when both days have vyapti, purva-yuk (Dwitiya-joined / first) is shubha.
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'require_sunrise_vyapini' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'dual_day_rule' => 'both_sunrise_vyapini_choose_purva_yuk_dwitiya_joined_tritiya',
            'ritual_profile' => 'matsya_jayanti_satsangi',
            'sect_specific' => true,
            'source_refs' => ['Satsangi Jeevan 4.60'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.60.17',
                    'supports' => 'Take sunrise-vyapini Chaitra Shukla Tritiya; when both days have sunrise vyapti, the purva-yuk / Dwitiya-joined Tritiya is preferred.',
                ],
            ],
        ],
        'Shravana Maas Begins' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'aliases' => ['Shravana Masarambh', 'Shravan Maas Begins', 'Shiva Puja Begins'],
            'description' => 'Shravana Maas Begins marks the start of the sacred Shravana month and the beginning of the month-long Shiva worship period.',
            'deity' => 'Shiva',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'strict_karmakala' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
        ],
        'Varaha Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'varaha_jayanti',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'aliases' => ['Swaminarayan Varaha Jayanti'],
            'description' => 'Varaha Jayanti celebrates the appearance of Lord Vishnu as Varaha, observed on Shravana Shukla Chaturthi with madhyahna worship.',
            'deity' => 'Vishnu (Varaha)',
            'fasting' => true,
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'require_madhyahna_vyapini' => true,
            'madhyahna_purvatithi_vedha_rejection' => true,
            'reject_prev_tithi_vedha' => true,
            'prev_tithi' => 3,
            'kshaya_accept_previous_tithi_vedha' => true,
            // SJ 4.61: when both or neither day is madhyahna-vyapini, take the later Chaturthi.
            'vriddhi_preference' => 'last',
            'kshaya_preference' => 'first',
            'dual_day_rule' => 'choose_second_chaturthi_even_if_both_or_neither_are_madhyahna_vyapini',
            'sect_specific' => true,
            'source_refs' => ['Satsangi Jeevan 4.61'],
        ],
        'Kalki Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Birth anniversary of Kalki avatar of Lord Vishnu',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Dadhichi Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Birth anniversary of Sage Dadhichi',
            'deity' => 'Dadhichi',
            'karmakala_type' => 'sunrise',
        ],
        'Vishwakarma Jayanti' => [
            'type' => 'solar',
            'month' => 9,
            'day' => 17,
            'description' => 'Birth anniversary of Lord Vishwakarma, the divine architect',
            'deity' => 'Vishwakarma',
            'regions' => ['Pan-India'],
        ],
        'Valmiki Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Birth anniversary of Sage Valmiki, author of Ramayana',
            'deity' => 'Valmiki',
            'karmakala_type' => 'sunrise',
        ],
        'Meerabai Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Birth anniversary of devotee poetess Meerabai',
            'deity' => 'Meerabai',
            'karmakala_type' => 'sunrise',
        ],
        'Narmada Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Celebration of River Narmada descent to Earth',
            'deity' => 'Narmada',
            'regions' => ['Madhya Pradesh'],
            'karmakala_type' => 'sunrise',
        ],
        'Bhanu Saptami' => [
            'type' => 'tithi',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'weekday' => 0, // Sunday
            'description' => 'Auspicious Saptami falling on a Sunday dedicated to Lord Surya',
            'deity' => 'Surya',
            'fasting' => true,
        ],
        'Arudra Darshan' => [
            'nakshatra_only' => true,
            'nakshatra' => 'Ardra',
            'allowed_months_amanta' => ['Margashirsha', 'Pausha'],
            'aliases' => ['Thiruvadhirai', 'Arudra Darshanam', 'Ardra Utsav'],
            'description' => 'Cosmic dance of Lord Shiva as Nataraja; celebrated during Margashirsha Ardra Nakshatra',
            'deity' => 'Shiva (Nataraja)',
            'regions' => ['Tamil Nadu', 'South India'],
            'karmakala_type' => 'sunrise',
        ],
        'Telugu Hanuman Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 10,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Jyeshtha',
            'aliases' => ['Telugu Hanuman Vratam', 'Telugu Hanuman Jayanthi'],
            'description' => '41-day Hanuman Deeksha conclusion; celebrated primarily in Andhra and Telangana',
            'deity' => 'Hanuman',
            'regions' => ['Andhra Pradesh', 'Telangana'],
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
        ],
        'Ramanuja Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'nakshatra_only' => true,
            'nakshatra' => 'Ardra',
            'paksha' => 'Shukla',
            'allowed_months_amanta' => ['Vaishakha'],
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Ramanuja Jayanti commemorates the appearance of Shri Ramanujacharya, the revered teacher of Vishishtadvaita Vedanta.',
            'deity' => 'Ramanuja',
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
        ],
        'Rohini Vrat' => [
            'nakshatra_only' => true,
            'nakshatra' => 'Rohini',
            'description' => 'Fasting observed on the day when Rohini Nakshatra prevails after sunrise',
            'deity' => 'Rohini',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Dayanand Saraswati Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 10,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Birth anniversary of Maharishi Dayanand Saraswati, founder of Arya Samaj',
            'deity' => 'Dayanand Saraswati',
            'karmakala_type' => 'sunrise',
        ],
        'Kamika Ekadashi' => [
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
        'Aadi Amavasya (Karkidaka Vavu)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Shravana',
            'description' => 'Highly auspicious day for ancestor worship (Tarpanam) in South India',
            'deity' => 'Pitrus',
            'regions' => ['Tamil Nadu', 'Kerala'],
            'karmakala_type' => 'aparahna',
        ],
        'Hariyali Teej' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Monsoon festival welcoming the rain',
            'deity' => 'Parvati/Shiva',
        ],
        'Nag Panchami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha_amanta' => 'Krishna',
            'paksha_purnimanta' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'aliases' => ['Nag Pancham'],
            'description' => 'Nag Panchami is a traditional observance dedicated to the worship of serpent deities.',
            'deity' => 'Nagas',
            'karmakala_type' => 'sunrise',
            'nag_panchami_paraviddha_table' => true,
            'resolution_policy' => [
                'shashthi_viddha_paraviddha' => true,
                'shashthi_vedha_ghadi_threshold' => 6,
                'chaturthi_vedha_ghadi_threshold' => 6,
                'vriddhi_preference' => 'first',
                'kshaya_preference' => 'first',
            ],
        ],
        'Tulsidas Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Birth anniversary of Goswami Tulsidas',
            'deity' => 'Rama/Tulsidas',
        ],
        'Shravana Putrada Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'aliases' => ['Vaishnava Shravana Putrada Ekadashi', 'Pavitra Ekadashi', 'Pavitropana Ekadashi', 'Pavitran Ekadashi', 'Pavitra Utsav', 'Pavitrotsava'],
            'description' => 'Fasting to be blessed with a son',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Avani Avittam (Yajur Upakarma)' => [
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
        'Pola' => [
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
        'Kushotpatini Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Amavasya dedicated to Goddess Kushotpatini',
            'deity' => 'Kushotpatini',
        ],
        'Aja Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Annada Ekadashi', 'Kaliya Dalana Ekadashi'],
            'description' => 'Fasting for Aja Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Goga Pancham' => [
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
            'karmakala_type' => 'sunrise',
            'nag_panchami_paraviddha_table' => true,
            'resolution_policy' => [
                'shashthi_viddha_paraviddha' => true,
                'shashthi_vedha_ghadi_threshold' => 6,
                'chaturthi_vedha_ghadi_threshold' => 6,
                'vriddhi_preference' => 'first',
                'kshaya_preference' => 'first',
            ],
        ],
        'Shravana Somvar (Monday Fasting)' => [
            'type' => 'weekday_in_month',
            'weekday' => 1,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Auspicious Monday of Shravana month dedicated to Lord Shiva',
            'deity' => 'Shiva',
            'fasting' => true,
        ],
        'First Mangala Gauri Vrat' => [
            'type' => 'nth_weekday_in_month',
            'weekday' => 2,
            'nth' => 1,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'First Tuesday fasting in the month of Shravana dedicated to Goddess Gauri',
            'deity' => 'Gauri',
            'fasting' => true,
        ],
        'Second Mangala Gauri Vrat' => [
            'type' => 'nth_weekday_in_month',
            'weekday' => 2,
            'nth' => 2,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Second Tuesday fasting in the month of Shravana dedicated to Goddess Gauri',
            'deity' => 'Gauri',
            'fasting' => true,
        ],
        'Third Mangala Gauri Vrat' => [
            'type' => 'nth_weekday_in_month',
            'weekday' => 2,
            'nth' => 3,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Third Tuesday fasting in the month of Shravana dedicated to Goddess Gauri',
            'deity' => 'Gauri',
            'fasting' => true,
        ],
        'Fourth Mangala Gauri Vrat' => [
            'type' => 'nth_weekday_in_month',
            'weekday' => 2,
            'nth' => 4,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Fourth Tuesday fasting in the month of Shravana dedicated to Goddess Gauri',
            'deity' => 'Gauri',
            'fasting' => true,
        ],
        'Ravivar Vrat' => [
            'type' => 'weekday',
            'weekday' => 0,
            'description' => 'Weekly Sunday fast dedicated to Lord Surya',
            'deity' => 'Sun',
            'fasting' => true,
            'aliases' => ['Sunday Vrat', 'Navagraha Weekday Fasting'],
        ],
        'Somwar Vrat' => [
            'type' => 'weekday',
            'weekday' => 1,
            'description' => 'Weekly Monday fast dedicated to Lord Shiva',
            'deity' => 'Shiva',
            'fasting' => true,
            'aliases' => ['Monday Vrat', 'Deities Weekdays Fasting'],
        ],
        'Mangalwar Vrat' => [
            'type' => 'weekday',
            'weekday' => 2,
            'description' => 'Weekly Tuesday fast dedicated to Hanuman and Mangala',
            'deity' => 'Hanuman',
            'fasting' => true,
            'aliases' => ['Tuesday Vrat'],
        ],
        'Budhwar Vrat' => [
            'type' => 'weekday',
            'weekday' => 3,
            'description' => 'Weekly Wednesday fast dedicated to Lord Vishnu and Budha',
            'deity' => 'Vishnu',
            'fasting' => true,
            'aliases' => ['Wednesday Vrat'],
        ],
        'Guruvar Vrat' => [
            'type' => 'weekday',
            'weekday' => 4,
            'description' => 'Weekly Thursday fast dedicated to Brihaspati and Lord Vishnu',
            'deity' => 'Vishnu',
            'fasting' => true,
            'aliases' => ['Brihaspativar Vrat', 'Thursday Vrat'],
        ],
        'Shukravar Vrat' => [
            'type' => 'weekday',
            'weekday' => 5,
            'description' => 'Weekly Friday fast dedicated to Goddess Lakshmi and Shukra',
            'deity' => 'Lakshmi',
            'fasting' => true,
            'aliases' => ['Friday Vrat'],
        ],
        'Shanivar Vrat' => [
            'type' => 'weekday',
            'weekday' => 6,
            'description' => 'Weekly Saturday fast dedicated to Lord Shani',
            'deity' => 'Shani',
            'fasting' => true,
            'aliases' => ['Saturday Vrat'],
        ],
        'Kajari Teej' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 3,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Kajari Teej festival',
            'deity' => 'Parvati/Shiva',
        ],
        'Bol Choth' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Bol Choth is a Gujarati vrata connected with reverence for cows and family wellbeing.',
            'deity' => 'Krishna/Cows',
            'karmakala_type' => 'sayankala',
            'strict_karmakala' => true,
        ],
        'Randhan Chhath' => [
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
        'Balarama Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Balarama Jayanti (Hala Shashthi)', 'Hal Shashthi (Balarama Jayanti)', 'Baladeva Chhath', 'Baldev Chhath', 'Balbhadra Jayanti'],
            'description' => 'Balarama birth celebration following the classical Garga Samhita tradition observed during Madhyahna',
            'deity' => 'Balarama',
            'fasting' => true,
            'nakshatra' => 'Swati',
            'prefer_nakshatra' => true,
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'source_refs' => ['Garga Samhita 1.11', 'Satsangi Jeevan 4.60'],
        ],
        'Sheetala Satam' => [
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
        'Krishna Janmashtami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Krishna Janmashtami celebrates the divine appearance of Bhagwan Shri Krishna with fasting, worship and midnight devotion.',
            'deity' => 'Krishna',
            'fasting' => true,
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
            'nakshatra' => 'Rohini',
            'prefer_nakshatra' => true,
            'prefer_weekdays' => [1, 3],
            'janmashtami_truth_table' => true,
            'traditions' => [
                'uddhav' => [
                    'variant_name' => 'Krishna Janmashtami',
                    'aliases' => ['Krishna Janmashtami (Swaminarayan-Uddhav)', 'Gokulashtami'],
                    'vriddhi_preference' => 'last',
                    'prefer_nakshatra_window' => false,
                    'tradition_profile' => 'Uddhav/Swaminarayan Janmashtami with Vitthalesh Goswami accepted opinion',
                    'ritual_profile' => 'janmashtami_uddhav',
                    'source_refs' => ['Satsangi Jeevan 4.11', 'Satsangi Jeevan 4.55'],
                ],
                'smarta' => [
                    'variant_name' => 'Krishna Janmashtami (Smarta)',
                    'prefer_nakshatra_window' => true,
                    'vriddhi_preference' => 'first',
                ],
            ],
        ],
        'Ramanand Swami Appearance Festival' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'ramanand_pradurbhav',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Ramanand Swami Pradurbhavotsav'],
            'description' => 'Ramanand Swami Pradurbhavotsav commemorates the morning appearance of Shri Ramanand Swami; after this, Janmashtami is observed by the already-given rule.',
            'deity' => 'Ramanand Swami',
            'fasting' => true,
            // SJ 4.61.34: appearance at pratah / morning (प्रगे), not nishitha.
            'karmakala_type' => 'pratah_kal',
            'strict_karmakala' => true,
            'require_karmakala_match' => true,
            'sect_specific' => true,
            'tradition_profile' => 'Satsangi Jeevan Shravana Krishna Ashtami pratah appearance',
            'ritual_profile' => 'ramanand_pradurbhav_satsangi',
            'source_refs' => ['Satsangi Jeevan 4.61'],
            'context_refs' => ['Satsangi Jeevan 4.12', 'Satsangi Jeevan 4.55'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.61.34-38',
                    'supports' => 'Shravana Krishna Ashtami morning (pratah) appearance of Ramanand Swami; mahapuja in the morning; Janmashtami utsav follows by the already-stated Janmashtami rule.',
                ],
            ],
        ],
        'Nand Mahotsav' => [
            'type' => 'day_after',
            'parent_festival' => 'Krishna Janmashtami',
            'days_after' => 1,
            'aliases' => ['Nanda Mahotsav'],
            'description' => "Nand Mahotsav celebrates the joyful festivities in Nanda Baba's home following the appearance of Shri Krishna.",
            'deity' => 'Nanda / Krishna family',
            'ritual_profile' => 'nand_mahotsav',
        ],
        'Hartalika Teej' => [
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
        'Gowri Habba (Swarna Gauri Vrata)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Major Karnataka festival honoring Goddess Gauri, observed a day before Ganesh Chaturthi',
            'deity' => 'Gauri',
            'regions' => ['Karnataka', 'Andhra Pradesh', 'Tamil Nadu'],
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
        ],
        'Ganesh Chaturthi' => [
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
            'require_karmakala_match' => true,
            'previous_tithi_vedha_tolerated' => true,
            'prefer_full_karmakala_coverage' => true,
            'gujarati_special_case' => 'prefer_full_madhyahna_chaturthi_coverage_over_partial_previous_overlap',
            'chandradarshan_nishedh' => true,
            'chandradarshan_nishedh_mode' => 'metadata',
            'source_refs' => ['Satsangi Jeevan 4.56.42-56'],
        ],
        'Nara-Narayan Arjun Janmotsav' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Nara-Narayan Janmotsav', 'Arjun Janmotsav'],
            'description' => 'Swaminarayan Nara-Narayan / Arjun birth festival with midday Dwitiya and Uttara Phalguni preference',
            'deity' => 'Nara-Narayan/Arjun',
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'nakshatra' => 'Uttara Phalguni',
            'prefer_nakshatra' => true,
            'sect_specific' => true,
            'ritual_profile' => 'nara_narayan_arjun_janmotsav',
            'source_refs' => ['Satsangi Jeevan 4.56.21-26'],
        ],
        'Rishi Panchami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Worship of the Saptarishis',
            'deity' => 'Saptarishis',
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
            'karmakala_type' => 'madhyahna',
        ],
        'Radha Ashtami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Radhashtami'],
            'description' => 'Radha Ashtami celebrates the divine appearance of Shri Radhika with worship and devotion to Radha-Krishna.',
            'deity' => 'Radha',
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'madhyahna_purvatithi_vedha_rejection' => true,
            'kshaya_accept_previous_tithi_vedha' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'resolution_policy' => [
                'madhyahna_vyapini' => true,
                'saptami_vedha_rejected' => true,
                'both_days_prefer_navami_yuta' => true,
                'vriddhi_preference' => 'first',
                'kshaya_preference' => 'first',
                'kshaya_accept_saptami_vedha' => true,
            ],
            'source_refs' => ['Satsangi Jeevan 4.56.64-78'],
        ],
        'Bhagavat Saptah Prarambh' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Bhagavat Saptaha Begins', 'Bhagwat Saptah Begins'],
            'description' => 'Bhagavat Saptah Prarambh marks the beginning of the seven-day recitation or listening of the Shrimad Bhagavatam.',
            'deity' => 'Vishnu/Krishna',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'ritual_profile' => 'bhagavat_saptah',
            'resolution_policy' => [
                'saptah_period' => true,
                'period_end_tithi' => 15,
            ],
            'source_evidence' => [
                [
                    'kind' => 'period_relation',
                    'locator' => 'Bhagavat Saptah section',
                    'supports' => 'The start is defined in relation to the Bhadrapada Shukla Purnima completion day; no independent sunrise-vyapini wording is stated in the extracted rule text.',
                ],
            ],
        ],
        'Bhagavat Saptah Samapt' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Bhagavat Saptaha Ends', 'Bhagwat Saptah Ends'],
            'description' => 'Bhagavat Saptah Samapt marks the completion of the seven-day Shrimad Bhagavatam observance.',
            'deity' => 'Vishnu/Krishna',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'vriddhi_preference' => 'first',
            'ritual_profile' => 'bhagavat_saptah',
            'source_evidence' => [
                [
                    'kind' => 'period_relation',
                    'locator' => 'Bhagavat Saptah section',
                    'supports' => 'The completion is assigned to Bhadrapada Shukla Purnima; no independent sunrise-vyapini wording is stated in the extracted rule text.',
                ],
            ],
        ],
        'Jivitputrika Vrat (Jitiya)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Rigorous fasting by mothers for the long life of their children',
            'deity' => 'Jimutavahana',
            'fasting' => true,
            'regions' => ['Bihar', 'Jharkhand', 'Uttar Pradesh', 'Nepal'],
        ],
        'Parivartini Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Parsva Ekadashi', 'Parshva Ekadashi', 'Jal Jhilani Ekadashi', 'Vamana Ekadashi', 'Dol Gyaras', 'Jayanti Ekadashi', 'Danleela Mahotsav', 'Padma Ekadashi'],
            'description' => 'Parivartini (Parsva) Ekadashi when Lord Vishnu turns side during Chaturmas; also known as Jal Jhilani Utsav (Swaminarayan)',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'fasting_guidance_key' => 'satsangi_ekadashi_standard_fast_guidance',
            'source_refs' => ['Satsangi Jeevan 3.32.160-168', 'Satsangi Jeevan 4.56.80-87'],
        ],
        'Mahant Swami Maharaj Janma Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 9,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Physical birth anniversary of Mahant Swami Maharaj (13 September 1933, Bhadarva Vad 9)',
            'deity' => 'Swaminarayan',
        ],
        'Goga Navami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 9,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Gugga Naumi', 'Shri Goga Navami'],
            'description' => 'North Indian festival dedicated to snake god Goga Ji',
            'deity' => 'Goga Ji',
            'regions' => ['Rajasthan', 'Haryana', 'Punjab', 'Himachal Pradesh', 'Uttar Pradesh', 'North India'],
            'karmakala_type' => 'sunrise',
        ],
        'Mahant Swami Maharaj Parshadi Diksha Din (Official Jayanti)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 1,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Official BAPS celebration of Mahant Swami Maharaj Jayanti on Parshadi Diksha Din (2 February 1957, Maha Vad 1)',
            'deity' => 'Swaminarayan',
        ],
        'Vamana Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Birth of Vamana Avatar',
            'deity' => 'Vamana',
            'karmakala_type' => 'abhijit',
            'strict_karmakala' => true,
            'nakshatra' => 'Shravana',
            'prefer_nakshatra' => true,
            'source_refs' => ['Satsangi Jeevan 4.56.89-101'],
        ],
        'Anant Chaturdashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 14,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'aliases' => ['Ganesh Visarjan'],
            'description' => 'Anant Chaturdashi is a Bhadrapada Shukla Chaturdashi vrata dedicated to Bhagwan Anant and auspicious protection.',
            'deity' => 'Vishnu/Ganesha',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'anant_chaturdashi_paraviddha_table' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'resolution_policy' => [
                'purvahna_primary_kala' => true,
                'post_sunrise_muhurta_threshold' => 2,
                'below_threshold_previous_day' => true,
                'vriddhi_preference' => 'first',
                'kshaya_preference' => 'first',
            ],
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Purnima Shraddha' => [
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
        'Pitru Paksha Begins' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 1,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Beginning of the fortnight of ancestors',
            'deity' => 'Pitrus',
        ],
        'Indira Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'aliases' => ['Pitri Uddhar Ekadashi', 'Ekadashi Shradh'],
            'description' => 'Fasting for Indira Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Lalita Panchami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Lalita Panchami is an Ashvina Shukla Panchami vrata dedicated to Upanga Lalita Devi.',
            'deity' => 'Lalita Devi',
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
            'lalita_panchami_aparahna_table' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Dussehra' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'aliases' => ['Vijayadashami', 'Vijayadashami (Aparajita Puja)'],
            'description' => 'Victory of Lord Rama over Ravana / End of Sharad Navaratri',
            'deity' => 'Rama/Durga',
            'karmakala_type' => 'vijaya_kaal',
            'strict_karmakala' => true,
            'target_window' => 'vijaya_kaal',
            'fallback_support' => 'aparahna',
            'nakshatra' => 'Shravana',
            'prefer_nakshatra' => true,
            'vijaya_kaal_primary' => true,
            'vijayadashami_truth_table' => true,
            'navratri_type' => 'sharad',
            'worship_profile' => 'north_navadurga_bhadrakali_kalpa',
            'ritual_profile' => 'vijayadashami_satsangi',
            'source_refs' => ['Satsangi Jeevan 4.57.30-34'],
        ],
        'Ayudha Puja (Saraswati Puja)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Worship of instruments, tools, and Goddess Saraswati during Navaratri',
            'deity' => 'Saraswati',
            'regions' => ['Karnataka', 'Tamil Nadu', 'Kerala', 'Andhra Pradesh', 'Telangana'],
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
        ],
        'Papankusha Ekadashi' => [
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
        'Gunatitanand Swami Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Birth anniversary of Gunatitanand Swami',
            'deity' => 'Swaminarayan',
        ],
        'Rama Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Rambha Ekadashi', 'Rameshwaram Ekadashi'],
            'description' => 'Fasting for Rama Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Karva Chauth' => [
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
        'Ahoi Ashtami' => [
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
        'Vagh Baras' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Govatsa Dwadashi', 'Vasu Baras', 'Bachha Baras'],
            'description' => 'Worship of cows and calves',
            'deity' => 'Krishna/Cows',
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'vriddhi_preference' => 'last',
            'prefer_first_karmakala' => false,
            'govatsa_equal_pradosha_preference' => 'second_day',
            'govatsa_truth_table' => true,
            'deepotsav_sequence' => 'govatsa_dwadashi',
        ],
        'Kojagari Lakshmi Puja' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'aliases' => ['Sharad Purnima', 'Kojagara Lakshmi Puja'],
            'description' => 'Lakshmi worship on Sharad Purnima night (Kojagari)',
            'deity' => 'Lakshmi',
            'karmakala_type' => 'nishitha',
            'reject_anumati_purnima' => true,
            'ritual_profile' => 'sharad_purnima_rasa',
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Dhanteras' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 13,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Worship of Lakshmi and Dhanvantari (Aso Vad 13, pradosha).',
            'deity' => 'Lakshmi/Dhanvantari',
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'deepotsav_sequence' => 'dhanteras',
            'aliases' => ['Dhanatrayodashi', 'Dhanvantari Jayanti (Dhantrayodashi)'],
            'source_refs' => ['Satsangi Jeevan 4.57.50-57'],
        ],
        'Alankar Marjanotsav' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 13,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Alankar Marjan', 'Alankar Marjanotsava'],
            'description' => "Alankar Marjanotsav is the ceremonial cleaning and preparation of the Lord's ornaments, worship vessels and temple articles before Diwali.",
            'deity' => 'Vishnu/Krishna',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'kshaya_preference' => 'first',
            'sect_specific' => true,
            'ritual_profile' => 'alankar_marjan_satsangi',
            'resolution_policy' => [
                'udaya_vyapini_trayodashi' => true,
                'kshaya_shift_to_dvadashi' => true,
            ],
            'source_refs' => ['Satsangi Jeevan 4.57'],
        ],
        'Kali Chaudas (Naraka Chaturdashi)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 14,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Kali Chaudas', 'Hanuman Puja'],
            'description' => 'Kali Chaudas worship of Goddess Kali and Hanuman during Sangava or Arunodaya',
            'deity' => 'Kali/Hanuman',
            'karmakala_type' => 'sangava',
            'strict_karmakala' => true,
            'deepotsav_sequence' => 'kali_chaudas_hanuman_puja',
            'source_refs' => ['Satsangi Jeevan 4.57'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.57',
                    'supports' => 'Hanuman worship on Kali Chaudas takes the Chaturdashi that pervades sangava-kala.',
                ],
            ],
        ],
        'Naraka Chaturdashi Abhyanga Snan' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 14,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Narak Chaturdashi', 'Abhyanga Snan'],
            'description' => 'Naraka Chaturdashi Abhyanga Snan is the traditional oil bath observed before Diwali for purification and auspiciousness.',
            'deity' => 'Krishna',
            'karmakala_type' => 'moonrise',
            'strict_karmakala' => true,
            'vriddhi_preference' => 'first',
            'naraka_chaturdashi_abhyanga_table' => true,
            'deepotsav_sequence' => 'naraka_chaturdashi_abhyanga_snan',
            'location_sensitive' => true,
            'rule_convention' => 'Abhyanga Snan primarily follows chandrodaya-vyapini Chaturdashi, with sunrise or ushah-side fallback handling where the moonrise rule alone does not settle the observance.',
            'source_refs' => ['Satsangi Jeevan 4.57'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.57',
                    'supports' => 'Prefer the Ashvina Krishna Chaturdashi that pervades moonrise; when moonrise alone does not settle the choice, sunrise or ushah-side fallback handling is discussed.',
                ],
                [
                    'kind' => 'textual_variant',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.57',
                    'supports' => 'Some authorities prefer the earlier arunodaya-side bath, while others still allow the later chandrodaya-side bath even if Amavasya has begun after arunodaya.',
                ],
            ],
        ],
        'Kali Puja' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Diwali', 'Kali Puja (Shyama Puja)'],
            'description' => 'Festival of lights / Worship of Goddess Kali',
            'deity' => 'Kali',
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'require_karmakala_match' => true,
            'vriddhi_preference' => 'first',
            'deepotsav_sequence' => 'diwali_lakshmi_kali_puja',
        ],
        'Govardhan Puja' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Annakut', 'Govardhan Utsav', 'Bali Puja'],
            'description' => "Govardhan Puja and Annakut celebrate Shri Krishna's Govardhan lila with cow worship, Govardhan worship and abundant food offerings.",
            'deity' => 'Krishna',
            'karmakala_type' => 'sayankala',
            'strict_karmakala' => true,
            'govardhan_annakut_truth_table' => true,
            'chandradarshan_nishedh' => true,
            'chandradarshan_nishedh_mode' => 'metadata',
            'deepotsav_sequence' => 'govardhan_annakut',
            'location_sensitive' => true,
            'resolution_policy' => [
                'sayahna_vyapini_pratipada' => true,
                'amavasya_viddha_pratipada_preferred' => true,
                'dwitiya_viddha_pratipada_rejected' => true,
                'sthula_chandra_darshana_muhurta_threshold' => 9,
                'annakut_solar_eclipse_fallback_navami' => true,
            ],
            'rule_convention' => 'Govardhan Puja is resolved for the configured location; city-specific public almanacs can differ by one civil date.',
            'source_refs' => ['Satsangi Jeevan 4.58.19-21'],
        ],
        'Bestu Varas' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Bestu Varas is the Gujarati New Year observed on Kartika Shukla Pratipada.',
            'deity' => 'Lakshmi/Ganesha',
            'regions' => ['Gujarat'],
            'resolution_policy' => [
                'new_year_kartika' => true,
            ],
            'calendar_rule' => [
                'tithi' => 1,
                'month_amanta' => 'Kartika',
                'month_purnimanta' => 'Kartika',
                'paksha' => 'Shukla',
            ],
        ],
        'Bhai Dooj' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Bhai Tika', 'Bhau Beej', 'Yama Dwitiya'],
            'description' => 'Brother-sister relationship celebration (known as Bhai Tika in Nepal)',
            'deity' => 'Yama/Yamuna',
            'regions' => ['Pan-India', 'Nepal'],
            'karmakala_type' => 'aparahna',
            'deepotsav_sequence' => 'bhai_beej',
        ],
        'Chhath Puja (Sandhya Arghya)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Chhath Puja (Surya Shashthi)'],
            'description' => 'Major day (3rd of 4) of Chhath Puja - evening arghya to Surya; festival runs Kartika Shukla 4-7',
            'deity' => 'Surya/Chhathi Maiya',
            'fasting' => true,
            'regions' => ['Bihar', 'Jharkhand', 'Eastern UP', 'Nepal'],
            'karmakala_type' => 'aparahna',
        ],
        'Jalaram Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Birth anniversary of Saint Jalaram Bapa of Virpur',
            'deity' => 'Rama/Jalaram Bapa',
        ],
        'Gopashtami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Worship and celebration of cows',
            'deity' => 'Kamadhenu/Krishna',
            'karmakala_type' => 'sunrise',
            'source_refs' => ['Satsangi Jeevan 4.58.36-44'],
        ],
        'Jagaddhatri Puja' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Worship of Goddess Jagaddhatri in Bengal',
            'deity' => 'Jagaddhatri',
        ],
        'Subrahmanya Shashti (Champa Shashthi)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'aliases' => ['Champa Shashthi'],
            'description' => 'Important festival dedicated to Lord Subrahmanya (Karthikeya) and Shiva',
            'deity' => 'Subrahmanya / Shiva (Khandoba)',
            'regions' => ['Karnataka', 'Andhra Pradesh', 'Maharashtra'],
            'karmakala_type' => 'sunrise',
        ],
        'Pramukh Swami Maharaj Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Birth anniversary of Pramukh Swami Maharaj (7 December 1921, Magshar Sud 8, VS 1978)',
            'deity' => 'Swaminarayan',
        ],
        'Devutthana (Prabodhini) Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Gauna Devutthana Ekadashi', 'Vaishnava Devutthana Ekadashi', 'Devutthana Ekadashi', 'Prabodhini Ekadashi', 'Haribodhini Ekadashi', 'Vishnu Prabodhini Ekadashi', 'Dev Uthani Ekadashi', 'Uttana Ekadashi', 'Papaharini Ekadashi', 'Kartiki Ekadashi', 'Devuthi Ekadashi'],
            'description' => 'Prabodhini Ekadashi / End of Chaturmas',
            'deity' => 'Vishnu/Swaminarayan',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'fasting_guidance_key' => 'satsangi_prabodhini_strict_fast_guidance',
            'source_refs' => ['Satsangi Jeevan 3.32.147-175', 'Satsangi Jeevan 4.58.45-99'],
        ],
        'Dharmadev Janmotsav' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'prabodhini_ekadashi_related',
            'inherit_decision_from' => 'Prabodhini Utsav',
            'document_status' => 'external_or_sect_specific_not_named_in_doc',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Swaminarayan Dharmadev birth festival observed on Prabodhini Ekadashi',
            'deity' => 'Dharmadev/Bhaktidevi',
            'fasting' => true,
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'sect_specific' => true,
            'kshaya_preference' => 'last',
            'fasting_guidance_key' => 'satsangi_prabodhini_strict_fast_guidance',
            'ritual_profile' => 'dharmadev_janmotsav',
            'source_refs' => ['Satsangi Jeevan 4.58.55-99'],
        ],
        'Hatadi Festival' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'prabodhini_ekadashi_related',
            'inherit_decision_from' => 'Prabodhini Utsav',
            'document_status' => 'named_in_nirnay_document',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Swaminarayan Hatadi observance with Radha-Damodar worship on Prabodhini evening',
            'deity' => 'Radha-Damodar',
            'fasting' => true,
            'karmakala_type' => 'sayankala',
            'strict_karmakala' => true,
            'sect_specific' => true,
            'kshaya_preference' => 'last',
            'fasting_guidance_key' => 'satsangi_prabodhini_strict_fast_guidance',
            'ritual_profile' => 'hatadi_prabodhini',
            'source_refs' => ['Satsangi Jeevan 4.58.55-99'],
        ],
        'Tulsi Vivah' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Ceremonial marriage of Tulsi to Vishnu',
            'deity' => 'Tulsi/Vishnu',
            'karmakala_type' => 'madhyahna',
            'ritual_kala_type' => 'sayankala',
            'source_refs' => ['Satsangi Jeevan 4.58.105-117'],
        ],
        'Vaikuntha Chaturdashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 14,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Vaikuntha Chaturdashi is a Kartika Shukla Chaturdashi observance honouring both Vishnu and Shiva.',
            'deity' => 'Vishnu/Shiva',
            'fasting' => true,
            'karmakala_type' => 'arunodaya',
            'strict_karmakala' => true,
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Dev Diwali (Tripurari Purnima)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Dev Diwali is the Kartika Purnima festival of lights, celebrated with worship, lamps and devotional joy.',
            'deity' => 'Shiva',
            'regions' => ['Varanasi', 'Uttar Pradesh'],
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'strict_karmakala' => true,
            'ritual_kala_type' => 'moonrise',
            'source_refs' => ['Satsangi Jeevan 4.58.119-134'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.58.119-134',
                    'supports' => 'Mukutotsav/Dev Diwali is selected by sunrise-vyapini Kartika Purnima in this source.',
                ],
                [
                    'kind' => 'alias_context',
                    'source' => 'Registry naming',
                    'supports' => 'Tripurari Purnima is retained as a broader alias/context label, not as a separate rule proven by this source passage.',
                ],
            ],
        ],
        'Karthigai Deepam' => [
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
        'Guru Nanak Jayanti (Kartika Purnima)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Birth anniversary of the first Sikh Guru; major pan-India observance',
            'deity' => 'Guru Nanak',
            'regions' => ['Pan-India', 'Punjab'],
            'karmakala_type' => 'sunrise',
        ],
        'Utpanna Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Margashirsha',
            'aliases' => ['Utpatti Ekadashi'],
            'description' => 'Fasting for Utpanna Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Vachanamrut Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Commemoration of the Vachanamrut',
            'deity' => 'Swaminarayan',
        ],
        'Kalabhairav Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Birth of Lord Kalabhairav (Shiva)',
            'deity' => 'Kalabhairav',
        ],
        'Vivah Panchami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Wedding anniversary of Rama and Sita',
            'deity' => 'Rama/Sita',
        ],
        'Mokshada Ekadashi (Geeta Jayanti)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'aliases' => ['Geeta Jayanti', 'Gita Jayanti Ekadashi', 'Mokshada Ekadashi', 'Mauna Ekadashi'],
            'description' => 'Fasting for Mokshada Ekadashi and celebration of Bhagavad Gita (Geeta Jayanti); known as the Gateway to Heaven',
            'deity' => 'Krishna/Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Dattatreya Jayanti' => [
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
        'Saphala Ekadashi' => [
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
        'Pausha Putrada Ekadashi' => [
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

        'Thai Poosam' => [
            'nakshatra_only' => true,
            'nakshatra' => 'Pushya',
            'requires_purnima' => true,
            'allowed_months_amanta' => ['Pausha', 'Magha'],
            'description' => 'Tamil festival dedicated to Lord Murugan; observed when Pushya nakshatra coincides with Purnima in Tamil month Thai (Jan-Feb)',
            'deity' => 'Murugan',
            'regions' => ['Tamil Nadu', 'Kerala', 'Karnataka', 'Andhra Pradesh', 'Telangana', 'Sri Lanka', 'Malaysia', 'Singapore'],
        ],
        'Onam (Thiruvonam)' => [
            'nakshatra_only' => true,
            'nakshatra' => 'Shravana',
            'allowed_months_amanta' => ['Shravana', 'Bhadrapada'],
            'sun_sign' => 4,
            'description' => "Kerala harvest festival; Thiruvonam nakshatra (Shravana) in Malayalam month Chingam (Aug-Sep); marks King Mahabali's annual visit",
            'deity' => 'Vishnu/Mahabali',
            'regions' => ['Kerala', 'Tamil Nadu', 'Karnataka'],
            'karmakala_type' => 'madhyahna',
        ],
        'Gunatitanand Swami Diksha Day' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Pausha',
            'description' => 'Diksha day of Gunatitanand Swami',
            'deity' => 'Swaminarayan',
        ],
        'Shattila Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Magha',
            'aliases' => ['Tila Ekadashi', 'Tilda Ekadashi'],
            'description' => 'Fasting for Shattila Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Vasant Panchami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'aliases' => ['Saraswati Jayanti', 'Saraswati Puja', 'Shree Panchami', 'Shikshapatri Jayanti', 'Vasant Panchami (Saraswati Puja)'],
            'description' => 'Vasant Panchami welcomes spring and is observed with worship of Saraswati and festive devotion.',
            'deity' => 'Saraswati',
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'require_sunrise_vyapini' => true,
            'resolution_policy' => [
                'vriddhi_preference' => 'first',
                'kshaya_table' => '4-5 or 6',
            ],
            'source_refs' => ['Satsangi Jeevan 4.59'],
        ],
        'Chandrayan Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 14,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Pausha',
            'description' => 'Chandrayan vrat beginning with Pausha Shukla Chaturdashi in the Swaminarayan annual vrata cycle',
            'deity' => 'Vishnu/Chandra',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'sect_specific' => true,
            'ritual_profile' => 'chandrayan_vrat_satsangi',
            'source_refs' => ['Satsangi Jeevan 4.19'],
        ],
        'Ratha Saptami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 7,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'aliases' => ['Ratha Saptami (Surya Jayanti)'],
            'description' => 'Surya Jayanti',
            'deity' => 'Surya',
            'karmakala_type' => 'arunodaya',
            'strict_karmakala' => true,
            'require_karmakala_match' => true,
        ],
        'Bhishma Ashtami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Anniversary of Bhishma Pitamah departure',
            'deity' => 'Bhishma',
        ],
        'Jaya Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'aliases' => ['Gauna Jaya Ekadashi', 'Vaishnava Jaya Ekadashi', 'Bhaimi Ekadashi', 'Bhishma Ekadashi'],
            'description' => 'Fasting for Jaya Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Vijaya Ekadashi' => [
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
        'Maha Shivaratri' => [
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
            'prefer_full_karmakala_coverage' => true,
            'ekadesha_coverage_allowed' => true,
            'mahashivaratri_truth_table' => true,
            'source_refs' => ['Satsangi Jeevan 4.59'],
        ],
        'Phulera Dooj' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Festival of flowers',
            'deity' => 'Krishna',
        ],
        'Amalaki Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'aliases' => ['Amla Ekadashi', 'Rangbhari Ekadashi'],
            'description' => 'Fasting for Amalaki Ekadashi',
            'deity' => 'Vishnu',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Holashtak Prarambh' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'aliases' => ['Holi Ashtak Begins'],
            'description' => 'Holashtak Prarambh begins the eight-day period leading up to Holi.',
            'deity' => 'Holi',
            'karmakala_type' => 'tithi_boundary',
            'tithi_boundary_rule' => 'start',
            'source_refs' => ['Vrat Parva Viveka (Priyavrat Sharma)'],
        ],
        'Holika Dahan' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Holika Dahan, also called Hutashani, is the sacred fire ceremony performed on the eve of Dhulendi.',
            'deity' => 'Vishnu/Hiranyakashipu',
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'require_karmakala_match' => true,
            'vriddhi_preference' => 'first',
            'avoid_bhadra_mukha' => true,
            'prefer_bhadra_puchha' => true,
            'holika_lunar_eclipse_exception' => true,
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Holashtak Samapt' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'aliases' => ['Holi Ashtak Ends', 'Holashtak Samapt'],
            'description' => 'Holashtak Samapt marks the close of the eight-day Holi preparation period on Phalguna Purnima.',
            'deity' => 'Holi',
            'karmakala_type' => 'tithi_boundary',
            'tithi_boundary_rule' => 'end',
            'source_refs' => ['Vrat Parva Viveka (Priyavrat Sharma)'],
        ],
        'Bhagatji Maharaj Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Birth of Bhagatji Maharaj (Swaminarayan Sect)',
            'deity' => 'Swaminarayan',
        ],
        'Dhuleti' => [
            'type' => 'day_after',
            'parent_festival'  => 'Holika Dahan',
            'days_after' => 1,
            'aliases' => ['Dhulandi'],
            'description' => 'Mainstream festival of colors played on the day following the Holika Dahan bonfire.',
            'deity' => 'Krishna',
            'regions' => ['Pan-India'],
        ],
        'Phuldolotsava' => [
            'type' => 'special',
            'resolver' => 'phuldolotsava',
            'family' => 'phuldolotsava',
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Chaitra',
            'allowed_months_amanta' => ['Phalguna'],
            // Purnimanta: Purnima is still Phalguna; Krishna Padwa shifts to Chaitra.
            'allowed_months_purnimanta' => ['Phalguna', 'Chaitra'],
            'candidate_tithis' => [
                ['paksha' => 'Shukla', 'tithi' => 15], // Purnima exception candidate
                ['paksha' => 'Krishna', 'tithi' => 1], // Main Padwa / Pratipada candidate
            ],
            // Kept for metadata/export compatibility with tithi-oriented consumers.
            'paksha' => 'Krishna',
            'tithi' => 1,
            'nakshatra' => 'Uttara Phalguni',
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'require_sunrise_nakshatra' => true,
            'dual_day_rule' => 'if_purnima_and_pratipada_both_have_sunrise_uttara_phalguni_choose_purnima',
            'aliases' => ['Pushpadolotsav', 'Fuldol Utsav', 'Phalgun Dolotsav', 'Phooldolotsav'],
            'description' => 'Phuldolotsava is a Phalguna festival of flowers, colours and devotional swing worship connected with Shri Krishna and Nar-Narayan devotion.',
            'deity' => 'Nar-Narayan',
            'sect_specific' => true,
            'tradition_profile' => 'Satsangi Jeevan Phalguna Uttaraphalguni Dolotsav',
            'ritual_profile' => 'pushpa_dolotsav_satsangi',
            'ritual_layers' => ['swing_dolotsav', 'pushpa_abhishek', 'vasant_rang_kriya', 'nar_narayan_dhyana'],
            'source_refs' => ['Satsangi Jeevan 4.60'],
            'context_refs' => ['Satsangi Jeevan 4.59'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.60.5',
                    'supports' => 'सूर्योदये ... आर्यम्णं ... पूर्वे दिने तच्चेद्दिनद्वये — observe on the sunrise-Uttara-Phalguni day; if both Purnima and Padwa have it, take the earlier Purnima day because of longer vyapti.',
                ],
                [
                    'kind' => 'seasonal_context',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.59',
                    'supports' => 'Falguni-lila singing continues from Vasant Panchami until Phuldolotsava; this is context, not the date-selection rule.',
                ],
            ],
        ],
        'Kurma Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'kurma_jayanti',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'aliases' => ['Swaminarayan Kurma Jayanti', 'Kurma Jayanti (Swaminarayan/Satsangi)'],
            'description' => 'Commemorates Kurma avatar appearance during Samudra Manthana',
            'deity' => 'Vishnu (Kurma)',
            // SJ 4.60.38: sunrise-vyapini Pratipada; both days → para-yukta / second.
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'require_karmakala_match' => true,
            'require_sunrise_vyapini' => true,
            'vriddhi_preference' => 'last',
            'dual_day_rule' => 'both_sunrise_vyapini_choose_para_yukta_pratipada',
            'sect_specific' => true,
            'tradition_profile' => 'Swaminarayan Kurma Jayanti rule on Vaishakha Shukla Pratipada',
            'ritual_profile' => 'kurma_jayanti_satsangi',
            'resolution_policy' => [
                'vriddhi_preference' => 'last',
            ],
            'source_refs' => ['Satsangi Jeevan 4.60'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.60.37-38',
                    'supports' => 'Take sunrise-vyapini Vaishakha Shukla Pratipada; when both days are sunrise-vyapini, take the later / Dwitiya-yukta Pratipada.',
                ],
            ],
        ],
        'Kurma Jayanti (Vaishakha Purnima Tradition)' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'aliases' => ['Shri Koorma Jayanti', 'Kurma Avatara Appearance'],
            'description' => 'This entry represents a later calendrical convention that associates Kurma Jayanti with Vaishakha Purnima.',
            'deity' => 'Vishnu (Kurma)',
            'fasting' => true,
            'karmakala_type' => 'aparahna',
            'strict_karmakala' => true,
            'require_karmakala_match' => true,
            'tradition_profile' => 'Vaishakha Purnima Kurma Jayanti convention',
            'source_refs' => ['Siddhanta Darpana'],
        ],
        'Snanyatra' => [
            // SJ 4.61.8: in Jyeshtha month, the day with Jyeshtha nakshatra at sunrise (not fixed by tithi).
            'type' => 'tithi',
            'resolver' => 'classical',
            'nakshatra_only' => true,
            'nakshatra' => 'Jyeshtha',
            'allowed_months_amanta' => ['Jyeshtha'],
            'description' => 'Snanyatra is the ceremonial bathing festival of Shri Krishna observed on the Jyeshtha-month day that has Jyeshtha nakshatra at sunrise.',
            'deity' => 'Krishna',
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'require_sunrise_nakshatra' => true,
            'sect_specific' => true,
            'ritual_profile' => 'snanyatra_satsangi',
            'source_refs' => ['Satsangi Jeevan 4.61'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.61.8',
                    'supports' => 'In Jyeshtha month choose the civil day that has Jyeshtha nakshatra present at sunrise; not fixed by tithi.',
                ],
            ],
        ],
        'Swaminarayan Rathyatra' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'nakshatra_only' => true,
            'nakshatra' => 'Pushya',
            // SJ 4.61: Ashadha Shukla paksha + Pushya at sunrise (not fixed Ashadha Sud 2).
            'paksha' => 'Shukla',
            'allowed_pakshas' => ['Shukla'],
            'allowed_months_amanta' => ['Ashadha'],
            'description' => 'Swaminarayan Rathyatra when Pushya nakshatra is present at sunrise in Ashadha',
            'deity' => 'Balakrishna',
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'require_sunrise_nakshatra' => true,
            'sect_specific' => true,
            'ritual_profile' => 'rathyatra_satsangi',
            'source_refs' => ['Satsangi Jeevan 4.61'],
        ],
        'Hindola Festival Begins' => [
            // SJ 4.61.17–18: Ashadha Krishna Pratipada or Dwitiya when Moon has strength in Taurus; daily evening 2-ghadi swing.
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'hindola_period',
            'paksha' => 'Krishna',
            'tithi' => 1,
            'tithi_options' => [1, 2],
            'prefer_higher_tithi_option' => true,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Shravana',
            'description' => 'Hindola Festival Begins marks the start of the Swaminarayan hindola season, when Shri Balakrishna is lovingly seated and swung in decorated swings.',
            'deity' => 'Balakrishna',
            'karmakala_type' => 'sayankala',
            'sect_specific' => true,
            'ritual_profile' => 'hindola_satsangi',
            'moon_sign_strength' => 'Taurus',
            'unresolved_conditions' => [
                'Moon strength in Taurus (वृषराशि चन्द्रबल) is stated in SJ 4.61.17 but not yet enforced as a hard executable filter.',
            ],
            'resolution_policy' => [
                'period_start_conditional' => true,
                'period_end_tithi' => 3,
                'period_end_month_amanta' => 'Shravana',
                'period_end_month_purnimanta' => 'Bhadrapada',
                'period_end_paksha' => 'Krishna',
                'daily_evening_two_ghadi' => true,
                'adhika_nij_rule' => true,
                'avoid_bhadra' => true,
                'avoid_weekdays' => [3, 6],
            ],
            'source_refs' => ['Satsangi Jeevan 4.61'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.61.17-18',
                    'supports' => 'Start on Ashadha Krishna Pratipada or Dwitiya when the Moon has strength in Taurus; every evening seat Balakrishna, arati, and swing for two ghadi.',
                ],
            ],
        ],
        'Hindola Festival Ends' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'hindola_period',
            'paksha' => 'Krishna',
            // SJ 4.61.19: Shravana Krishna Tritiya — final arati and removal from the hindola.
            'tithi' => 3,
            'tithi_options' => [3],
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Hindola Festival Ends marks the completion of the Swaminarayan hindola season on Shravana Krishna Tritiya.',
            'deity' => 'Balakrishna',
            'karmakala_type' => 'sayankala',
            'sect_specific' => true,
            'ritual_profile' => 'hindola_satsangi',
            'source_refs' => ['Satsangi Jeevan 4.61'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.61.19',
                    'supports' => 'On Shravana Krishna Tritiya perform final arati and remove Balakrishna from the hindola.',
                ],
            ],
        ],
        'Pavitra Festival' => [
            // SJ 4.61.27: Shravana Shukla Ekadashi vrat day or Dwadashi.
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'pavitra_arpan',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'tithi_options' => [11, 12],
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'aliases' => ['Pavitra Arpan', 'Pavitra Arpan Utsav'],
            'description' => 'Swaminarayan Pavitra offering on Shravana Shukla Ekadashi vrat day or Dwadashi',
            'deity' => 'Krishna',
            'karmakala_type' => 'sunrise',
            'sect_specific' => true,
            'ritual_profile' => 'pavitra_satsangi',
            'source_refs' => ['Satsangi Jeevan 4.61'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.61.27',
                    'supports' => 'Offer pavitra on the Shravana Shukla Ekadashi vrat day or on Dwadashi.',
                ],
            ],
        ],
        'Swaminarayan Varaha Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'varaha_jayanti',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => "Swaminarayan Varaha Jayanti celebrates Lord Vishnu's Varaha appearance with midday worship in the Swaminarayan tradition.",
            'deity' => 'Vishnu (Varaha)',
            'fasting' => true,
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'require_madhyahna_vyapini' => true,
            'sect_specific' => true,
            'madhyahna_purvatithi_vedha_rejection' => true,
            'reject_prev_tithi_vedha' => true,
            'prev_tithi' => 3,
            'kshaya_accept_previous_tithi_vedha' => true,
            // SJ 4.61: both or neither madhyahna-vyapini => second/later Chaturthi (panchami-yuta).
            'vriddhi_preference' => 'last',
            'kshaya_preference' => 'first',
            'dual_day_rule' => 'choose_second_chaturthi_even_if_both_or_neither_are_madhyahna_vyapini',
            'resolution_policy' => [
                'madhyahna_vyapini' => true,
                'tritiya_vedha_rejected' => true,
                'both_days_prefer_panchami_yuta' => true,
                'vriddhi_preference' => 'last',
                'kshaya_preference' => 'first',
                'kshaya_accept_tritiya_vedha' => true,
            ],
            'ritual_profile' => 'varaha_jayanti_satsangi',
            'source_refs' => ['Satsangi Jeevan 4.61'],
        ],
        'Varalakshmi Vratam' => [
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
            'aliases' => ['Kamala Ekadashi', 'Purushottami Ekadashi', 'Padmini Vishuddha Ekadashi'],
            'description' => 'Special Ekadashi occurring ONLY during Adhik Maas',
            'deity' => 'Vishnu',
            'fasting' => true,
            'adhika_only' => true,
            'vriddhi_preference' => 'last',
            'kshaya_preference' => 'last',
            'prefer_growth_before_score' => true,
            'require_vaishnava_ekadashi_today' => true,
        ],
        'Parama Ekadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'aliases' => ['Parama Shuddha Ekadashi'],
            'description' => 'Special Ekadashi occurring ONLY during Adhik Maas Krishna Paksha',
            'deity' => 'Vishnu',
            'fasting' => true,
            'adhika_only' => true,
            'require_vaishnava_ekadashi_today' => true,
        ],
        'Skanda Sashti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Kanda Sashti (Soorasamharam)', 'Skanda Shashti Vratam'],
            'description' => "Skanda Sashti celebrates Lord Murugan's victory over Surapadman with devotion, worship and vrata observance",
            'deity' => 'Skanda',
            'regions' => ['Tamil Nadu', 'South India'],
            'karmakala_type' => 'sunset',
            'strict_karmakala' => true,
            'panchami_viddha_allowed' => true,
        ],
        'Vidyarambham' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'aliases' => ['Vidyarambham Day'],
            'description' => 'Initiation into knowledge and education',
            'deity' => 'Saraswati',
            'regions' => ['Kerala', 'South India'],
            'karmakala_type' => 'sunrise',
        ],
        'Karwa Chauth' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Karak Chaturthi'],
            'description' => 'Fasting by married women for the longevity of husbands',
            'deity' => 'Gauri/Shiva',
            'regions' => ['North India'],
            'karmakala_type' => 'moonrise',
        ],
        'Yama Deepam' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 13,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Lighting lamps to please Lord Yama',
            'deity' => 'Yama',
            'karmakala_type' => 'pradosha',
        ],
        'Chopda Pujan' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Sharda Puja', 'Deepavali Puja'],
            'description' => 'Consecration of account books and Lakshmi worship',
            'deity' => 'Lakshmi/Ganesha',
            'regions' => ['Gujarat', 'Maharashtra'],
            'karmakala_type' => 'pradosha',
        ],
        'Bali Pratipada' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Annakut'],
            'description' => 'Worship of King Bali and Govardhan Hill',
            'deity' => 'Bali/Krishna',
            // Same SJ 4.58 Govardhan/Annakut day selection as Govardhan Puja (Bhuj Mandir:
            // Bali+Annakut may shift to Amavasya-viddha day; Bestu Varas stays sunrise).
            'karmakala_type' => 'sayankala',
            'strict_karmakala' => true,
            'govardhan_annakut_truth_table' => true,
            'chandradarshan_nishedh' => true,
            'chandradarshan_nishedh_mode' => 'metadata',
            'deepotsav_sequence' => 'govardhan_annakut',
            'location_sensitive' => true,
            'resolution_policy' => [
                'sayahna_vyapini_pratipada' => true,
                'amavasya_viddha_pratipada_preferred' => true,
                'dwitiya_viddha_pratipada_rejected' => true,
                'sthula_chandra_darshana_muhurta_threshold' => 9,
            ],
            'source_refs' => ['Satsangi Jeevan 4.58'],
        ],
        'Sata Yuga Diwas' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'aliases' => ['Akshaya Navami', 'Kushmanda Navami'],
            'description' => 'Akshaya Navami, also connected with Sata Yuga remembrance, is a Kartika Shukla Navami observance of auspicious merit.',
            'deity' => 'Vishnu',
            'karmakala_type' => 'purvahna',
            'akshaya_navami_purvahna_table' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'source_refs' => ['Vrat Parva Viveka (Priyavrat Sharma)'],
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'General Manvadi/Yugadi purvahna rule',
                    'supports' => 'The purvahna decision is inherited through the Akshaya Navami/Yugadi-style rule; the exact Sata Yuga Diwas name is not separately listed in the Nirnay document.',
                ],
            ],
        ],
        'Treta Yuga Diwas' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'aliases' => ['Akshaya Tritiya'],
            'description' => 'Treta Yuga Diwas is traditionally remembered on Akshaya Tritiya and associated with auspicious merit and dharmic beginnings.',
            'deity' => 'Vishnu/Parashurama',
            'karmakala_type' => 'purvahna',
            'akshaya_tritiya_purvahna_table' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'resolution_policy' => [
                'purvahna_vyapini' => true,
                'both_days_purvahna_second_day_muhurta_threshold' => 3,
                'vriddhi_preference' => 'first',
                'kshaya_preference' => 'first',
            ],
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Akshaya Tritiya / general Yugadi purvahna rule',
                    'supports' => 'Treta Yuga remembrance inherits Akshaya Tritiya purvahna-vyapini handling; the exact Treta Yuga Diwas name is not separately listed in the Nirnay document.',
                ],
            ],
        ],
        'Dwapara Yuga Diwas' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Phalguna',
            'aliases' => ['Mauni Amavasya'],
            'description' => 'Commemoration of the beginning of Dwapara Yuga',
            'deity' => 'Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Kali Yuga Diwas' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 13,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Commemoration of the beginning of Kali Yuga',
            'deity' => 'Shiva/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Vaivaswata Manvadi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Commemoration of the beginning of Vaivaswata Manvantara',
            'deity' => 'Brahma/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Swayambhuva Manvadi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Commemoration of the beginning of Swayambhuva Manvantara',
            'deity' => 'Brahma/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Brahma Savarni Manvadi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Pausha',
            'description' => 'Commemoration of the beginning of Brahma Savarni Manvantara',
            'deity' => 'Brahma/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Daksha Savarni Manvadi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Commemoration of the beginning of Daksha Savarni Manvantara',
            'deity' => 'Brahma/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Indra Savarni Manvadi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Commemoration of the beginning of Indra Savarni Manvantara',
            'deity' => 'Brahma/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Tamasa Manvadi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Commemoration of the beginning of Tamasa Manvantara',
            'deity' => 'Brahma/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Uttama Manvadi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Commemoration of the beginning of Uttama Manvantara',
            'deity' => 'Brahma/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Raivata Manvadi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Commemoration of the beginning of Raivata Manvantara',
            'deity' => 'Brahma/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Chakshusha Manvadi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Commemoration of the beginning of Chakshusha Manvantara',
            'deity' => 'Brahma/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Swarochisha Manvadi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Commemoration of the beginning of Swarochisha Manvantara',
            'deity' => 'Brahma/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Savarni Manvadi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Commemoration of the beginning of Savarni Manvantara',
            'deity' => 'Brahma/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Rudra Savarni Manvadi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Commemoration of the beginning of Rudra Savarni Manvantara',
            'deity' => 'Brahma/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Daiva Savarni Manvadi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Commemoration of the beginning of Daiva Savarni Manvantara',
            'deity' => 'Brahma/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Bhishma Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Dedicated to Bhishma Pitamaha of Mahabharata',
            'deity' => 'Bhishma',
            'karmakala_type' => 'sunrise',
        ],
        'Sheetala Ashtami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Chaitra',
            'aliases' => ['Basoda', 'Sheetala Aatham'],
            'description' => 'Worship of Goddess Sheetala; eating stale food (Basoda)',
            'deity' => 'Sheetala Devi',
            'regions' => ['Rajasthan', 'North India'],
            'karmakala_type' => 'sunrise',
        ],
        'Matsya Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Appearance day of Matsya avatar',
            'deity' => 'Vishnu (Matsya)',
            'karmakala_type' => 'sunrise',
        ],
        'Vamana Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Appearance day of Vamana avatar',
            'deity' => 'Vishnu (Vamana)',
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'nakshatra' => 'Shravana',
            'prefer_nakshatra' => true,
        ],
        'Varaha Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'description' => 'Appearance day of Varaha avatar',
            'deity' => 'Vishnu (Varaha)',
            'karmakala_type' => 'sunrise',
        ],
        'Kurma Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Pausha',
            'description' => 'Appearance day of Kurma avatar',
            'deity' => 'Vishnu (Kurma)',
            'karmakala_type' => 'sunrise',
        ],
        'Narasimha Dwadashi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 12,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Appearance day of Narasimha avatar',
            'deity' => 'Vishnu (Narasimha)',
            'karmakala_type' => 'sunrise',
        ],
        'Ramakrishna Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Birth anniversary of Sri Ramakrishna Paramahansa',
            'deity' => 'Ramakrishna',
            'karmakala_type' => 'sunrise',
        ],
        'Vallabhacharya Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 11,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Vallabhacharya Jayanti commemorates the appearance of Shri Vallabhacharya, the revered founder-acharya of Pushtimarg.',
            'deity' => 'Vallabhacharya',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'kshaya_preference' => 'last',
            'source_refs' => ['Vrat Parva Viveka (Priyavrat Sharma)'],
        ],
        'Vighnaraja Sankashti Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Vighnaraja Sankashti Chaturthi',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'sankashti_truth_table' => true,
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'aliases' => ['Sankashti Chaturthi', 'Vighnaraja Sankashti'],
            'description' => 'Sankashti Chaturthi of Bhadrapada/Ashvina month',
            'deity' => 'Ganesha',
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Generic Sankashti Chaturthi section',
                    'supports' => 'The named monthly Sankashti identity inherits the generic moonrise-vyapini Sankashti Chaturthi rule.',
                ],
            ],
        ],
        'Ganesha Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Magha',
            'month_purnimanta' => 'Magha',
            'aliases' => ['Dhundhiraja Chaturthi', 'Varada Chaturthi', 'Tila Chaturthi', 'Gauriganesha Chaturthi', 'Vinayaka Chaturthi'],
            'description' => 'Birth anniversary of Lord Ganesha',
            'deity' => 'Ganesha',
            'karmakala_type' => 'sunrise',
        ],
        'Aniruddha Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Dedicated to Aniruddha, grandson of Lord Krishna',
            'deity' => 'Aniruddha',
            'karmakala_type' => 'sunrise',
        ],
        'Pradyumna Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Dedicated to Pradyumna, son of Lord Krishna',
            'deity' => 'Pradyumna',
            'karmakala_type' => 'sunrise',
        ],
        'Sankarshana Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Dedicated to Sankarshana (Balarama)',
            'deity' => 'Sankarshana',
            'karmakala_type' => 'sunrise',
        ],
        'Vasudeva Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Phalguna',
            'description' => 'Dedicated to Lord Vasudeva',
            'deity' => 'Krishna',
            'karmakala_type' => 'sunrise',
        ],
        'Thrissur Pooram' => [
            'nakshatra_only' => true,
            'nakshatra' => 'Purva Phalguni',
            'allowed_months_amanta' => ['Chaitra', 'Vaishakha'],
            'description' => 'Grand festival of Thrissur, Kerala; observed during Medam month Pooram Nakshatra',
            'deity' => 'Shiva/Parvati',
            'regions' => ['Kerala'],
            'karmakala_type' => 'sunrise',
        ],
        'Attukal Pongal' => [
            'nakshatra_only' => true,
            'nakshatra' => 'Bharani',
            'allowed_months_amanta' => ['Magha', 'Phalguna'],
            'description' => 'Large gathering of women for cooking offering to Attukal Amma',
            'deity' => 'Attukal Amma (Durga)',
            'regions' => ['Kerala'],
            'karmakala_type' => 'sunrise',
        ],
        'Rigveda Upakarma' => [
            'nakshatra_only' => true,
            'nakshatra' => 'Shravana',
            'requires_purnima' => true,
            'allowed_months_amanta' => ['Shravana', 'Bhadrapada'],
            'description' => 'Annual ritual of changing the sacred thread for Rigvedis',
            'deity' => 'Rishis/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Yajurveda Upakarma' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Shravana',
            'description' => 'Annual ritual of changing the sacred thread for Yajurvedis',
            'deity' => 'Rishis/Vishnu',
            'karmakala_type' => 'sunrise',
        ],
        'Samaveda Upakarma' => [
            'nakshatra_only' => true,
            'nakshatra' => 'Hasta',
            'allowed_months_amanta' => ['Bhadrapada'],
            'description' => 'Annual ritual of changing the sacred thread for Samavedis (primarily Hasta nakshatra based)',
            'deity' => 'Rishis/Vishnu',
            'karmakala_type' => 'aparahna',
            'require_nakshatra_window' => true,
        ],
        'Gayatri Japam' => [
            'type' => 'day_after',
            'parent_festival' => 'Shravana Purnima',
            'days_after' => 1,
            'description' => 'Chanting of Gayatri Mantra following Upakarma',
            'deity' => 'Gayatri',
        ],
        'Anvadhan' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'description' => 'Fasting observed on Purnima/Amavasya; typically the day before Ishti',
            'deity' => 'Agni',
            'karmakala_type' => 'sunrise',
        ],
        'Ishti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 1,
            'description' => 'Sacrificial rites performed on the day following Purnima/Amavasya',
            'deity' => 'Agni',
            'karmakala_type' => 'sunrise',
        ],
        'Kalashtami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'description' => 'Monthly fasting dedicated to Lord Bhairava',
            'deity' => 'Bhairava (Shiva)',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Masik Durgashtami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'description' => 'Masik Durgashtami is the monthly Shukla Ashtami observance dedicated to Devi.',
            'deity' => 'Durga',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'durgashtami_paraviddha_table' => true,
            'resolution_policy' => [
                'navami_viddha_paraviddha' => true,
                'ashtami_vedha_muhurta_threshold' => 3,
                'vriddhi_preference' => 'first',
                'kshaya_preference' => 'first',
            ],
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Masik Krishna Janmashtami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'masik_krishna_janmashtami',
            'inherit_decision_from' => 'Krishna Janmashtami',
            'document_status' => 'generic_family_rule_named_classifier',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'description' => 'Monthly fasting marking the birth tithi of Lord Krishna',
            'deity' => 'Krishna',
            'fasting' => true,
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
            'masik_janmashtami_truth_table' => true,
            'tradition_profile' => 'Monthly Krishna Janmashtami Nishitha Ashtami decision',
        ],
        'Chandra Darshana' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'description' => 'Chandra Darshana marks the first visible crescent moon after Amavasya.',
            'deity' => 'Chandra',
            'fasting' => true,
            'karmakala_type' => 'chandra_darshana_visibility',
            'chandra_darshana_visibility' => true,
            'chandra_darshana_visibility_model' => 'classical_sthula_chandra_darshana_9_muhurta',
            'chandra_darshana_visibility_basis' => 'classical_textual_rule_sthula_chandra_darshana',
            'chandra_darshana_sthula_muhurta_threshold' => 9,
            'location_sensitive' => true,
        ],
        'Bhogi Pandigai' => [
            'type' => 'day_after',
            'parent_festival' => 'Makara Sankranti (Pongal)',
            'days_after' => -1, // Day BEFORE Makar Sankranti
            'description' => 'First day of Pongal, celebrated in honor of Lord Indra',
            'deity' => 'Indra',
            'regions' => ['Tamil Nadu', 'Andhra Pradesh', 'Telangana'],
        ],
        'Makaravilakku' => [
            'type' => 'solar_sankranti',
            'rashi' => 9,
            'description' => 'Annual festival held on Makara Sankranti in Sabarimala',
            'deity' => 'Ayyappan',
            'regions' => ['Kerala'],
        ],
        'Magh Bihu' => [
            'type' => 'solar_sankranti',
            'rashi' => 9,
            'aliases' => ['Bhogali Bihu', 'Magh Bihu (Bhogali Bihu)'],
            'description' => 'Harvest festival of Assam, coinciding with Makara Sankranti',
            'deity' => 'Agni/Ancestors',
            'regions' => ['Assam'],
        ],
        'Thai Amavasai' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Pausha',
            'month_purnimanta' => 'Magha',
            'aliases' => ['Darsha Amavasya'],
            'description' => 'Auspicious Amavasya in Tamil month of Thai for ancestor worship',
            'deity' => 'Pitrus',
            'regions' => ['Tamil Nadu'],
            'karmakala_type' => 'aparahna',
        ],
        'Darsha Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'description' => 'Darsha Amavasya is the monthly Amavasya observance associated with pitru rites and ancestral remembrance.',
            'deity' => 'Chandra/Pitrus',
            'fasting' => true,
            'karmakala_type' => 'aparahna',
            'darsha_amavasya_aparahna_table' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
        ],
        'Amavasya' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'aliases' => ['Amas', 'Amavasya Vrat', 'Somavati Amavasya', 'Bhaumavati Amavasya', 'Shani Amavasya'],
            'description' => 'Amavasya is the monthly new-moon day associated with ancestral remembrance, worship and inner purification.',
            'deity' => 'Pitru/Chandra',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'strict_karmakala' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
            'source_refs' => ['Nirnaya Sindhu / Dharma Sindhu'],
        ],
        'Mukutotsav Purnima' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'aliases' => ['Mukutotsav Poonam'],
            'description' => 'Mukutotsav Purnima is the monthly Purnima observance for offering a beautiful crown and festive adornment to Thakorji.',
            'deity' => 'Thakorji',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
            'strict_karmakala' => true,
            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'first',
        ],
        'Masik Karthigai' => [
            'nakshatra_only' => true,
            'nakshatra' => 'Krittika',
            'description' => 'Monthly Krittika Nakshatra fasting for Lord Murugan',
            'deity' => 'Murugan',
            'fasting' => true,
            'regions' => ['Tamil Nadu'],
            'karmakala_type' => 'sunrise',
        ],
        'Yashoda Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 6,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Birth anniversary of Mother Yashoda',
            'deity' => 'Yashoda',
            'karmakala_type' => 'sunrise',
        ],
        'Shabari Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 7,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Birth anniversary of Shabari',
            'deity' => 'Shabari',
            'karmakala_type' => 'sunrise',
        ],
        'Janaki Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Phalguna',
            'month_purnimanta' => 'Chaitra',
            'aliases' => ['Sita Ashtami'],
            'description' => 'Birth anniversary of Goddess Sita (according to some regional calendars)',
            'deity' => 'Sita',
            'karmakala_type' => 'sunrise',
        ],
        'Lakshmi Panchami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Worship of Goddess Lakshmi',
            'deity' => 'Lakshmi',
            'karmakala_type' => 'sunrise',
        ],
        'Ashoka Ashtami Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Fasting and worship on Chaitra Shukla Ashtami, associated with Goddess Durga and Ashoka blossoms',
            'deity' => 'Durga',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Tara Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Birth anniversary of Goddess Tara (Mahavidya)',
            'deity' => 'Tara',
            'karmakala_type' => 'sunrise',
        ],
        'Kubjika Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Birth anniversary of Goddess Kubjika',
            'deity' => 'Kubjika (Durga)',
            'karmakala_type' => 'sunrise',
        ],
        'Parashara Rishi Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth anniversary of Sage Parashara',
            'deity' => 'Parashara',
            'karmakala_type' => 'sunrise',
        ],
        'Matangi Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth anniversary of Goddess Matangi (Mahavidya)',
            'deity' => 'Matangi',
            'karmakala_type' => 'sunrise',
        ],
        'Surdas Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 5,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth anniversary of poet-saint Surdas',
            'deity' => 'Surdas',
            'karmakala_type' => 'sunrise',
        ],
        'Bagalamukhi Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth anniversary of Goddess Bagalamukhi (Mahavidya)',
            'deity' => 'Bagalamukhi',
            'karmakala_type' => 'sunrise',
        ],
        'Siddhilakshmi Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 9,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth anniversary of Goddess Siddhilakshmi',
            'deity' => 'Lakshmi',
            'karmakala_type' => 'sunrise',
        ],
        'Chhinnamasta Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 14,
            'month_amanta' => 'Vaishakha',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth anniversary of Goddess Chhinnamasta (Mahavidya)',
            'deity' => 'Chhinnamasta',
            'karmakala_type' => 'sunrise',
        ],
        'Chandika Jayanti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Vaishakha',
            'description' => 'Birth anniversary of Goddess Chandika',
            'deity' => 'Chandika (Durga)',
            'karmakala_type' => 'sunrise',
        ],
        'Chitra Pournami' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'nakshatra' => 'Chitra',
            'month_amanta' => 'Chaitra',
            'month_purnimanta' => 'Chaitra',
            'description' => 'Full moon day associated with Chitra Nakshatra; worship of Chitragupta',
            'deity' => 'Chitragupta',
            'regions' => ['Tamil Nadu'],
            'karmakala_type' => 'sunrise',
            'prefer_nakshatra' => true,
        ],
        'Bhishma Panchak Ends' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Conclusion of Bhishma Panchak',
            'deity' => 'Bhishma',
            'karmakala_type' => 'sunrise',
        ],
        'Maha Bharani' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'nakshatra' => 'Bharani',
            'paksha' => 'Krishna',
            'month_amanta' => 'Bhadrapada',
            'month_purnimanta' => 'Ashvina',
            'description' => 'Highly auspicious Bharani Nakshatra during Pitru Paksha',
            'deity' => 'Pitrus',
            'karmakala_type' => 'aparahna',
            'prefer_nakshatra' => true,
        ],
        'Maha Sangada Hara Chathurti' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Maha Sangada Hara Chathurti',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'sankashti_truth_table' => true,
            'paksha' => 'Krishna',
            'tithi' => 4,
            'month_amanta' => 'Shravana',
            'month_purnimanta' => 'Bhadrapada',
            'description' => 'Most auspicious Sankashti Chaturthi of the year',
            'deity' => 'Ganesha',
            'karmakala_type' => 'moonrise',
            'fasting' => true,
            'source_evidence' => [
                [
                    'kind' => 'inherited_rule',
                    'locator' => 'Generic Sankashti Chaturthi section',
                    'supports' => 'The named yearly Sankashti identity inherits the generic moonrise-vyapini Sankashti Chaturthi rule.',
                ],
            ],
        ],
        'Nagula Chavithi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Kartika',
            'month_purnimanta' => 'Kartika',
            'description' => 'Worship of Nagas in Andhra Pradesh',
            'deity' => 'Naga Devatas',
            'regions' => ['Andhra Pradesh', 'Telangana'],
            'karmakala_type' => 'sunrise',
        ],
        'Varada Chaturthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'month_amanta' => 'Jyeshtha',
            'month_purnimanta' => 'Jyeshtha',
            'description' => 'Dedicated to Lord Ganesha for fulfillment of desires',
            'deity' => 'Ganesha',
            'karmakala_type' => 'sunrise',
        ],
        'Yama Panchaka Begins' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 13,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Start of the five-day Diwali celebration period',
            'deity' => 'Yama',
            'karmakala_type' => 'pradosha',
        ],
        'Mandala Pooja Begins' => [
            'type' => 'solar',
            'month' => 11,
            'day' => 15,
            'description' => 'Beginning of 41-day Mandala period at Sabarimala',
            'deity' => 'Ayyappan',
            'regions' => ['Kerala'],
        ],
        'Mandala Pooja' => [
            'type' => 'solar',
            'month' => 12,
            'day' => 26,
            'description' => 'Conclusion of 41-day Mandala period at Sabarimala',
            'deity' => 'Ayyappan',
            'regions' => ['Kerala'],
        ],
        'Hanuman Puja' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 14,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Worship of Lord Hanuman during Diwali period',
            'deity' => 'Hanuman',
            'karmakala_type' => 'sunrise',
        ],
        'Kedar Gauri Vrat' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Ashvina',
            'month_purnimanta' => 'Kartika',
            'description' => 'Vrata observed by married women during Diwali',
            'deity' => 'Gauri/Shiva',
            'regions' => ['South India'],
            'karmakala_type' => 'pradosha',
        ],
        'Jagannath Ratha Yatra' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 2,
            'month_amanta' => 'Ashadha',
            'month_purnimanta' => 'Ashadha',
            'description' => 'Chariot festival of Jagannath, Balabhadra and Subhadra',
            'deity' => 'Jagannath',
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
        ],
        'Kannada Hanuman Vratam' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 13,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Margashirsha',
            'description' => 'Karnataka Hanuman Vratam on Margashirsha Shukla Trayodashi',
            'deity' => 'Hanuman',
            'fasting' => true,
            'karmakala_type' => 'sunrise',
            'require_sunrise_vyapini' => true,
        ],
        'Tamil Hanumath Jayanthi' => [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 15,
            'month_amanta' => 'Margashirsha',
            'month_purnimanta' => 'Pausha',
            'description' => 'Tamil Hanuman observance on Margashirsha Amavasya',
            'deity' => 'Hanuman',
            'fasting' => true,
            'nakshatra' => 'Mula',
            'prefer_nakshatra' => true,
            'karmakala_type' => 'sunrise',
        ],
        'Thai Pusam' => [
            'type' => 'solar_nakshatra',
            'resolver' => 'classical',
            'rashi' => 9,
            'nakshatra' => 'Pushya',
            'nakshatra_only' => true,
            'description' => 'Murugan festival in the solar month Thai under Pushya Nakshatra',
            'deity' => 'Skanda-Murugan',
            'fasting' => true,
            'regions' => ['Tamil Nadu'],
            'sun_sign' => 9,
            'karmakala_type' => 'sunrise',
        ],
        'Vaikasi Visakam' => [
            'type' => 'solar_nakshatra',
            'resolver' => 'classical',
            'rashi' => 1,
            'nakshatra' => 'Vishakha',
            'nakshatra_only' => true,
            'description' => 'Murugan festival in the solar month Vaikasi under Vishakha Nakshatra',
            'deity' => 'Skanda-Murugan',
            'fasting' => true,
            'regions' => ['Tamil Nadu'],
            'sun_sign' => 1,
            'karmakala_type' => 'sunrise',
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

    public static function getFestivalCount(): int
    {
        return count(self::FESTIVALS);
    }

    /**
     * Get festivals for a specific date using actual panchang data
     * This is the PRIMARY method - uses real tithi from PanchangService.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolveFestivalsForDate(
        CarbonImmutable $date,
        array $todayDetails,
        array $tomorrowDetails,
        ?array $yesterdayDetails = null,
        ?callable $fetchHistoricalSnapshot = null,
        bool $includeExtraWinners = false,
        string $selection = 'all'
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

        foreach ($this->expandFestivalRules() as $name => $rules) {
            if (!$this->shouldIncludeFestivalRules($rules, $selection)) {
                continue;
            }

            $calendar = $todayDetails['Hindu_Calendar'] ?? [];
            $isAdhika = (bool) ($calendar['Is_Adhika'] ?? false);
            $isKshaya = (bool) ($calendar['Is_Kshaya'] ?? false);
            $type = (string) ($rules['type'] ?? 'tithi');

            // Adhika/Nija filtering logic for lunar (tithi) observances.
            // Default behavior is Nija-only unless explicitly marked otherwise.
            $adhikaAllowed = (bool) ($rules['allow_adhika'] ?? false) || (bool) ($rules['allows_adhika'] ?? false);
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
            if ($this->monthRuleExcluded($rules, (array) ($todayDetails['Hindu_Calendar'] ?? []))) {
                continue;
            }

            if ((isset($rules['month_amanta']) || isset($rules['month_purnimanta']))
                && !$this->monthRuleMatches($rules, (array) ($todayDetails['Hindu_Calendar'] ?? []))
                && !$this->canResolveAcrossMonthBoundary($rules, (array) ($tomorrowDetails['Hindu_Calendar'] ?? []), $isClassical)) {
                continue; // Skip this festival for this month
            }

            if ($isClassical) {
                $resolved = $this->ruleEngine->resolveMajorFestival($name, $rules, $date, $todayDetails, $tomorrowDetails);
                if ($resolved !== null
                    && $resolved['observance_date'] === $date->toDateString()
                    && !$this->previousDayAlreadyWonSamePradoshInterval($name, $rules, $date, $todayDetails, $yesterdayDetails, $resolved)
                    && !$this->rejectResolvedFestivalForDay($rules, $todayDetails)
                    && !isset($addedFestivalKeys[$name])) {
                    $festivals[] = $this->buildFestivalPayload($name, $rules, $resolved);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                } elseif ($yesterdayDetails !== null && !(bool) ($rules['prefer_growth_before_score'] ?? false) && !isset($addedFestivalKeys[$name])) {
                    // Back-fill festivals whose resolved observance date is today but
                    // whose tithi decision was derived from yesterday->today.
                    $resolvedYesterday = $this->ruleEngine->resolveMajorFestival($name, $rules, $date->subDay(), $yesterdayDetails, $todayDetails);
                    if ($resolvedYesterday !== null
                        && $resolvedYesterday['observance_date'] === $date->toDateString()
                        && !$this->rejectResolvedFestivalForDay($rules, $todayDetails)) {
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

        $this->appendDerivedVaishnavaObservances(
            $date,
            $todayDetails,
            $tomorrowDetails,
            $yesterdayDetails,
            $festivals,
            $festivalMeta,
            $addedFestivalKeys,
            $selection
        );

        // During Adhika Maas, if special Adhika Ekadashi(s) are present on a date,
        // suppress regular Ekadashi labels for that same date to avoid double tagging.
        if ((bool) (($todayDetails['Hindu_Calendar']['Is_Adhika'] ?? false)) && $festivals !== []) {
            $hasAdhikaOnlyEkadashi = false;
            foreach ($festivalMeta as $meta) {
                if ((bool) ($meta['is_ekadashi'] ?? false) && (bool) ($meta['adhika_only'] ?? false)) {
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

        // Resolve day_after festivals (e.g. Holi after Holika Dahan)
        // These require checking if the parent festival was observed on a previous date
        $dayAfterFestivals = $this->resolveDayAfterFestivals(
            $date,
            $todayDetails,
            $tomorrowDetails,
            $yesterdayDetails,
            $fetchHistoricalSnapshot,
            $addedFestivalKeys,
            $selection
        );
        foreach ($dayAfterFestivals as $item) {
            $festivals[] = $item['festival'];
            $festivalMeta[] = $item['meta'];
            $addedFestivalKeys[] = $item['key'];
        }

        // Post-processing: Deduplicate festivals on the same date based on aliases
        $mergedFestivals = [];
        $namesToRemove = [];

        // Find if any festival has another festival's name as an alias
        foreach ($festivals as $fest) {
            $name = $fest['name'] ?? '';
            $aliases = $fest['aliases'] ?? [];
            foreach ($festivals as $other) {
                $otherName = $other['name'] ?? '';
                if ($name !== $otherName && in_array($otherName, $aliases, true)) {
                    // If they are mutual aliases, prefer to keep the one with fewer aliases (more specific)
                    $otherAliases = $other['aliases'] ?? [];
                    if (in_array($name, $otherAliases, true)) {
                        if (count($aliases) < count($otherAliases)) {
                            $namesToRemove[$otherName] = true;
                        }
                    } else {
                        $namesToRemove[$otherName] = true;
                    }
                }
            }
        }

        foreach ($festivals as $fest) {
            if (!isset($namesToRemove[$fest['name'] ?? ''])) {
                $mergedFestivals[] = $fest;
            }
        }

        return $mergedFestivals;
    }

    /**
     * Build a complete, localized festival payload while preserving the
     * calculation basis from the registry and resolver decision context.
     *
     * @return array<string, mixed>
     */
    public function buildFestivalPayload(string $name, array $rules, ?array $resolved = null): array
    {
        if ($name === 'Pradosh Vrat' && isset($resolved['observance_date'])) {
            $dateObj = CarbonImmutable::parse($resolved['observance_date']);
            $dayOfWeek = $dateObj->dayOfWeek; // 0 for Sunday, 6 for Saturday

            $names = [
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

            $name = $names[$dayOfWeek] ?? 'Pradosh Vrat';
            $rules['description'] = $descriptions[$dayOfWeek] ?? ($rules['description'] ?? '');
            $rules['deity'] = ($dayOfWeek === 6) ? 'Shiva/Shani' : 'Shiva';
            $rules['aliases'] = ['Pradosh Vrat'];
            if ($dayOfWeek === 6) {
                $rules['aliases'][] = 'Shani Trayodashi';
            }
        }

        $regions = $rules['regions'] ?? ['Pan-India'];
        $localizedAliases = array_map(
            static fn (string $alias): string => Localization::translate('Festival', $alias),
            array_map(strval(...), (array) ($rules['aliases'] ?? []))
        );
        $aliases = array_values(array_unique($localizedAliases));
        $deity = $rules['deity'] ?? null;

        $payload = [
            'name' => Localization::translate('Festival', $name),
            'name_key' => $name,
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
        $resolver = (string) ($rules['resolver'] ?? '');

        // Engine-backed resolvers (generic classical + named special decision tables).
        return in_array($resolver, ['classical', 'phuldolotsava'], true);
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
                'paksha_name' => Paksha::{$paksha}->getName(),
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
     * Expand merged tradition-aware festival definitions into effective runtime rules.
     *
     * This allows the registry to keep one canonical root entry while still emitting
     * distinct observance variants such as Smarta vs Uddhav/Swaminarayan.
     *
     * @return array<string, array<string, mixed>>
     */
    private function expandFestivalRules(): array
    {
        $expanded = [];

        foreach (self::FESTIVALS as $name => $rules) {
            $traditions = $rules['traditions'] ?? null;
            if (!is_array($traditions)) {
                $expanded[$name] = $rules;
                continue;
            }

            $baseRules = $rules;
            unset($baseRules['traditions']);

            foreach ($traditions as $traditionKey => $traditionRules) {
                if (!is_array($traditionRules)) {
                    continue;
                }

                $variantName = $traditionRules['variant_name'];
                $variantAliases = array_map(
                    static fn (mixed $alias): string => (string) $alias,
                    (array) ($traditionRules['aliases'] ?? [])
                );

                $effectiveTraditionRules = $traditionRules;
                unset($effectiveTraditionRules['variant_name'], $effectiveTraditionRules['aliases']);

                $effectiveRules = array_replace($baseRules, $effectiveTraditionRules);
                if ($variantAliases !== []) {
                    $effectiveRules['aliases'] = array_values(array_unique($variantAliases));
                }

                $effectiveRules['merged_tradition_key'] = (string) $traditionKey;
                $expanded[$variantName] = $effectiveRules;
            }
        }

        return $expanded;
    }

    private function rejectResolvedFestivalForDay(array $rules, array $todayDetails): bool
    {
        if ((bool) ($rules['require_vaishnava_ekadashi_today'] ?? false)) {
            $vaishnava = (array) (($todayDetails['Ekadashi_Observance']['ekadashi_vaishnava'] ?? []));

            return (string) ($vaishnava['fasting_day'] ?? '') !== 'Today';
        }

        return false;
    }

    private function previousDayAlreadyWonSamePradoshInterval(
        string $name,
        array $rules,
        CarbonImmutable $date,
        array $todayDetails,
        ?array $yesterdayDetails,
        array $resolved
    ): bool {
        if ($yesterdayDetails === null || !$this->isPradoshRuleMetadata($rules)) {
            return false;
        }

        $previousResolved = $this->ruleEngine->resolveMajorFestival($name, $rules, $date->subDay(), $yesterdayDetails, $todayDetails);
        if ($previousResolved === null || ($previousResolved['observance_date'] ?? null) !== $date->subDay()->toDateString()) {
            return false;
        }

        $currentStart = $resolved['target_tithi_start_jd'] ?? null;
        $currentEnd = $resolved['target_tithi_end_jd'] ?? null;
        $previousStart = $previousResolved['target_tithi_start_jd'] ?? null;
        $previousEnd = $previousResolved['target_tithi_end_jd'] ?? null;
        if (!is_numeric($currentStart) || !is_numeric($currentEnd) || !is_numeric($previousStart) || !is_numeric($previousEnd)) {
            return false;
        }

        return abs((float) $currentStart - (float) $previousStart) < 0.0000001
            && abs((float) $currentEnd - (float) $previousEnd) < 0.0000001;
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
        array $addedFestivalKeys,
        string $selection = 'all'
    ): array {
        $results = [];

        foreach ($this->expandFestivalRules() as $name => $rules) {
            if ((string) ($rules['type'] ?? '') !== 'day_after') {
                continue;
            }

            if (!$this->shouldIncludeFestivalRules($rules, $selection)) {
                continue;
            }

            $parentName = (string) ($rules['parent_festival'] ?? '');
            $daysAfter = (int) ($rules['days_after'] ?? 1);

            if ($parentName === '') {
                continue;
            }

            $parentDate = $daysAfter === 0 ? $date : $date->subDays($daysAfter);
            $parentFound = false;

            // Case 0: same-day linked festival (e.g. Chandan Yatra Begins on Parashurama Jayanti day).
            // Prefer already-resolved parent keys from this same day pass.
            if ($daysAfter === 0) {
                $parentFound = $this->resolvedFestivalKeysContainParent($addedFestivalKeys, $parentName)
                    || $this->snapshotContainsFestivalName($todayDetails, $parentName)
                    || $this->checkDayAfterParent($parentName, $todayDetails);
            }
            // Case 1: daysAfter === 1, use yesterdayDetails directly
            elseif ($daysAfter === 1 && $yesterdayDetails !== null) {
                $parentFound = $this->snapshotContainsFestivalName($yesterdayDetails, $parentName)
                    || $this->checkDayAfterParent($parentName, $yesterdayDetails);
            }
            // Case 2: daysAfter > 1, fetch historical snapshot via callback
            elseif ($daysAfter > 1 && $fetchHistoricalSnapshot !== null) {
                $historicalSnapshot = $fetchHistoricalSnapshot($parentDate);
                if ($historicalSnapshot !== null) {
                    $parentFound = $this->snapshotContainsFestivalName($historicalSnapshot, $parentName)
                        || $this->checkDayAfterParent($parentName, $historicalSnapshot);
                }
            }

            if (!$parentFound) {
                continue;
            }

            $key = 'day_after:' . $name . ':' . $date->toDateString();
            if (in_array($key, $addedFestivalKeys, true)) {
                continue;
            }

            $parentLabel = Localization::translate('Festival', $parentName);
            $observanceNote = $daysAfter === 0
                ? sprintf(
                    Localization::translate('String', 'observance_note_same_day_parent'),
                    $parentLabel
                )
                : sprintf(
                    Localization::translate('String', 'observance_note_day_after'),
                    $daysAfter,
                    $parentLabel
                );

            $resolved = [
                'festival_name' => $name,
                'standard_date' => $date->toDateString(),
                'observance_date' => $date->toDateString(),
                'observance_note' => $observanceNote,
                'decision' => [
                    'winning_reason' => $daysAfter === 0 ? 'same_day_linked_parent_festival' : 'day_after_parent_festival',
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

    private function shouldIncludeFestivalRules(array $rules, string $selection): bool
    {
        $normalized = strtolower($selection);
        $isVrat = (bool) ($rules['fasting'] ?? false);

        return match ($normalized) {
            'all' => true,
            'vrats' => $isVrat,
            'festivals' => !$isVrat,
            default => throw new LogicException('Unknown festival selection: ' . $selection),
        };
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

    /** Collect canonical + alias + tradition-variant names for a parent festival key. */
    private function parentFestivalNameSet(string $parentName): array
    {
        $names = [$parentName];
        foreach ((array) (self::FESTIVALS[$parentName]['aliases'] ?? []) as $alias) {
            $names[] = (string) $alias;
        }

        foreach ((array) (self::FESTIVALS[$parentName]['traditions'] ?? []) as $tradition) {
            if (!is_array($tradition)) {
                continue;
            }

            $variant = (string) ($tradition['variant_name'] ?? '');
            if ($variant !== '') {
                $names[] = $variant;
            }

            foreach ((array) ($tradition['aliases'] ?? []) as $alias) {
                $names[] = (string) $alias;
            }
        }

        return array_values(array_unique(array_filter($names, static fn (string $value): bool => $value !== '')));
    }

    /** True when the current day pass already resolved the parent festival. */
    private function resolvedFestivalKeysContainParent(array $addedFestivalKeys, string $parentName): bool
    {
        foreach ($this->parentFestivalNameSet($parentName) as $name) {
            if (isset($addedFestivalKeys[$name]) || in_array($name, $addedFestivalKeys, true)) {
                return true;
            }
        }

        return false;
    }

    /** True when the day snapshot already lists the parent festival (or one of its aliases). */
    private function snapshotContainsFestivalName(array $snapshot, string $parentName): bool
    {
        $names = $this->parentFestivalNameSet($parentName);

        foreach ((array) ($snapshot['Festivals'] ?? []) as $festival) {
            if (!is_array($festival)) {
                continue;
            }

            $festivalName = (string) ($festival['name'] ?? $festival['resolution']['festival_name'] ?? '');
            if ($festivalName !== '' && in_array($festivalName, $names, true)) {
                return true;
            }

            foreach ((array) ($festival['aliases'] ?? []) as $alias) {
                if (in_array((string) $alias, $names, true)) {
                    return true;
                }
            }
        }

        return false;
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
            'chandra_darshana_visibility_min_lag_minutes' => $rules['chandra_darshana_visibility_min_lag_minutes'] ?? null,
            'chandra_darshana_visibility_min_elongation_degrees' => $rules['chandra_darshana_visibility_min_elongation_degrees'] ?? null,
            'chandra_darshana_visibility_hard_elongation_floor_degrees' => $rules['chandra_darshana_visibility_hard_elongation_floor_degrees'] ?? null,
            'chandra_darshana_visibility_min_illumination_percent' => $rules['chandra_darshana_visibility_min_illumination_percent'] ?? null,
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
            'source_refs' => $rules['source_refs'] ?? null,
            'source_evidence' => $rules['source_evidence'] ?? null,
            'textual_variants' => $rules['textual_variants'] ?? null,
            'resolver_compatibility' => $rules['resolver_compatibility'] ?? null,
            'unresolved_conditions' => $rules['unresolved_conditions'] ?? null,
        ];

        return $this->filterEmptyMetadata($basis);
    }

    private function isEkadashiNirnayRuleMetadata(array $rules): bool
    {
        return (int) ($rules['tithi'] ?? 0) === 11
            && ((bool) ($rules['ekadashi_nirnay_table'] ?? false) || (bool) ($rules['require_vaishnava_ekadashi_today'] ?? false));
    }

    private function isPradoshRuleMetadata(array $rules): bool
    {
        return (int) ($rules['tithi'] ?? 0) === 13
            && (string) ($rules['karmakala_type'] ?? '') === 'pradosha'
            && (bool) ($rules['fasting'] ?? false);
    }

    private function isSankashtiRuleMetadata(array $rules): bool
    {
        return (int) ($rules['tithi'] ?? 0) === 4
            && (string) ($rules['paksha'] ?? '') === 'Krishna'
            && (string) ($rules['karmakala_type'] ?? '') === 'moonrise'
            && str_contains(strtolower((string) ($rules['description'] ?? '')), 'sankashti');
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

    private function resolveNakshatraNumber(string $label): ?int
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        foreach (Nakshatra::cases() as $case) {
            if ($case->getName('en') === $label || $case->getName('hi') === $label || $case->getName('gu') === $label) {
                return $case->value + 1;
            }
        }

        $normalized = $this->normalizeMonthName($label);
        if ($normalized === '') {
            return null;
        }

        foreach (Nakshatra::cases() as $case) {
            if ($this->normalizeMonthName($case->getName('en')) === $normalized) {
                return $case->value + 1;
            }

            foreach (['hi', 'gu'] as $locale) {
                if ($this->normalizeMonthName($case->getName($locale)) === $normalized) {
                    return $case->value + 1;
                }
            }
        }

        return null;
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
     * @param array<int, array<string, mixed>> $festivals
     * @param array<int, array<string, mixed>> $festivalMeta
     * @param array<string, bool> $addedFestivalKeys
     */
    private function appendDerivedVaishnavaObservances(
        CarbonImmutable $date,
        array $todayDetails,
        array $tomorrowDetails,
        ?array $yesterdayDetails,
        array &$festivals,
        array &$festivalMeta,
        array &$addedFestivalKeys,
        string $selection = 'all'
    ): void {
        $todayVaishnava = (array) (($todayDetails['Ekadashi_Observance']['ekadashi_vaishnava'] ?? []));
        $yesterdayVaishnava = is_array($yesterdayDetails)
            ? (array) (($yesterdayDetails['Ekadashi_Observance']['ekadashi_vaishnava'] ?? []))
            : [];

        if (($todayVaishnava['fasting_day'] ?? null) === 'Today') {
            $rules = [
                'type' => 'derived_vaishnava_ekadashi',
                'description' => 'Vaishnava / ISKCON Ekadashi fasting day observed with devotion to Lord Vishnu',
                'deity' => 'Vishnu',
                'fasting' => true,
                'aliases' => ['Vaishnava Ekadashi'],
            ];
            if ($this->shouldIncludeFestivalRules($rules, $selection)) {
                $this->appendDerivedFestival(
                    name: 'ISKCON Ekadashi',
                    rules: $rules,
                    observanceDate: $date->toDateString(),
                    reason: 'vaishnava_ekadashi_today',
                    festivals: $festivals,
                    festivalMeta: $festivalMeta,
                    addedFestivalKeys: $addedFestivalKeys,
                );
            }
        }

        if (($yesterdayVaishnava['fasting_day'] ?? null) === 'Tomorrow_Mahadvadashi') {
            $status = (string) ($yesterdayVaishnava['status'] ?? 'Mahadvadashi');
            $rules = [
                'type' => 'derived_mahadvadashi',
                'description' => 'Mahadwadashi fasting day observed in the Vaishnava Ekadashi tradition',
                'deity' => 'Vishnu',
                'fasting' => true,
                'aliases' => ['Vaishnava Mahadwadashi'],
            ];
            if ($this->shouldIncludeFestivalRules($rules, $selection)) {
                $this->appendDerivedFestival(
                    name: 'Mahadwadashi',
                    rules: $rules,
                    observanceDate: $date->toDateString(),
                    reason: $status,
                    festivals: $festivals,
                    festivalMeta: $festivalMeta,
                    addedFestivalKeys: $addedFestivalKeys,
                );
            }
        }

        $isAdhikaToday = (bool) (($todayDetails['Hindu_Calendar']['Is_Adhika'] ?? false));
        $isAdhikaYesterday = is_array($yesterdayDetails)
            ? (bool) (($yesterdayDetails['Hindu_Calendar']['Is_Adhika'] ?? false))
            : false;
        $isAdhikaTomorrow = (bool) (($tomorrowDetails['Hindu_Calendar']['Is_Adhika'] ?? false));

        if ($isAdhikaToday && !$isAdhikaYesterday) {
            $rules = [
                'type' => 'derived_adhika_month_boundary',
                'description' => 'Beginning of Purushottam Maas, the intercalary lunar month dedicated to Lord Vishnu',
                'deity' => 'Vishnu',
            ];
            if ($this->shouldIncludeFestivalRules($rules, $selection)) {
                $this->appendDerivedFestival(
                    name: 'Purushottam Maas Begins',
                    rules: $rules,
                    observanceDate: $date->toDateString(),
                    reason: 'adhika_month_begin',
                    festivals: $festivals,
                    festivalMeta: $festivalMeta,
                    addedFestivalKeys: $addedFestivalKeys,
                );
            }
        }

        if ($isAdhikaToday && !$isAdhikaTomorrow) {
            $rules = [
                'type' => 'derived_adhika_month_boundary',
                'description' => 'Conclusion of Purushottam Maas, the intercalary lunar month dedicated to Lord Vishnu',
                'deity' => 'Vishnu',
            ];
            if ($this->shouldIncludeFestivalRules($rules, $selection)) {
                $this->appendDerivedFestival(
                    name: 'Purushottam Maas Ends',
                    rules: $rules,
                    observanceDate: $date->toDateString(),
                    reason: 'adhika_month_end',
                    festivals: $festivals,
                    festivalMeta: $festivalMeta,
                    addedFestivalKeys: $addedFestivalKeys,
                );
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $festivals
     * @param array<int, array<string, mixed>> $festivalMeta
     * @param array<string, bool> $addedFestivalKeys
     */
    private function appendDerivedFestival(
        string $name,
        array $rules,
        string $observanceDate,
        string $reason,
        array &$festivals,
        array &$festivalMeta,
        array &$addedFestivalKeys
    ): void {
        if (isset($addedFestivalKeys[$name])) {
            return;
        }

        $resolved = [
            'festival_name' => $name,
            'standard_date' => $observanceDate,
            'observance_date' => $observanceDate,
            'observance_note' => null,
            'decision' => [
                'winning_reason' => $reason,
                'winning_score' => 1000,
            ],
        ];

        $festivals[] = $this->buildFestivalPayload($name, $rules, $resolved);
        $festivalMeta[] = [
            'raw_name' => $name,
            'adhika_only' => false,
            'is_ekadashi' => str_contains($name, 'Ekadashi'),
        ];
        $addedFestivalKeys[$name] = true;
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
            $allowsAdhika = ($rules['allows_adhika'] ?? false) || ($rules['allow_adhika'] ?? false);

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
            $matchedTithi = false;
            foreach ($ruleTithis as $rTithi) {
                if ($tithiNum === $rTithi) {
                    $matchedTithi = true;
                    break;
                }

                // If paksha is Both, check Krishna equivalent
                $rulePaksha = $this->resolveRulePakshaForCalendar($rules, (array) ($panchangDetails['Hindu_Calendar'] ?? []), 'Shukla');
                if ($rulePaksha === 'Both' && $tithiNum === ($rTithi + 15)) {
                    $matchedTithi = true;
                    break;
                }
            }

            if (!$matchedTithi) {
                return false;
            }
        }

        // Check paksha match
        if (isset($rules['paksha']) || isset($rules['paksha_amanta']) || isset($rules['paksha_purnimanta'])) {
            $rulePaksha = $this->resolveRulePakshaForCalendar($rules, (array) ($panchangDetails['Hindu_Calendar'] ?? []), 'Shukla');
            $rulePakshas = $rulePaksha === 'Both' ? ['Shukla', 'Krishna'] : (is_array($rulePaksha) ? $rulePaksha : [$rulePaksha]);
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
        if (isset($rules['nakshatra'], $panchangDetails['Nakshatra']['name'])
            && !$this->nakshatraRuleMatches((string) $rules['nakshatra'], (array) $panchangDetails['Nakshatra'])) {
            return false;
        }

        // Check weekday_in_month (e.g., Shravan Somvar)
        if (($rules['type'] ?? '') === 'weekday_in_month' && isset($rules['weekday'])) {
            $calendar = (array) ($panchangDetails['Hindu_Calendar'] ?? []);
            if (!$this->monthRuleMatches($rules, $calendar)) {
                return false;
            }
        }

        // Check nth_weekday_in_month (e.g., First Shravan Somvar)
        if (($rules['type'] ?? '') === 'nth_weekday_in_month' && isset($rules['weekday'], $rules['nth'])) {
            if ($date->dayOfWeek !== $rules['weekday']) {
                return false;
            }

            $nth = (int) $rules['nth'];
            $currentNth = (int) ceil($date->day / 7);
            if ($currentNth !== $nth) {
                return false;
            }

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
        $calendarType = strtolower((string) ($calendar['Calendar_Type'] ?? AstroCore::getConfig('panchang.defaults.calendar_type', 'amanta')));

        $allowedAmanta = array_values(array_filter(array_map(
            fn ($month): string => $this->normalizeMonthName((string) $month),
            (array) ($rules['allowed_months_amanta'] ?? [])
        ), fn (string $value): bool => $value !== ''));
        $allowedPurnimanta = array_values(array_filter(array_map(
            fn ($month): string => $this->normalizeMonthName((string) $month),
            (array) ($rules['allowed_months_purnimanta'] ?? [])
        ), fn (string $value): bool => $value !== ''));

        if ((string) ($rules['family'] ?? '') === 'phuldolotsava' && $allowedAmanta !== []) {
            return $amanta !== '' && in_array($amanta, $allowedAmanta, true);
        }

        if ($calendarType === 'purnimanta') {
            if ($allowedPurnimanta !== []) {
                return $purnimanta !== '' && in_array($purnimanta, $allowedPurnimanta, true);
            }

            if ($rulePurnimanta !== '') {
                return $rulePurnimanta === $purnimanta;
            }

            if ($allowedAmanta !== []) {
                return $amanta !== '' && in_array($amanta, $allowedAmanta, true);
            }

            if ($ruleAmanta !== '') {
                return $ruleAmanta === $amanta;
            }

            return true;
        }

        // Default: amanta
        if ($allowedAmanta !== []) {
            return $amanta !== '' && in_array($amanta, $allowedAmanta, true);
        }

        if ($ruleAmanta !== '') {
            return $ruleAmanta === $amanta;
        }

        if ($allowedPurnimanta !== []) {
            return $purnimanta !== '' && in_array($purnimanta, $allowedPurnimanta, true);
        }

        if ($rulePurnimanta !== '') {
            return $rulePurnimanta === $purnimanta;
        }

        return true;
    }

    /** Reject rules explicitly excluded for the active lunar month. */
    private function monthRuleExcluded(array $rules, array $calendar): bool
    {
        $amanta = $this->normalizeMonthName((string) ($calendar['Month_Amanta_En'] ?? $calendar['Month_Amanta'] ?? ''));
        $dynamicPurnimanta = $this->getDynamicPurnimantaName($rules, $calendar);
        $purnimanta = $this->normalizeMonthName($dynamicPurnimanta);
        $calendarType = strtolower((string) ($calendar['Calendar_Type'] ?? AstroCore::getConfig('panchang.defaults.calendar_type', 'amanta')));

        $excludedAmanta = array_map(fn ($month): string => $this->normalizeMonthName((string) $month), (array) ($rules['excluded_months_amanta'] ?? []));
        $excludedPurnimanta = array_map(fn ($month): string => $this->normalizeMonthName((string) $month), (array) ($rules['excluded_months_purnimanta'] ?? []));

        if ($calendarType === 'purnimanta' && $excludedPurnimanta !== []) {
            return in_array($purnimanta, $excludedPurnimanta, true);
        }

        if ($excludedAmanta !== []) {
            return in_array($amanta, $excludedAmanta, true);
        }

        return $excludedPurnimanta !== [] && in_array($purnimanta, $excludedPurnimanta, true);
    }

    /** Allow evening/night observances whose correct karmakala falls before the named-month sunrise. */
    private function canResolveAcrossMonthBoundary(array $rules, array $tomorrowCalendar, bool $isClassical): bool
    {
        if (!$isClassical || $tomorrowCalendar === []) {
            return false;
        }

        $karmakalaType = (string) ($rules['karmakala_type'] ?? 'sunrise');
        if (in_array($karmakalaType, ['sunrise', 'arunodaya'], true)) {
            return false;
        }

        return $this->monthRuleMatches($rules, $tomorrowCalendar);
    }

    private function resolveRulePakshaForCalendar(array $rules, array $calendar, string $fallbackPaksha = 'Shukla'): array|string
    {
        $calendarType = strtolower((string) ($calendar['Calendar_Type'] ?? AstroCore::getConfig('panchang.defaults.calendar_type', 'amanta')));
        if ($calendarType === 'purnimanta' && array_key_exists('paksha_purnimanta', $rules)) {
            return $rules['paksha_purnimanta'];
        }

        if ($calendarType !== 'purnimanta' && array_key_exists('paksha_amanta', $rules)) {
            return $rules['paksha_amanta'];
        }

        return $rules['paksha'] ?? $fallbackPaksha;
    }

    /**
     * Dynamically determine the expected Purnimanta month name based on the festival rule's paksha.
     * This fixes edge cases where the daily snapshot's Purnimanta month (from a Krishna sunrise)
     * mismatches a Shukla festival occurring later that same day.
     */
    private function getDynamicPurnimantaName(array $rules, array $calendar): string
    {
        $basePurnimanta = (string) ($calendar['Month_Purnimanta_En'] ?? $calendar['Month_Purnimanta'] ?? '');

        $resolvedRulePaksha = $this->resolveRulePakshaForCalendar($rules, $calendar, '');
        if ($resolvedRulePaksha !== '' && isset($calendar['Amanta_Index'])) {
            $rulePakshas = is_array($resolvedRulePaksha) ? $resolvedRulePaksha : [$resolvedRulePaksha];
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
