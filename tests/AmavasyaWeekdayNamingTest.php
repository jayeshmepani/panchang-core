<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use JayeshMepani\PanchangCore\Festivals\FestivalCatalog;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class AmavasyaWeekdayNamingTest extends TestCase
{
    public function testGenericAmavasyaCatalogNoLongerListsWeekdayNamesAsStaticAliases(): void
    {
        $aliases = FestivalService::FESTIVALS['Amavasya']['aliases'] ?? [];
        self::assertContains('Amas', $aliases);
        self::assertNotContains('Amavasya Vrat', $aliases);
        self::assertNotContains('Somavati Amavasya', $aliases);
        self::assertNotContains('Bhaumavati Amavasya', $aliases);
        self::assertNotContains('Shani Amavasya', $aliases);
        self::assertTrue((bool) (FestivalService::FESTIVALS['Amavasya']['weekday_classifier_after_resolution'] ?? false));
    }

    public function testCatalogFestivalCountExpandsAmavasyaWeekdayIdentities(): void
    {
        $withoutExpansion = [];
        foreach (FestivalService::FESTIVALS as $key => $definition) {
            if ($definition['fasting'] ?? false) {
                continue;
            }

            if ($key === 'Amavasya') {
                $withoutExpansion['Amavasya'] = true;
                continue;
            }

            $rawIdentity = $definition['identity_key'] ?? null;
            $identityKey = is_string($rawIdentity) ? $rawIdentity : $key;
            $withoutExpansion[$identityKey] = true;
        }

        self::assertSame(
            count($withoutExpansion) + 3,
            FestivalCatalog::getCatalogFestivalCount()
        );
    }

    /** @dataProvider weekdayElevationProvider */
    public function testGenericAmavasyaElevatesFormalWeekdayIdentity(
        string $observanceDate,
        string $expectedIdentity,
        array $mustNotAppearAsSiblingWeekdayAlias
    ): void {
        $payload = $this->payload('Amavasya', FestivalService::FESTIVALS['Amavasya'], $observanceDate);

        self::assertSame($expectedIdentity, $payload['name_key']);
        self::assertSame($expectedIdentity, $payload['name']);
        // Named weekday Amavasya may be referred to as Amavasya.
        self::assertContains('Amavasya', $payload['aliases']);
        foreach ($mustNotAppearAsSiblingWeekdayAlias as $forbidden) {
            self::assertNotContains($forbidden, $payload['aliases']);
        }

        self::assertSame(
            $expectedIdentity,
            $payload['calculation_basis']['weekday_classifier_key'] ?? null
        );
        self::assertSame('vaar', $payload['calculation_basis']['naming_basis'] ?? null);
    }

    public function testNonFormalWeekdayKeepsGenericAmavasyaIdentityWithoutFakeWeekdayAliases(): void
    {
        // 2026-01-18 is Sunday — no genuine weekday Amavasya name.
        $payload = $this->payload('Amavasya', FestivalService::FESTIVALS['Amavasya'], '2026-01-18');

        self::assertSame('Amavasya', $payload['name_key']);
        self::assertNull($payload['calculation_basis']['weekday_classifier_key'] ?? null);
        self::assertNotContains('Somavati Amavasya', $payload['aliases']);
        self::assertNotContains('Bhaumavati Amavasya', $payload['aliases']);
        self::assertNotContains('Shani Amavasya', $payload['aliases']);
        self::assertNotContains('Ravi Amavasya', $payload['aliases']);
    }

    public function testMonthNamedAmavasyaAliasesToGenericButNotToWeekdayNames(): void
    {
        // 2026-04-20 is Monday — weekday must NOT be attached to Magha Amavasya.
        // Magha may alias to Amavasya (named → generic), never the reverse.
        $payload = $this->payload(
            'Magha Amavasya',
            FestivalService::FESTIVALS['Magha Amavasya'],
            '2026-04-20'
        );

        self::assertSame('Magha Amavasya', $payload['name_key']);
        self::assertContains('Amavasya', $payload['aliases']);
        self::assertContains('Mauni Amavasya', $payload['aliases']);
        self::assertNotContains('Somavati Amavasya', $payload['aliases']);
        self::assertNotContains('Somvati Amavasya', $payload['aliases']);
        self::assertNotContains('Bhaumavati Amavasya', $payload['aliases']);
        self::assertNotContains('Shani Amavasya', $payload['aliases']);
        self::assertNull($payload['calculation_basis']['weekday_classifier_key'] ?? null);
    }

    public function testDarshaAmavasyaStaysTechnicalSubsetWithoutWeekdayOrGenericAlias(): void
    {
        // Darsha is an aparahna/evening–night subset — not always the main Amavasya identity.
        $payload = $this->payload(
            'Darsha Amavasya',
            FestivalService::FESTIVALS['Darsha Amavasya'],
            '2026-04-20' // Monday
        );

        self::assertSame('Darsha Amavasya', $payload['name_key']);
        self::assertNotContains('Amavasya', $payload['aliases'] ?? []);
        self::assertNotContains('Somavati Amavasya', $payload['aliases'] ?? []);
        self::assertNull($payload['calculation_basis']['weekday_classifier_key'] ?? null);
        self::assertStringContainsString('subset', strtolower((string) ($payload['description'] ?? '')));
    }

    /** @return array<string, array{0: string, 1: string, 2: list<string>}> */
    public static function weekdayElevationProvider(): array
    {
        return [
            'monday_somavati' => [
                '2026-04-20',
                'Somavati Amavasya',
                ['Bhaumavati Amavasya', 'Shani Amavasya'],
            ],
            'tuesday_bhaumavati' => [
                '2026-01-20',
                'Bhaumavati Amavasya',
                ['Somavati Amavasya', 'Shani Amavasya'],
            ],
            'saturday_shani' => [
                '2026-06-06',
                'Shani Amavasya',
                ['Somavati Amavasya', 'Bhaumavati Amavasya'],
            ],
        ];
    }

    /** @param array<string, mixed> $rules */
    private function payload(string $name, array $rules, string $observanceDate): array
    {
        $service = (new ReflectionClass(FestivalService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(FestivalService::class, 'buildFestivalPayload');

        return $method->invoke(
            $service,
            $name,
            $rules,
            ['observance_date' => $observanceDate]
        );
    }
}
