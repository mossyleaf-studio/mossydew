<?php

declare(strict_types=1);

namespace App\Application\Identity\RevokeDeviceToken;

use App\Application\Identity\CurrentUser;
use App\Application\Transaction;
use App\Domain\Identity\DeviceTokenRepository;

final readonly class RevokeDeviceTokenHandler
{
    public function __construct(
        private DeviceTokenRepository $tokens,
        private CurrentUser $currentUser,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RevokeDeviceToken $command): void
    {
        $token = $this->tokens->withSecret($command->secret);
        if (null !== $token && $token->owner()->id()->equals($this->currentUser->get()->id())) {
            $this->tokens->remove($token);
            $this->transaction->commit();
        }
    }
}
