<?php

namespace App\Enum;

class TransactionEnum
{
    public const PAYMENT = 0;
    public const DEPOSIT = 1;

    public const PAYMENT_NAME = 'payment';
    public const DEPOSIT_NAME = 'deposit';

    public const NAMES = [
        self::PAYMENT => self::PAYMENT_NAME,
        self::DEPOSIT => self::DEPOSIT_NAME,
    ];

    public const VALUES = [
        self::PAYMENT_NAME => self::PAYMENT,
        self::DEPOSIT_NAME => self::DEPOSIT,
    ];
}