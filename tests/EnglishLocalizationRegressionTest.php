<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Facades\Panchang;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;
use Override;
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
class EnglishLocalizationRegressionTest extends TestCase
{
    public function test_english_day_details_use_translated_sign_and_time_labels(): void
    {
        config(['panchang.defaults.locale' => 'en']);

        $details = Panchang::getDayDetails(
            CarbonImmutable::parse('2026-06-02', 'Asia/Kolkata'),
            23.2472446,
            69.668339,
            'Asia/Kolkata'
        );

        $this->assertSame('Taurus', $details['Chart_Auxiliary']['Sun_Sign'] ?? null);
        $this->assertSame('Sagittarius', $details['Chart_Auxiliary']['Moon_Sign'] ?? null);
        // Nirayana (sidereal) is the default Ayana/Ritu; keys are locale-stable.
        $this->assertSame('Uttarayana (Northward Course)', $details['Hindu_Calendar']['Ayana'] ?? null);
        $this->assertSame('Uttarayana (Northward Course)', $details['Hindu_Calendar']['Nirayana_Ayana'] ?? null);
        $this->assertSame('Uttarayana', $details['Hindu_Calendar']['Ayana_Key'] ?? null);
        $this->assertSame('Uttarayana', $details['Hindu_Calendar']['Nirayana_Ayana_Key'] ?? null);
        $this->assertSame('Grishma (Summer)', $details['Hindu_Calendar']['Ritu'] ?? null);
        $this->assertSame('Grishma (Summer)', $details['Hindu_Calendar']['Nirayana_Ritu'] ?? null);
        $this->assertSame('Grishma', $details['Hindu_Calendar']['Ritu_Key'] ?? null);
        $this->assertArrayHasKey('Sayana_Ayana', $details['Hindu_Calendar']);
        $this->assertArrayHasKey('Sayana_Ritu', $details['Hindu_Calendar']);
        $this->assertArrayHasKey('Sayana_Ayana_Key', $details['Hindu_Calendar']);
        $this->assertArrayHasKey('Sayana_Ritu_Key', $details['Hindu_Calendar']);
        $this->assertSame('Nirayana (Sidereal)', $details['Hindu_Calendar']['Ayana_System'] ?? null);
        $this->assertSame('Sayana (Tropical)', $details['Hindu_Calendar']['Sayana_Ayana_System'] ?? null);
        $this->assertSame('Second Lunar Day of Dark Half', $details['Tithi']['name'] ?? null);
        $this->assertSame('Dark Half (waning)', $details['Tithi']['paksha_name'] ?? null);
        $this->assertSame('Jyeshtha (Intercalary)', $details['Hindu_Calendar']['Month_Amanta_En'] ?? null);
        $this->assertSame('full_moon', $details['Moon_Phase_At_Sunrise']['key'] ?? null);
        $this->assertSame('Full Moon', $details['Moon_Phase_At_Sunrise']['name'] ?? null);
        $this->assertSame('Sunset to sunrise', $details['Moon_Phase_At_Sunrise']['visibility'] ?? null);

        $fivefold = $details['Daylight_Fivefold_Division'] ?? [];
        $this->assertSame('Morning', $fivefold[0]['name'] ?? null);
        $this->assertSame('Forenoon', $fivefold[1]['name'] ?? null);
        $this->assertSame('Midday', $fivefold[2]['name'] ?? null);
        $this->assertSame('Afternoon', $fivefold[3]['name'] ?? null);
        $this->assertSame('Evening', $fivefold[4]['name'] ?? null);

        $nightFivefold = $details['Nighttime_Fivefold_Division'] ?? [];
        $this->assertSame('Pradosha', $nightFivefold[0]['name'] ?? null);
        $this->assertSame('Night', $nightFivefold[1]['name'] ?? null);
        $this->assertSame('Nishitha', $nightFivefold[2]['name'] ?? null);
        $this->assertSame('Dawn', $nightFivefold[3]['name'] ?? null);
        $this->assertSame('Arunodaya', $nightFivefold[4]['name'] ?? null);

        $prahara = $details['Prahara_Full_Day'] ?? [];
        $this->assertSame('Morning Watch', $prahara[0]['name'] ?? null);
        $this->assertSame('Forenoon Watch', $prahara[1]['name'] ?? null);
        $this->assertSame('Midday Watch', $prahara[2]['name'] ?? null);
        $this->assertSame('Afternoon Watch', $prahara[3]['name'] ?? null);
        $this->assertSame('Dusk Watch', $prahara[4]['name'] ?? null);
        $this->assertSame('Midnight Watch', $prahara[5]['name'] ?? null);
        $this->assertSame('Late Night Watch', $prahara[6]['name'] ?? null);
        $this->assertSame('Dawn Watch', $prahara[7]['name'] ?? null);
    }

