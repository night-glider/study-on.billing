<?php

namespace App\DTO;

use App\Entity\Course;
use App\Enum\CourseEnum;
use JMS\Serializer\Annotation as Serializer;

class CourseRequestDTO
{
    public string $name;
    public string $code;
    public ?float $price = null;
    public int $type;

    public static function getCourseRequestDTO($name, $code, $price, $type)
    {
        $result = new self();
        $result->code = $code;
        $result->name = $name;
        $result->price = $price;
        $result->type = $type;
        return $result;
    }
}
