<?php

declare(strict_types=1);

namespace App\Application\Identity\RevokeDeviceToken;

final readonly class RevokeDeviceToken
{
    public function __construct(#[\SensitiveParameter] public string $secret)
    {
    }
}
