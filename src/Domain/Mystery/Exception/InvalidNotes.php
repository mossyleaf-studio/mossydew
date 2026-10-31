<?php

declare(strict_types=1);

namespace App\Domain\Mystery\Exception;

use App\Domain\Shared\Exception\DomainException;

final class InvalidNotes extends DomainException
{
    public function __construct()
    {
        parent::__construct('mystery.invalid_notes');
    }
}
