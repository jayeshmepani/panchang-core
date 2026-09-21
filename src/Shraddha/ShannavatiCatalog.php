<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Shraddha;

/**
 * Canonical Ṣaṇṇavati taxonomy and source-profile rule tables.
 *
 * IMPORTANT: the traditional 96 is a nominal taxonomy. Runtime manifestations
 * are not forced to exactly 96 physical dates: adhika-masa and repeated yoga
 * manifestations can legitimately change the occurrence count.
 */
final class ShannavatiCatalog
{
    public const array NOMINAL_COUNTS = [
        'amavasya' => 12,
        'yugadi' => 4,
        'manvadi' => 14,
        'sankranti' => 12,
        'vaidhriti' => 12,
        'vyatipata' => 12,
        'mahalaya' => 15,
        'ashtaka' => 5,
        'anvashtaka' => 5,
        'purvedyu' => 5,
    ];

    public const array AMAVASYA_MONTHS = [
        'Chaitra', 'Vaishakha', 'Jyeshtha', 'Ashadha', 'Shravana', 'Bhadrapada',
        'Ashvina', 'Kartika', 'Margashirsha', 'Pausha', 'Magha', 'Phalguna',
    ];

    /** Public Yoga_Windows indices are 1-based (Yoga enum values themselves are 0-based). */
    public const array NITYA_YOGA_WINDOW_INDICES = [
        'vyatipata' => 17,
        'vaidhriti' => 27,
    ];

    public const array SANKRANTIS = [
        0 => ['key' => 'mesha', 'name' => 'Mesha Sankranti'],
        1 => ['key' => 'vrishabha', 'name' => 'Vrishabha Sankranti'],
        2 => ['key' => 'mithuna', 'name' => 'Mithuna Sankranti'],
        3 => ['key' => 'karka', 'name' => 'Karka Sankranti'],
        4 => ['key' => 'simha', 'name' => 'Simha Sankranti'],
        5 => ['key' => 'kanya', 'name' => 'Kanya Sankranti'],
        6 => ['key' => 'tula', 'name' => 'Tula Sankranti'],
        7 => ['key' => 'vrischika', 'name' => 'Vrischika Sankranti'],
        8 => ['key' => 'dhanu', 'name' => 'Dhanu Sankranti'],
        9 => ['key' => 'makara', 'name' => 'Makara Sankranti'],
        10 => ['key' => 'kumbha', 'name' => 'Kumbha Sankranti'],
        11 => ['key' => 'meena', 'name' => 'Meena Sankranti'],
    ];

    /** Four stable Yugadi tithi slots; yuga-name assignments have textual variants. */
    public const array YUGADI_DHARMA_SINDHU = [
        [
            'id' => 'shannavati.yugadi.kartika_shukla_navami',
            'label' => 'Krita/Satya Yugadi Shraddha',
            'aliases' => ['Sata Yuga Diwas', 'Satya Yuga Diwas', 'Krita Yuga Diwas', 'Akshaya Navami'],
            'month_amanta' => 'Kartika', 'month_purnimanta' => 'Kartika',
            'paksha' => 'Shukla', 'tithi' => 9, 'karmakala' => 'purvahna',
        ],
        [
            'id' => 'shannavati.yugadi.vaishakha_shukla_tritiya',
            'label' => 'Treta Yugadi Shraddha',
            'aliases' => ['Treta Yuga Diwas', 'Akshaya Tritiya'],
            'month_amanta' => 'Vaishakha', 'month_purnimanta' => 'Vaishakha',
            'paksha' => 'Shukla', 'tithi' => 3, 'karmakala' => 'purvahna',
        ],
        [
            'id' => 'shannavati.yugadi.maghakrishna_amavasya',
            'label' => 'Magha Krishna Amavasya Yugadi Shraddha',
            'aliases' => ['Dwapara Yuga Diwas', 'Dvapara Yugadi', 'Mauni Amavasya'],
            'month_amanta' => 'Magha', 'month_purnimanta' => 'Phalguna',
            'paksha' => 'Krishna', 'tithi' => 15, 'karmakala' => 'aparahna',
        ],
        [
            'id' => 'shannavati.yugadi.bhadrapada_krishna_trayodashi',
            'label' => 'Bhadrapada Krishna Trayodashi Yugadi Shraddha',
            'aliases' => ['Kali Yuga Diwas', 'Kali Yugadi'],
            'month_amanta' => 'Bhadrapada', 'month_purnimanta' => 'Ashvina',
            'paksha' => 'Krishna', 'tithi' => 13, 'karmakala' => 'aparahna',
        ],
    ];

