<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

/**
 * Pakṣa Enumeration
 * 
 * Represents the two fortnights in a lunar month.
 * 
 * @package JayeshMepani\PanchangCore
 */
enum Paksha: string
{
    case Shukla = 'Shukla';
    case Krishna = 'Krishna';
    
    /**
     * Get Sanskrit name
     */
    public function getName(): string
    {
        return match ($this) {
            self::Shukla => 'Śukla Pakṣa',
            self::Krishna => 'Kṛṣṇa Pakṣa',
        };
    }
    
    /**
     * Get description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::Shukla => 'Waxing moon fortnight (bright half)',
            self::Krishna => 'Waning moon fortnight (dark half)',
        };
    }
    
    /**
     * Get tithi range
     * 
     * @return array{start: int, end: int} Tithi index range
     */
    public function getTithiRange(): array
    {
        return match ($this) {
            self::Shukla => ['start' => 1, 'end' => 15],
            self::Krishna => ['start' => 16, 'end' => 30],
        };
    }
    
    /**
     * Check if tithi belongs to this pakṣa
     * 
     * @param int $tithiIndex Tithi index (1-30)
     * @return bool True if belongs to this pakṣa
     */
    public function containsTithi(int $tithiIndex): bool
    {
        $range = $this->getTithiRange();
        return $tithiIndex >= $range['start'] && $tithiIndex <= $range['end'];
    }
    
    /**
     * Normalize tithi to 1-15 range
     * 
     * @param int $tithiIndex Tithi index (1-30)
     * @return int Normalized tithi (1-15)
     */
    public function normalizeTithi(int $tithiIndex): int
    {
        return match ($this) {
            self::Shukla => $tithiIndex,
            self::Krishna => $tithiIndex - 15,
        };
    }
    
    /**
     * Convert normalized tithi to absolute index
     * 
     * @param int $normalizedTithi Normalized tithi (1-15)
     * @return int Absolute tithi index (1-30)
     */
    public function toAbsoluteTithi(int $normalizedTithi): int
    {
        return match ($this) {
            self::Shukla => $normalizedTithi,
            self::Krishna => $normalizedTithi + 15,
        };
    }
}