    public function test_english_month_calendar_uses_translated_signs(): void
    {
        config(['panchang.defaults.locale' => 'en']);

        $calendar = Panchang::getMonthCalendar(
            2026,
            6,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            ['festival_scope' => 'month']
        );

        $firstDay = $calendar['2026-06-01'] ?? [];

        $this->assertSame('Taurus', $firstDay['sun_sign'] ?? null);
        $this->assertSame('Scorpio', $firstDay['moon_sign'] ?? null);
        $this->assertSame('full_moon', $calendar['2026-06-02']['moon_phase']['key'] ?? null);
        $this->assertSame('Full Moon', $calendar['2026-06-02']['moon_phase']['name'] ?? null);

        $sankranti = $calendar['2026-06-15']['festivals'][0] ?? [];
        $chandraDarshana = array_values(array_filter(
            $calendar['2026-06-16']['festivals'] ?? [],
            static fn (array $festival): bool => ($festival['name_key'] ?? '') === 'Chandra Darshana'
        ))[0] ?? [];

        $this->assertSame('Mithuna Sankranti', $sankranti['name'] ?? null);
        $this->assertSame('Sun enters Gemini', $sankranti['description'] ?? null);
        $this->assertSame('Gemini', $sankranti['calculation_basis']['solar_rashi']['name'] ?? null);
        $this->assertSame('Chandra Darshana', $chandraDarshana['name'] ?? null);
        $this->assertSame('First sighting of the moon after Amavasya', $chandraDarshana['description'] ?? null);
        $this->assertSame('Bright Half (waxing)', $chandraDarshana['calculation_basis']['tithi']['paksha_name'] ?? null);
        $this->assertSame('sunset to moonset visibility', $chandraDarshana['visibility_window']['type_name'] ?? null);
        $this->assertSame('07:39 PM to 09:17 PM', $chandraDarshana['visibility_window']['display'] ?? null);
        $this->assertSame($chandraDarshana['visibility_window'], $chandraDarshana['observance_window'] ?? null);
        $this->assertMatchesRegularExpression('/^\\d{2}\\/\\d{2}\\/2026 \\d{2}:\\d{2}:\\d{2} (AM|PM)$/', $chandraDarshana['visibility_window']['start_iso'] ?? '');
        $this->assertMatchesRegularExpression('/^1h 3\\dm \\d+s$/', $chandraDarshana['visibility_window']['duration_min'] ?? '');
        $this->assertIsFloat($chandraDarshana['visibility_window']['duration_minutes'] ?? null);
        $this->assertSame('source-sensitive monthly Chandra Darshana first-crescent model', $chandraDarshana['calculation_basis']['chandra_darshana_visibility_model_name'] ?? null);
        $this->assertSame('application-defined earliest classical first-crescent date', $chandraDarshana['calculation_basis']['chandra_darshana_visibility_basis_name'] ?? null);
    }

    public function test_english_sankranti_day_details_use_translated_runtime_labels(): void
    {
        config(['panchang.defaults.locale' => 'en']);

        $details = Panchang::getDayDetails(
            CarbonImmutable::parse('2026-06-15', 'Asia/Kolkata'),
            23.2472446,
            69.668339,
            'Asia/Kolkata'
        );

        $this->assertSame('Gemini', $details['Dharma_Sindhu']['Punya_Kaal']['sankranti_name'] ?? null);
    }

    #[Override]
    protected function getPackageProviders($app)
    {
        return [PanchangServiceProvider::class];
    }
}
