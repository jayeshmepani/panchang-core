<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JayeshMepani\PanchangCore\Core\Enums\CalendarType;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;

class SelectiveApiTest extends TestCase
{
    public function testSelectedSectionsMatchFullDayDetails(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $date = CarbonImmutable::create(2026, 5, 23, 0, 0, 0, 'Asia/Kolkata');
        $at = CarbonImmutable::create(2026, 5, 23, 10, 30, 0, 'Asia/Kolkata');
        $sections = [
            'Panchanga',
            'Special_Yogas',
            'Anandadi_Yoga',
            'Amritadi_Yoga',
            'Panchak',
            'Maitreya_Yoga',
            'Gajachchhaya_Yoga',
            'Nakshatra_Shool',
            'Disha_Shool',
            'Rahu_Vaasa',
            'Chandra_Vaasa',
            'Shiva_Vaasa',
            'Agni_Vaasa',
            'Yogini_Vaasa',
            'Panchaka_Rahita',
            'Hora_Full_Day',
            'Chogadiya_Full_Day',
            'Muhurta_Full_Day',
            'Lagna_Full_Day',
            'Rahu_Kaal_Gulika_Yamaganda',
            'Abhijit_Muhurta',
            'Prahara_Full_Day',
            'Daylight_Fivefold_Division',
            'Brahma_Muhurta',
            'Dur_Muhurta_Full_Day',
            'Nishita_Muhurta',
            'Vijaya_Muhurta',
            'Godhuli_Muhurta',
            'Sandhya',
            'Gowri_Panchangam',
            'Kala_Vela',
            'Karmakala_Windows',
            'Varjyam',
            'Amrita_Kaal',
            'Pradosha_Kaal',
            'Dharma_Sindhu',
            'Bhadra',
        ];

        $full = $service->getDayDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at, CalendarType::Amanta);
        $selected = $service->getSelectedDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', $sections, 0.0, $at, CalendarType::Amanta);

