<?php

declare(strict_types=1);

namespace App\Application\Identity\IssueDeviceToken;

use App\Application\Identity\CurrentUser;
use App\Application\Transaction;
use App\Domain\Identity\DeviceToken;
use App\Domain\Identity\DeviceTokenRepository;
use Psr\Clock\ClockInterface;
use Random\Randomizer;

final readonly class IssueDeviceTokenHandler
{
    public function __construct(
        private DeviceTokenRepository $tokens,
        private CurrentUser $currentUser,
        private Randomizer $randomizer,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(): string
    {
        $secret = DeviceToken::PREFIX.bin2hex($this->randomizer->getBytes(24));
        $this->tokens->add(DeviceToken::issue($this->currentUser->get(), $secret, $this->clock->now()));
        $this->transaction->commit();

        return $secret;
    }
}
