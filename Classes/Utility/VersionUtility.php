<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Utility;

use PackageVersions\Versions;
use Throwable;

final class VersionUtility
{
    private function __construct()
    {
    }

    public static function getVersion(): string
    {
        $str = 'dev';
        try {
            return explode('@', Versions::getVersion('andersundsehr/ssi-include'))[0] ?? $str;
        } catch (Throwable) {
            return $str;
        }
    }
}
