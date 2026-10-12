<?php

declare(strict_types=1);

namespace App\Domain\Social\Exception;

use App\Domain\Shared\Exception\DomainException;

abstract class InvalidFriendship extends DomainException
{
}
