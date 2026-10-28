<?php

declare(strict_types=1);

namespace App\Application\Identity\ChooseIcons;

use App\Application\Identity\CurrentUser;
use App\Application\Transaction;
use App\Domain\Identity\IconSet;

final readonly class ChooseIconsHandler
{
    public function __construct(
        private CurrentUser $currentUser,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(IconSet $iconSet): void
    {
        $this->currentUser->get()->drawWith($iconSet);
        $this->transaction->commit();
    }
}
