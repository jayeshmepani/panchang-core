<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Ritual;

final class MahadikshaGuidance
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'type' => 'ritual_guidance',
            'calendar_event' => false,
            'guidance_key' => 'mahadiksha',
            'prohibitions' => [
                'guru_asta',
                'shukra_asta',
                'adhika_masa',
                'varsha_ritu',
            ],
            'exceptions' => [
                'eclipse_exception_for_eclipse_related_diksha',
            ],
            'notes' => [
                'This is initiation guidance and is intentionally not emitted as a festival or vrat date.',
                'Use this alongside graha asta, masa, ritu, and eclipse context when a Mahadiksha election is requested.',
            ],
        ];
    }

    /** @param array<string, bool> $context */
    public static function evaluate(array $context): array
    {
        $rules = self::rules();
        $blocking = [];
        foreach ($rules['prohibitions'] as $key) {
            if ($context[$key] ?? false) {
                $blocking[] = $key;
            }
        }

        $hasEclipseException = $context['eclipse_exception_for_eclipse_related_diksha'] ?? false;
        if ($hasEclipseException) {
            $blocking = array_values(array_filter(
                $blocking,
                static fn (string $key): bool => $key !== 'guru_asta' && $key !== 'shukra_asta'
            ));
        }

        return [
            ...$rules,
            'eligible' => $blocking === [],
            'blocking_conditions' => $blocking,
            'exception_applied' => $hasEclipseException,
        ];
    }
}
