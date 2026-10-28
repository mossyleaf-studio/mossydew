<?php

declare(strict_types=1);

namespace App\Application\Identity\ChooseDesign;

use App\Application\Identity\CurrentUser;
use App\Application\Transaction;
use App\Domain\Identity\Design;

final readonly class ChooseDesignHandler
{
    public function __construct(
        private CurrentUser $currentUser,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(Design $design): void
    {
        $this->currentUser->get()->decorate($design);
        $this->transaction->commit();
    }
}
