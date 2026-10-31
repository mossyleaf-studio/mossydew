<?php

declare(strict_types=1);

namespace App\Domain\Mystery\Exception;

use App\Domain\Shared\Exception\DomainException;

final class CaseStillOpen extends DomainException
{
    public function __construct()
    {
        parent::__construct('mystery.case_still_open');
    }
}