    /**
     * Dharma-sindhu Manvadi tithi enumeration. Individual Manu names are kept
     * secondary because name-to-tithi assignments vary across source traditions.
     */
    public const array MANVADI_DHARMA_SINDHU = [
        ['id' => 'shannavati.manvadi.01', 'label' => 'Manvadi Shraddha - Chaitra Shukla Tritiya', 'aliases' => ['Swayambhuva Manvadi'], 'month_amanta' => 'Chaitra', 'month_purnimanta' => 'Chaitra', 'paksha' => 'Shukla', 'tithi' => 3],
        ['id' => 'shannavati.manvadi.02', 'label' => 'Manvadi Shraddha - Chaitra Purnima', 'aliases' => ['Swarochisha Manvadi'], 'month_amanta' => 'Chaitra', 'month_purnimanta' => 'Chaitra', 'paksha' => 'Shukla', 'tithi' => 15],
        ['id' => 'shannavati.manvadi.03', 'label' => 'Manvadi Shraddha - Jyeshtha Purnima', 'aliases' => ['Vaivaswata Manvadi'], 'month_amanta' => 'Jyeshtha', 'month_purnimanta' => 'Jyeshtha', 'paksha' => 'Shukla', 'tithi' => 15],
        ['id' => 'shannavati.manvadi.04', 'label' => 'Manvadi Shraddha - Ashadha Shukla Dashami', 'aliases' => ['Raivata Manvadi'], 'month_amanta' => 'Ashadha', 'month_purnimanta' => 'Ashadha', 'paksha' => 'Shukla', 'tithi' => 10],
        ['id' => 'shannavati.manvadi.05', 'label' => 'Manvadi Shraddha - Ashadha Purnima', 'aliases' => ['Chakshusha Manvadi'], 'month_amanta' => 'Ashadha', 'month_purnimanta' => 'Ashadha', 'paksha' => 'Shukla', 'tithi' => 15],
        ['id' => 'shannavati.manvadi.06', 'label' => 'Manvadi Shraddha - Shravana Krishna Ashtami', 'aliases' => ['Indra Savarni Manvadi', 'Bhadrapada Krishna Ashtami Manvadi'], 'month_amanta' => 'Shravana', 'month_purnimanta' => 'Bhadrapada', 'paksha' => 'Krishna', 'tithi' => 8],
        ['id' => 'shannavati.manvadi.07', 'label' => 'Manvadi Shraddha - Bhadrapada Shukla Tritiya', 'aliases' => ['Rudra Savarni Manvadi'], 'month_amanta' => 'Bhadrapada', 'month_purnimanta' => 'Bhadrapada', 'paksha' => 'Shukla', 'tithi' => 3],
        ['id' => 'shannavati.manvadi.08', 'label' => 'Manvadi Shraddha - Ashvina Shukla Navami', 'aliases' => ['Daksha Savarni Manvadi'], 'month_amanta' => 'Ashvina', 'month_purnimanta' => 'Ashvina', 'paksha' => 'Shukla', 'tithi' => 9],
        ['id' => 'shannavati.manvadi.09', 'label' => 'Manvadi Shraddha - Kartika Shukla Dwadashi', 'aliases' => ['Tamasa Manvadi'], 'month_amanta' => 'Kartika', 'month_purnimanta' => 'Kartika', 'paksha' => 'Shukla', 'tithi' => 12],
        ['id' => 'shannavati.manvadi.10', 'label' => 'Manvadi Shraddha - Kartika Purnima', 'aliases' => ['Uttama Manvadi'], 'month_amanta' => 'Kartika', 'month_purnimanta' => 'Kartika', 'paksha' => 'Shukla', 'tithi' => 15],
        ['id' => 'shannavati.manvadi.11', 'label' => 'Manvadi Shraddha - Pausha Shukla Ekadashi', 'aliases' => ['Dharma Savarni Manvadi'], 'month_amanta' => 'Pausha', 'month_purnimanta' => 'Pausha', 'paksha' => 'Shukla', 'tithi' => 11],
        ['id' => 'shannavati.manvadi.12', 'label' => 'Manvadi Shraddha - Magha Shukla Saptami', 'aliases' => ['Brahma Savarni Manvadi'], 'month_amanta' => 'Magha', 'month_purnimanta' => 'Magha', 'paksha' => 'Shukla', 'tithi' => 7],
        ['id' => 'shannavati.manvadi.13', 'label' => 'Manvadi Shraddha - Phalguna Purnima', 'aliases' => ['Savarni Manvadi'], 'month_amanta' => 'Phalguna', 'month_purnimanta' => 'Phalguna', 'paksha' => 'Shukla', 'tithi' => 15],
        ['id' => 'shannavati.manvadi.14', 'label' => 'Manvadi Shraddha - Phalguna Amavasya', 'aliases' => ['Phalguna Amavasya Manvadi'], 'month_amanta' => 'Phalguna', 'month_purnimanta' => 'Chaitra', 'paksha' => 'Krishna', 'tithi' => 15],
    ];

