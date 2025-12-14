<?php

namespace MoneyMonkey\Enums;

enum Severity: string
{
    case Required = 'required';
    case Recommended = 'recommended';
    case Optional = 'optional';
}

