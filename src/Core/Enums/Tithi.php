<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Tithi Enumeration.
 *
 * Represents the 30 lunar days in a Hindu lunar month.
 * Each tithi is completed when the Moon gains 12° on the Sun (360° / 30 = 12°).
 */
enum Tithi: int
{
    case ShuklaPratipada = 1;
    case ShuklaDwitiya = 2;
    case ShuklaTritiya = 3;
    case ShuklaChaturthi = 4;
    case ShuklaPanchami = 5;
    case ShuklaShashthi = 6;
    case ShuklaSaptami = 7;
    case ShuklaAshtami = 8;
    case ShuklaNavami = 9;
    case ShuklaDashami = 10;
    case ShuklaEkadashi = 11;
    case ShuklaDwadashi = 12;
    case ShuklaTrayodashi = 13;
    case ShuklaChaturdashi = 14;
    case Purnima = 15;
    case KrishnaPratipada = 16;
    case KrishnaDwitiya = 17;
    case KrishnaTritiya = 18;
    case KrishnaChaturthi = 19;
    case KrishnaPanchami = 20;
    case KrishnaShashthi = 21;
    case KrishnaSaptami = 22;
    case KrishnaAshtami = 23;
    case KrishnaNavami = 24;
    case KrishnaDashami = 25;
    case KrishnaEkadashi = 26;
    case KrishnaDwadashi = 27;
    case KrishnaTrayodashi = 28;
    case KrishnaChaturdashi = 29;
    case Amavasya = 30;

    /** Get Sanskrit name */
    public function getName(?string $locale = null): string
    {
        return Localization::translate('Tithi', $this->getNormalizedIndex(), $locale);
    }

    /** Get pakṣa (fortnight) */
    public function getPaksha(): Paksha
    {
        return $this->value <= 15 ? Paksha::Shukla : Paksha::Krishna;
    }

    /** Get normalized index (1-15) */
    public function getNormalizedIndex(): int
    {
        return $this->value <= 15 ? $this->value : $this->value - 15;
    }

    /** Check if this is Ekadashi */
    public function isEkadashi(): bool
    {
        return in_array($this, [self::ShuklaEkadashi, self::KrishnaEkadashi], true);
    }

    /** Check if this is Purnima or Amavasya */
    public function isPurnimaOrAmavasya(): bool
    {
        return in_array($this, [self::Purnima, self::Amavasya], true);
    }

    /** Check if this is Chaturdashi */
    public function isChaturdashi(): bool
    {
        return in_array($this, [self::ShuklaChaturdashi, self::KrishnaChaturdashi], true);
    }

    /**
     * Get tithi from Sun-Moon longitude difference.
     *
     * @param float $sunLon Sun longitude in degrees
     * @param float $moonLon Moon longitude in degrees
     *
     * @return self Tithi instance
     */
    public static function fromLongitudes(float $sunLon, float $moonLon): self
    {
        $diff = fmod($moonLon - $sunLon, 360.0);
        if ($diff < 0) {
            $diff += 360.0;
        }

        $index = (int) floor($diff / 12.0) + 1;
        return self::from($index);
    }

    /**
     * Get fraction remaining in tithi.
     *
     * @param float $sunLon Sun longitude in degrees
     * @param float $moonLon Moon longitude in degrees
     *
     * @return float Fraction remaining (0.0-1.0)
     */
    public static function getFractionRemaining(float $sunLon, float $moonLon): float
    {
        $diff = fmod($moonLon - $sunLon, 360.0);
        if ($diff < 0) {
            $diff += 360.0;
        }

        return 1.0 - (fmod($diff, 12.0) / 12.0);
    }
}