    /** Common modern named-Manvadi mapping used for ordinary festival labels. */
    public const array MANVADI_MODERN_NAMED = [
        ['name' => 'Swayambhuva Manvadi', 'month_amanta' => 'Chaitra', 'month_purnimanta' => 'Chaitra', 'paksha' => 'Shukla', 'tithi' => 3],
        ['name' => 'Swarochisha Manvadi', 'month_amanta' => 'Chaitra', 'month_purnimanta' => 'Chaitra', 'paksha' => 'Shukla', 'tithi' => 15],
        ['name' => 'Vaivaswata Manvadi', 'month_amanta' => 'Jyeshtha', 'month_purnimanta' => 'Jyeshtha', 'paksha' => 'Shukla', 'tithi' => 15],
        ['name' => 'Raivata Manvadi', 'month_amanta' => 'Ashadha', 'month_purnimanta' => 'Ashadha', 'paksha' => 'Shukla', 'tithi' => 10],
        ['name' => 'Chakshusha Manvadi', 'month_amanta' => 'Ashadha', 'month_purnimanta' => 'Ashadha', 'paksha' => 'Shukla', 'tithi' => 15],
        ['name' => 'Indra Savarni Manvadi', 'month_amanta' => 'Shravana', 'month_purnimanta' => 'Bhadrapada', 'paksha' => 'Krishna', 'tithi' => 8],
        ['name' => 'Daiva Savarni Manvadi', 'month_amanta' => 'Shravana', 'month_purnimanta' => 'Bhadrapada', 'paksha' => 'Krishna', 'tithi' => 15],
        ['name' => 'Rudra Savarni Manvadi', 'month_amanta' => 'Bhadrapada', 'month_purnimanta' => 'Bhadrapada', 'paksha' => 'Shukla', 'tithi' => 3],
        ['name' => 'Daksha Savarni Manvadi', 'month_amanta' => 'Ashvina', 'month_purnimanta' => 'Ashvina', 'paksha' => 'Shukla', 'tithi' => 9],
        ['name' => 'Tamasa Manvadi', 'month_amanta' => 'Kartika', 'month_purnimanta' => 'Kartika', 'paksha' => 'Shukla', 'tithi' => 12],
        ['name' => 'Uttama Manvadi', 'month_amanta' => 'Kartika', 'month_purnimanta' => 'Kartika', 'paksha' => 'Shukla', 'tithi' => 15],
        ['name' => 'Dharma Savarni Manvadi', 'month_amanta' => 'Pausha', 'month_purnimanta' => 'Pausha', 'paksha' => 'Shukla', 'tithi' => 11],
        ['name' => 'Brahma Savarni Manvadi', 'month_amanta' => 'Magha', 'month_purnimanta' => 'Magha', 'paksha' => 'Shukla', 'tithi' => 7],
        ['name' => 'Savarni Manvadi', 'month_amanta' => 'Phalguna', 'month_purnimanta' => 'Phalguna', 'paksha' => 'Shukla', 'tithi' => 15],
    ];

