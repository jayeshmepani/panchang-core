<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Shraddha;

enum ShannavatiTraditionProfile: string
{
    /** Dharma-sindhu / later nibandha tithi enumeration. */
    case DharmaSindhu = 'dharma_sindhu';

    /** Common modern named-Manvadi presentation used by contemporary panchangas. */
    case ModernNamed = 'modern_named';
}
