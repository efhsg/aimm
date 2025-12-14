<?php

namespace MoneyMonkey\Enums;

enum Rating: string
{
    case Buy = 'BUY';
    case Hold = 'HOLD';
    case Sell = 'SELL';
}

