<?php

namespace App\Services\ExpertSystem;

final class ExpertParameter
{
    public const PERFORMANCE = 'performance';
    public const COMPETENCY = 'competency';
    public const ATTENDANCE = 'attendance';
    public const EXPERIENCE = 'experience';
    public const LEADERSHIP = 'leadership';

    public static function values(): array
    {
        return [
            self::PERFORMANCE,
            self::COMPETENCY,
            self::ATTENDANCE,
            self::EXPERIENCE,
            self::LEADERSHIP,
        ];
    }
}