        foreach ($sections as $section) {
            $this->assertArrayHasKey($section, $selected);
            $this->assertSame(
                json_encode($full[$section], JSON_THROW_ON_ERROR),
                json_encode($selected[$section], JSON_THROW_ON_ERROR),
                sprintf('Selected section %s should match full day details.', $section)
            );
        }
    }

    public function testSelectedBasicDetailsReturnsOnlyBasicWrapper(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $date = CarbonImmutable::create(2026, 5, 23, 0, 0, 0, 'Asia/Kolkata');
        $at = CarbonImmutable::create(2026, 5, 23, 10, 30, 0, 'Asia/Kolkata');

        $full = $service->getDayDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at);
        $selected = $service->getSelectedDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', ['basic'], 0.0, $at);

        $this->assertSame(['Basic_Details'], array_keys($selected));
        $this->assertArrayHasKey('Panchanga', $selected['Basic_Details']);
        $this->assertArrayHasKey('Daily_Observances', $selected['Basic_Details']);
        $this->assertArrayNotHasKey('Special_Yogas', $selected['Basic_Details']);

        foreach ($selected['Basic_Details'] as $key => $value) {
            $this->assertSame(
                json_encode($full[$key] ?? null, JSON_THROW_ON_ERROR),
                json_encode($value, JSON_THROW_ON_ERROR),
                sprintf('Basic detail key %s should match full day details.', $key)
            );
        }
    }

    public function testUnknownSelectedSectionThrows(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $date = CarbonImmutable::create(2026, 5, 23, 0, 0, 0, 'Asia/Kolkata');

        $this->expectException(InvalidArgumentException::class);

        $service->getSelectedDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', ['Not_A_Section']);
    }

    public function testConvenienceSectionApisMatchSelectedSections(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $date = CarbonImmutable::create(2026, 5, 23, 0, 0, 0, 'Asia/Kolkata');
        $at = CarbonImmutable::create(2026, 5, 23, 10, 30, 0, 'Asia/Kolkata');

        $this->assertSame(
            $service->getSelectedDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', ['Panchanga'], 0.0, $at)['Panchanga'],
            $service->getPanchanga($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at)
        );
        $this->assertSame(
            $service->getSelectedDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', ['Basic_Details'], 0.0, $at)['Basic_Details'],
            $service->getBasicDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at)
        );
        $this->assertSame(
            $service->getSelectedDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', ['Special_Yogas'], 0.0, $at)['Special_Yogas'],
            $service->getSpecialYogas($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at)
        );
        $this->assertSame(
            $service->getSelectedDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', ['Muhurta_Full_Day'], 0.0, $at)['Muhurta_Full_Day'],
            $service->getMuhurtaFullDay($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at)
        );
        $this->assertSame(
            $service->getSelectedDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', ['Abhijit_Muhurta'], 0.0, $at)['Abhijit_Muhurta'],
            $service->getAbhijitMuhurta($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at)
        );
        $this->assertSame(
            $service->getSelectedDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', ['Varjyam'], 0.0, $at)['Varjyam'],
            $service->getVarjyam($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at)
        );
        $this->assertSame(
            $service->getSelectedDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', ['Dharma_Sindhu'], 0.0, $at)['Dharma_Sindhu'],
            $service->getDharmaSindhu($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at)
        );
        $this->assertSame(
            $service->getSelectedDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', ['Lagna_Full_Day'], 0.0, $at)['Lagna_Full_Day'],
            $service->getSection($date, 23.2472446, 69.668339, 'Asia/Kolkata', 'Lagna_Full_Day', 0.0, $at)
        );
    }

    public function testLimbApisReturnOnlyRequestedLimb(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $date = CarbonImmutable::create(2026, 5, 23, 0, 0, 0, 'Asia/Kolkata');
        $at = CarbonImmutable::create(2026, 5, 23, 10, 30, 0, 'Asia/Kolkata');
        $panchanga = $service->getPanchanga($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at);

        $this->assertSame($panchanga['Tithi'], $service->getTithi($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at));
        $this->assertSame($panchanga['Current_Tithi_At_Input_Now'], $service->getCurrentTithi($date, 23.2472446, 69.668339, 'Asia/Kolkata', $at));
        $this->assertSame($panchanga['Nakshatra'], $service->getNakshatra($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at));
        $this->assertSame($panchanga['Current_Nakshatra_At_Input_Now'], $service->getCurrentNakshatra($date, 23.2472446, 69.668339, 'Asia/Kolkata', $at));
        $this->assertSame($panchanga['Yoga'], $service->getYoga($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at));
        $this->assertSame($panchanga['Current_Yoga_At_Input_Now'], $service->getCurrentYoga($date, 23.2472446, 69.668339, 'Asia/Kolkata', $at));
        $this->assertSame($panchanga['Karana'], $service->getKarana($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at));
        $this->assertSame($panchanga['Current_Karana_At_Input_Now'], $service->getCurrentKarana($date, 23.2472446, 69.668339, 'Asia/Kolkata', $at));
        $this->assertSame($panchanga['Vara'], $service->getVara($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at));
    }

    public function testFieldApiReturnsOnlyRequestedNestedFields(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $date = CarbonImmutable::create(2026, 5, 23, 0, 0, 0, 'Asia/Kolkata');
        $at = CarbonImmutable::create(2026, 5, 23, 10, 30, 0, 'Asia/Kolkata');

        $fields = $service->getFields($date, 23.2472446, 69.668339, 'Asia/Kolkata', [
            'Panchanga.Current_Tithi_At_Input_Now.name',
            'Abhijit_Muhurta.abhijit_start',
            'Sunrise',
        ], 0.0, $at);

        $full = $service->getDayDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at);

        $this->assertSame(
            $full['Panchanga']['Current_Tithi_At_Input_Now']['name'],
            $fields['Panchanga']['Current_Tithi_At_Input_Now']['name']
        );
        $this->assertSame($full['Abhijit_Muhurta']['abhijit_start'], $fields['Abhijit_Muhurta']['abhijit_start']);
        $this->assertSame($full['Sunrise'], $fields['Sunrise']);
        $this->assertArrayNotHasKey('abhijit_end', $fields['Abhijit_Muhurta']);
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
