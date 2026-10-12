<?php

declare(strict_types=1);

namespace App\Application\Identity\RevokeAccessToken;

use App\Application\Identity\CurrentUser;
use App\Application\Transaction;
use App\Domain\Identity\AccessTokenRepository;

final readonly class RevokeAccessTokenHandler
{
    public function __construct(
        private AccessTokenRepository $tokens,
        private CurrentUser $currentUser,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(): void
    {
        $token = $this->tokens->of($this->currentUser->get());
        if (null !== $token) {
            $this->tokens->remove($token);
            $this->transaction->commit();
        }
    }
}
