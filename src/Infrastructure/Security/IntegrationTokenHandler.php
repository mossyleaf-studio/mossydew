<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Identity\AccessTokenRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final readonly class IntegrationTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private AccessTokenRepository $tokens)
    {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $token = $this->tokens->withSecret($accessToken) ?? throw new BadCredentialsException('Invalid access token.');

        return new UserBadge((string) $token->owner()->id());
    }
}
