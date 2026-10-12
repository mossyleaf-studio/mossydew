<?php

declare(strict_types=1);

namespace App\Application\Identity\CreateAccessToken;

use App\Application\Identity\CurrentUser;
use App\Application\Transaction;
use App\Domain\Identity\AccessToken;
use App\Domain\Identity\AccessTokenRepository;
use Psr\Clock\ClockInterface;
use Random\Randomizer;

final readonly class CreateAccessTokenHandler
{
    public function __construct(
        private AccessTokenRepository $tokens,
        private CurrentUser $currentUser,
        private Randomizer $randomizer,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(): string
    {
        $owner = $this->currentUser->get();
        $secret = AccessToken::PREFIX.bin2hex($this->randomizer->getBytes(24));
        $existing = $this->tokens->of($owner);
        if (null === $existing) {
            $this->tokens->add(AccessToken::issue($owner, $secret, $this->clock->now()));
        } else {
            $existing->rotate($secret, $this->clock->now());
        }
        $this->transaction->commit();

        return $secret;
    }
}
