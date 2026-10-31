<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

enum Gender: string
{
    case Female = 'female';
    case Male = 'male';
}
