<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Muhūrta (48-Minute Period) Enumeration.
 *
 * This package uses the 15 day-name and 15 night-name system.
 */
enum Muhurta: int
{
    // Day Muhurtas
    case Rudra = 0;
    case Sarpa = 1;
    case Mitra = 2;
    case Pitri = 3;
    case Vasu = 4;
    case Vara = 5;
    case Vishvedeva = 6;
    case Vidhi = 7;
    case Brahma = 8;
    case Indra = 9;
    case Indragni = 10;
    case Daitya = 11;
    case Varuna = 12;
    case Aryaman = 13;
    case Bhaga = 14;

    // Night Muhurtas
    case Ishvara = 15;
    case Ajapada = 16;
    case Ahirbudhnya = 17;
    case Pushya = 18;
    case Nasatya = 19;
    case Yama = 20;
    case Vahni = 21;
    case Dhala = 22;
    case Shashi = 23;
    case Aditya = 24;
    case Guru = 25;
    case Acyuta = 26;
    case Arka = 27;
    case Tvashta = 28;
    case Vayu = 29;

    /** Get Sanskrit name */
    public function getName(?string $locale = null): string
    {
        return Localization::translate('Muhurta', $this->value, $locale);
    }

    /**
     * Get day sequence (15 muhurtas).
     *
     * @return array<int, self> Day muhurtas
     */
    public static function getDaySequence(): array
    {
        return array_slice(self::cases(), 0, 15);
    }

    /**
     * Get night sequence (15 muhurtas).
     *
     * @return array<int, self> Night muhurtas
     */
    public static function getNightSequence(): array
    {
        return array_slice(self::cases(), 15, 15);
    }
}