    /** Five Aṣṭakā cycles of the later Ṣaṇṇavati nibandha enumeration. */
    public const array ASHTAKA_MONTHS = [
        ['key' => 'bhadrapada', 'name' => 'Bhadrapada', 'month_amanta' => 'Bhadrapada', 'month_purnimanta' => 'Ashvina'],
        ['key' => 'margashirsha', 'name' => 'Margashirsha', 'month_amanta' => 'Margashirsha', 'month_purnimanta' => 'Pausha'],
        ['key' => 'pausha', 'name' => 'Pausha', 'month_amanta' => 'Pausha', 'month_purnimanta' => 'Magha'],
        ['key' => 'magha', 'name' => 'Magha', 'month_amanta' => 'Magha', 'month_purnimanta' => 'Phalguna'],
        ['key' => 'phalguna', 'name' => 'Phalguna', 'month_amanta' => 'Phalguna', 'month_purnimanta' => 'Chaitra'],
    ];

    public static function nominalCount(): int
    {
        return array_sum(self::NOMINAL_COUNTS);
    }

    /**
     * Expand the traditional nominal taxonomy to 96 stable catalogue IDs.
     *
     * The twelve Vyatipata and twelve Vaidhriti IDs are taxonomy slots only;
     * runtime nitya-yoga manifestations are intentionally not forced into a
     * one-to-one slot mapping.
     *
     * @return list<string>
     */
    public static function nominalCanonicalIds(): array
    {
        $ids = [];

        foreach (self::AMAVASYA_MONTHS as $month) {
            $ids[] = 'shannavati.amavasya.' . strtolower($month);
        }

        foreach (self::YUGADI_DHARMA_SINDHU as $rule) {
            $ids[] = (string) $rule['id'];
        }

        foreach (self::MANVADI_DHARMA_SINDHU as $rule) {
            $ids[] = (string) $rule['id'];
        }

        foreach (self::SANKRANTIS as $rule) {
            $ids[] = 'shannavati.sankranti.' . $rule['key'];
        }

        for ($i = 1; $i <= 12; ++$i) {
            $ids[] = sprintf('shannavati.vaidhriti.nominal.%02d', $i);
            $ids[] = sprintf('shannavati.vyatipata.nominal.%02d', $i);
        }

        for ($i = 1; $i <= 15; ++$i) {
            $ids[] = sprintf('shannavati.mahalaya.%02d', $i);
        }

        foreach (self::ASHTAKA_MONTHS as $month) {
            $ids[] = 'shannavati.ashtaka.' . $month['key'];
            $ids[] = 'shannavati.anvashtaka.' . $month['key'];
            $ids[] = 'shannavati.purvedyu.' . $month['key'];
        }

        return $ids;
    }

    /** @return list<array<string,mixed>> */
    public static function manvadiRules(ShannavatiTraditionProfile $profile): array
    {
        if ($profile === ShannavatiTraditionProfile::DharmaSindhu) {
            return self::MANVADI_DHARMA_SINDHU;
        }

        return array_map(
            static fn(array $rule, int $index): array => [
                'id' => sprintf('shannavati.manvadi.%02d', $index + 1),
                'label' => $rule['name'],
                'aliases' => [$rule['name']],
                ...$rule,
            ],
            self::MANVADI_MODERN_NAMED,
            array_keys(self::MANVADI_MODERN_NAMED)
        );
    }
}
