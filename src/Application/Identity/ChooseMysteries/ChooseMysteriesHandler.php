<?php

declare(strict_types=1);

namespace App\Application\Identity\ChooseMysteries;

use App\Application\Identity\CurrentUser;
use App\Application\Transaction;

final readonly class ChooseMysteriesHandler
{
    public function __construct(
        private CurrentUser $currentUser,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(bool $enabled): void
    {
        $this->currentUser->get()->playMysteries($enabled);
        $this->transaction->commit();
    }
}
