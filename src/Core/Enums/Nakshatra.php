<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

/**
 * Nakṣatra Enumeration.
 *
 * Represents the 27 lunar mansions in Vedic astrology.
 * Each nakṣatra spans 13°20' of the zodiac (360° / 27 = 13.333...°).
 */
enum Nakshatra: int
{
    case Ashwini = 0;
    case Bharani = 1;
    case Krittika = 2;
    case Rohini = 3;
    case Mrigashira = 4;
    case Ardra = 5;
    case Punarvasu = 6;
    case Pushya = 7;
    case Ashlesha = 8;
    case Magha = 9;
    case PurvaPhalguni = 10;
    case UttaraPhalguni = 11;
    case Hasta = 12;
    case Chitra = 13;
    case Swati = 14;
    case Vishakha = 15;
    case Anuradha = 16;
    case Jyeshtha = 17;
    case Mula = 18;
    case PurvaAshadha = 19;
    case UttaraAshadha = 20;
    case Shravana = 21;
    case Dhanishta = 22;
    case Shatabhisha = 23;
    case PurvaBhadrapada = 24;
    case UttaraBhadrapada = 25;
    case Revati = 26;

    /** Get Sanskrit name */
    public function getName(): string
    {
        return match ($this) {
            self::Ashwini => 'Ashwini',
            self::Bharani => 'Bharani',
            self::Krittika => 'Krittika',
            self::Rohini => 'Rohini',
            self::Mrigashira => 'Mrigashira',
            self::Ardra => 'Ardra',
            self::Punarvasu => 'Punarvasu',
            self::Pushya => 'Pushya',
            self::Ashlesha => 'Ashlesha',
            self::Magha => 'Magha',
            self::PurvaPhalguni => 'Purva Phalguni',
            self::UttaraPhalguni => 'Uttara Phalguni',
            self::Hasta => 'Hasta',
            self::Chitra => 'Chitra',
            self::Swati => 'Swati',
            self::Vishakha => 'Vishakha',
            self::Anuradha => 'Anuradha',
            self::Jyeshtha => 'Jyeshtha',
            self::Mula => 'Mula',
            self::PurvaAshadha => 'Purva Ashadha',
            self::UttaraAshadha => 'Uttara Ashadha',
            self::Shravana => 'Shravana',
            self::Dhanishta => 'Dhanishta',
            self::Shatabhisha => 'Shatabhisha',
            self::PurvaBhadrapada => 'Purva Bhadrapada',
            self::UttaraBhadrapada => 'Uttara Bhadrapada',
            self::Revati => 'Revati',
        };
    }

    /** Get deity of the nakṣatra */
    public function getDeity(): string
    {
        return match ($this) {
            self::Ashwini => 'Ashwini Kumars',
            self::Bharani => 'Yama',
            self::Krittika => 'Agni',
            self::Rohini => 'Brahma',
            self::Mrigashira => 'Soma',
            self::Ardra => 'Rudra',
            self::Punarvasu => 'Aditi',
            self::Pushya => 'Brihaspati',
            self::Ashlesha => 'Nagas',
            self::Magha => 'Pitris',
            self::PurvaPhalguni => 'Bhaga',
            self::UttaraPhalguni => 'Aryaman',
            self::Hasta => 'Savitar',
            self::Chitra => 'Vishvakarma',
            self::Swati => 'Vayu',
            self::Vishakha => 'Indra-Agni',
            self::Anuradha => 'Mitra',
            self::Jyeshtha => 'Indra',
            self::Mula => 'Nirriti',
            self::PurvaAshadha => 'Apas',
            self::UttaraAshadha => 'Vishvadevas',
            self::Shravana => 'Vishnu',
            self::Dhanishta => 'Vasus',
            self::Shatabhisha => 'Varuna',
            self::PurvaBhadrapada => 'Aja Ekapada',
            self::UttaraBhadrapada => 'Ahir Budhnya',
            self::Revati => 'Pushan',
        };
    }

    /** Get ruling planet */
    public function getRulingPlanet(): string
    {
        $planets = ['Ketu', 'Venus', 'Sun', 'Moon', 'Mars', 'Rahu', 'Jupiter', 'Saturn', 'Mercury'];
        return $planets[$this->value % 9];
    }

    /** Get Vedic symbol */
    public function getSymbol(): string
    {
        return match ($this) {
            self::Ashwini => 'Horse Head',
            self::Bharani => 'Yoni',
            self::Krittika => 'Knife',
            self::Rohini => 'Cart',
            self::Mrigashira => 'Deer Head',
            self::Ardra => 'Teardrop',
            self::Punarvasu => 'Bow',
            self::Pushya => 'Cow Udder',
            self::Ashlesha => 'Serpent',
            self::Magha => 'Royal Throne',
            self::PurvaPhalguni => 'Bed',
            self::UttaraPhalguni => 'Bed',
            self::Hasta => 'Hand',
            self::Chitra => 'Pearl',
            self::Swati => 'Coral',
            self::Vishakha => 'Triumphal Gateway',
            self::Anuradha => 'Lotus',
            self::Jyeshtha => 'Earring',
            self::Mula => 'Bunch of Roots',
            self::PurvaAshadha => 'Winnowing Basket',
            self::UttaraAshadha => 'Elephant Tusk',
            self::Shravana => 'Three Footprints',
            self::Dhanishta => 'Drum',
            self::Shatabhisha => 'Thousand Stars',
            self::PurvaBhadrapada => 'Sword',
            self::UttaraBhadrapada => 'Back Legs of Bed',
            self::Revati => 'Fish',
        };
    }

    /**
     * Get Vedic longitude range.
     *
     * @return array{start: float, end: float} Longitude range in degrees
     */
    public function getLongitudeRange(): array
    {
        $start = $this->value * 13.3333333333;
        $end = ($this->value + 1) * 13.3333333333;

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Get nakṣatra from longitude.
     *
     * @param float $longitude Longitude in degrees (0-360)
     *
     * @return self Nakṣatra instance
     */
    public static function fromLongitude(float $longitude): self
    {
        $normalized = fmod($longitude, 360.0);
        if ($normalized < 0) {
            $normalized += 360.0;
        }

        $index = (int) floor($normalized / 13.3333333333);
        return self::from($index);
    }

    /**
     * Get pada (quarter) from longitude.
     *
     * @param float $longitude Longitude in degrees
     *
     * @return int Pada number (1-4)
     */
    public static function getPada(float $longitude): int
    {
        $normalized = fmod($longitude, 360.0);
        if ($normalized < 0) {
            $normalized += 360.0;
        }

        $nakshatraIndex = (int) floor($normalized / 13.3333333333);
        $nakshatraStart = $nakshatraIndex * 13.3333333333;
        $progress = $normalized - $nakshatraStart;

        return (int) floor($progress / 3.3333333333) + 1;
    }
}
