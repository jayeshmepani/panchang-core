<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

/**
 * Saṃvatsara (60-Year Jupiter Cycle) Enumeration.
 *
 * Represents the 60 years in the Jupiter cycle.
 * Each year has a specific name and characteristics.
 */
enum Samvatsara: int
{
    case Prabhava = 0;
    case Vibhava = 1;
    case Shukla = 2;
    case Pramoda = 3;
    case Prajapati = 4;
    case Angirasa = 5;
    case Srimukha = 6;
    case Bhava = 7;
    case Yuva = 8;
    case Dhata = 9;
    case Ishvara = 10;
    case Bahudhanya = 11;
    case Pramathi = 12;
    case Vikrama = 13;
    case Vrisha = 14;
    case Chitrabhanu = 15;
    case Svabhanu = 16;
    case Tarana = 17;
    case Parthiva = 18;
    case Vyaya = 19;
    case Sarvajit = 20;
    case Sarvadhari = 21;
    case Virodhi = 22;
    case Vikriti = 23;
    case Khara = 24;
    case Nandana = 25;
    case Vijaya = 26;
    case Jaya = 27;
    case Manmatha = 28;
    case Durmukhi = 29;
    case Hevilambi = 30;
    case Vilambi = 31;
    case Vikari = 32;
    case Sharvari = 33;
    case Plava = 34;
    case Shubhakritu = 35;
    case Shobhakritu = 36;
    case Krodhi = 37;
    case Vishvavasu = 38;
    case Parabhava = 39;
    case Plavanga = 40;
    case Kilaka = 41;
    case Saumya = 42;
    case Sadharana = 43;
    case Virodhikritu = 44;
    case Paritapi = 45;
    case Pramadi = 46;
    case Ananda = 47;
    case Rakshasa = 48;
    case Nala = 49;
    case Pingala = 50;
    case Kalayukti = 51;
    case Siddharthi = 52;
    case Raudri = 53;
    case Durmati = 54;
    case Dundubhi = 55;
    case Rudhirodgari = 56;
    case Raktakshi = 57;
    case Krodhana = 58;
    case Akshaya = 59;

    /** Get Sanskrit name */
    public function getName(): string
    {
        return match ($this) {
            self::Prabhava => 'Prabhava',
            self::Vibhava => 'Vibhava',
            self::Shukla => 'Śukla',
            self::Pramoda => 'Pramoda',
            self::Prajapati => 'Prajāpati',
            self::Angirasa => 'Aṅgirasa',
            self::Srimukha => 'Śrīmukha',
            self::Bhava => 'Bhava',
            self::Yuva => 'Yuva',
            self::Dhata => 'Dhātā',
            self::Ishvara => 'Īśvara',
            self::Bahudhanya => 'Bahudhanya',
            self::Pramathi => 'Pramathi',
            self::Vikrama => 'Vikrama',
            self::Vrisha => 'Vṛiṣha',
            self::Chitrabhanu => 'Chitrabhānu',
            self::Svabhanu => 'Svabhānu',
            self::Tarana => 'Tārana',
            self::Parthiva => 'Pārthiva',
            self::Vyaya => 'Vyaya',
            self::Sarvajit => 'Sarvajit',
            self::Sarvadhari => 'Sarvadhāri',
            self::Virodhi => 'Virodhi',
            self::Vikriti => 'Vikṛiti',
            self::Khara => 'Khara',
            self::Nandana => 'Nandana',
            self::Vijaya => 'Vijaya',
            self::Jaya => 'Jaya',
            self::Manmatha => 'Manmatha',
            self::Durmukhi => 'Durmukhi',
            self::Hevilambi => 'Hevilambi',
            self::Vilambi => 'Vilambi',
            self::Vikari => 'Vikāri',
            self::Sharvari => 'Śarvari',
            self::Plava => 'Plava',
            self::Shubhakritu => 'Śubhakṛtu',
            self::Shobhakritu => 'Śobhakṛtu',
            self::Krodhi => 'Krodhi',
            self::Vishvavasu => 'Viśvāvasu',
            self::Parabhava => 'Parābhava',
            self::Plavanga => 'Plavanga',
            self::Kilaka => 'Kīlaka',
            self::Saumya => 'Saumya',
            self::Sadharana => 'Sādhāraṇa',
            self::Virodhikritu => 'Virodhikṛtu',
            self::Paritapi => 'Paritāpi',
            self::Pramadi => 'Pramādi',
            self::Ananda => 'Ānanda',
            self::Rakshasa => 'Rākṣasa',
            self::Nala => 'Nala',
            self::Pingala => 'Piṅgala',
            self::Kalayukti => 'Kalāyukti',
            self::Siddharthi => 'Siddhārthi',
            self::Raudri => 'Raudri',
            self::Durmati => 'Durmati',
            self::Dundubhi => 'Dundubhi',
            self::Rudhirodgari => 'Rudhirodgāri',
            self::Raktakshi => 'Rākṣakṣi',
            self::Krodhana => 'Krodhana',
            self::Akshaya => 'Akṣaya',
        };
    }

    /**
     * Get saṃvatsara from year number.
     *
     * @param int $year Year number (1-60)
     *
     * @return self Samvatsara instance
     */
    public static function fromYear(int $year): self
    {
        return self::from(($year - 1) % 60);
    }

    /**
     * Get current saṃvatsara from Gregorian year.
     *
     * @param int $gregorianYear Gregorian year
     *
     * @return self Samvatsara instance
     */
    public static function fromGregorianYear(int $gregorianYear): self
    {
        // Reference: 1987 CE = Prabhava (year 1)
        $referenceYear = 1987;
        $yearsSinceReference = $gregorianYear - $referenceYear;
        $samvatsaraIndex = $yearsSinceReference % 60;

        if ($samvatsaraIndex < 0) {
            $samvatsaraIndex += 60;
        }

        return self::from($samvatsaraIndex);
    }
}
