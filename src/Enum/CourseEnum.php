<?php

namespace App\Enum;

class CourseEnum
{
    public const FREE = 0;
    public const BUY = 1;
    public const RENT = 2;

    public const FREE_NAME = 'free';
    public const BUY_NAME = 'buy';
    public const RENT_NAME = 'rent';


    public const NAMES = [
        self::FREE => self::FREE_NAME,
        self::BUY => self::BUY_NAME,
        self::RENT => self::RENT_NAME,
    ];

    public const VALUES = [
        self::FREE_NAME => self::FREE,
        self::BUY_NAME => self::BUY,
        self::RENT_NAME => self::RENT,
    ];
}