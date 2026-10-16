<?php

declare(strict_types=1);

namespace App\Domain\Identity;

interface DeviceTokenRepository
{
    public function add(DeviceToken $token): void;

    public function remove(DeviceToken $token): void;

    public function withSecret(string $secret): ?DeviceToken;
}
