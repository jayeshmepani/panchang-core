<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

/**
 * Ṛtu (Season) Enumeration
 * 
 * Represents the 6 Hindu seasons.
 * Each ṛtu spans 2 lunar months.
 * 
 * @package JayeshMepani\PanchangCore
 */
enum Ritu: int
{
    case Vasanta = 0;
    case Grishma = 1;
    case Varsha = 2;
    case Sharad = 3;
    case Hemanta = 4;
    case Shishira = 5;
    
    /**
     * Get Sanskrit name
     */
    public function getName(): string
    {
        return match ($this) {
            self::Vasanta => 'Vasanta',
            self::Grishma => 'Grīṣma',
            self::Varsha => 'Varṣā',
            self::Sharad => 'Śarad',
            self::Hemanta => 'Hemanta',
            self::Shishira => 'Śiśira',
        };
    }
    
    /**
     * Get English name
     */
    public function getEnglishName(): string
    {
        return match ($this) {
            self::Vasanta => 'Spring',
            self::Grishma => 'Summer',
            self::Varsha => 'Monsoon',
            self::Sharad => 'Autumn',
            self::Hemanta => 'Pre-Winter',
            self::Shishira => 'Winter',
        };
    }
    
    /**
     * Get months in this ṛtu
     * 
     * @return array<int> Month indices (0-11)
     */
    public function getMonths(): array
    {
        return match ($this) {
            self::Vasanta => [0, 1],  // Chaitra, Vaishakha
            self::Grishma => [2, 3],  // Jyeshtha, Ashadha
            self::Varsha => [4, 5],   // Shravana, Bhadrapada
            self::Sharad => [6, 7],   // Ashvina, Kartika
            self::Hemanta => [8, 9],  // Margashirsha, Pausha
            self::Shishira => [10, 11], // Magha, Phalguna
        };
    }
    
    /**
     * Get ṛtu from month index
     * 
     * @param int $monthIndex Month index (0-11)
     * @return self Ritu instance
     */
    public static function fromMonth(int $monthIndex): self
    {
        $rituMap = [0, 0, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5];
        return self::from($rituMap[$monthIndex]);
    }
}
