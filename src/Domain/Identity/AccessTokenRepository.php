<?php

declare(strict_types=1);

namespace App\Domain\Identity;

interface AccessTokenRepository
{
    public function add(AccessToken $token): void;

    public function remove(AccessToken $token): void;

    public function of(User $owner): ?AccessToken;

    public function withSecret(string $secret): ?AccessToken;
}
