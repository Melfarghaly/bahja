<?php

namespace App\Enums;

enum ChildStatus: string
{
    case Active = 'active';
    case Graduated = 'graduated';
    case Withdrawn = 'withdrawn';
}
