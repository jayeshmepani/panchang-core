<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Types;

/**
 * Tithi (Lunar Day) Enumeration.
 *
 * Represents the 30 tithis in a lunar month:
 * - Śukla Pakṣa: Pratipada to Pūrṇimā (1-15)
 * - Kṛṣṇa Pakṣa: Pratipada to Amāvāsyā (16-30)
 */
enum Tithi: int
{
    case PRATIPADA_SHUKLA = 1;
    case DWITIYA_SHUKLA = 2;
    case TRITIYA_SHUKLA = 3;
    case CHATURTHI_SHUKLA = 4;
    case PANCHAMI_SHUKLA = 5;
    case SHASHTHI_SHUKLA = 6;
    case SAPTAMI_SHUKLA = 7;
    case ASHTAMI_SHUKLA = 8;
    case NAVAMI_SHUKLA = 9;
    case DASHAMI_SHUKLA = 10;
    case EKADASHI_SHUKLA = 11;
    case DWADASHI_SHUKLA = 12;
    case TRAYODASHI_SHUKLA = 13;
    case CHATURDASHI_SHUKLA = 14;
    case PURNIMA = 15;
    case PRATIPADA_KRISHNA = 16;
    case DWITIYA_KRISHNA = 17;
    case TRITIYA_KRISHNA = 18;
    case CHATURTHI_KRISHNA = 19;
    case PANCHAMI_KRISHNA = 20;
    case SHASHTHI_KRISHNA = 21;
    case SAPTAMI_KRISHNA = 22;
    case ASHTAMI_KRISHNA = 23;
    case NAVAMI_KRISHNA = 24;
    case DASHAMI_KRISHNA = 25;
    case EKADASHI_KRISHNA = 26;
    case DWADASHI_KRISHNA = 27;
    case TRAYODASHI_KRISHNA = 28;
    case CHATURDASHI_KRISHNA = 29;
    case AMAVASYA = 30;

    /** Get tithi name */
    public function getName(): string
    {
        return match ($this) {
            self::PRATIPADA_SHUKLA, self::PRATIPADA_KRISHNA => 'Pratipada',
            self::DWITIYA_SHUKLA, self::DWITIYA_KRISHNA => 'Dwitiya',
            self::TRITIYA_SHUKLA, self::TRITIYA_KRISHNA => 'Tritiya',
            self::CHATURTHI_SHUKLA, self::CHATURTHI_KRISHNA => 'Chaturthi',
            self::PANCHAMI_SHUKLA, self::PANCHAMI_KRISHNA => 'Panchami',
            self::SHASHTHI_SHUKLA, self::SHASHTHI_KRISHNA => 'Shashthi',
            self::SAPTAMI_SHUKLA, self::SAPTAMI_KRISHNA => 'Saptami',
            self::ASHTAMI_SHUKLA, self::ASHTAMI_KRISHNA => 'Ashtami',
            self::NAVAMI_SHUKLA, self::NAVAMI_KRISHNA => 'Navami',
            self::DASHAMI_SHUKLA, self::DASHAMI_KRISHNA => 'Dashami',
            self::EKADASHI_SHUKLA, self::EKADASHI_KRISHNA => 'Ekadashi',
            self::DWADASHI_SHUKLA, self::DWADASHI_KRISHNA => 'Dwadashi',
            self::TRAYODASHI_SHUKLA, self::TRAYODASHI_KRISHNA => 'Trayodashi',
            self::CHATURDASHI_SHUKLA, self::CHATURDASHI_KRISHNA => 'Chaturdashi',
            self::PURNIMA => 'Purnima',
            self::AMAVASYA => 'Amavasya',
        };
    }

    /** Get pakṣa (fortnight) */
    public function getPaksha(): Paksha
    {
        return $this->value <= 15 ? Paksha::SHUKLA : Paksha::KRISHNA;
    }

    /** Get normalized tithi number (1-15) */
    public function getNormalized(): int
    {
        return match ($this->getPaksha()) {
            Paksha::SHUKLA => $this->value,
            Paksha::KRISHNA => $this->value - 15,
        };
    }

    /** Check if this is Ekadashi (11th tithi) */
    public function isEkadashi(): bool
    {
        return in_array($this, [self::EKADASHI_SHUKLA, self::EKADASHI_KRISHNA], true);
    }

    /** Check if this is Purnima or Amavasya */
    public function isPurnimaOrAmavasya(): bool
    {
        return in_array($this, [self::PURNIMA, self::AMAVASYA], true);
    }

    /** Check if this is Chaturdashi (14th tithi) */
    public function isChaturdashi(): bool
    {
        return in_array($this, [self::CHATURDASHI_SHUKLA, self::CHATURDASHI_KRISHNA], true);
    }
}
