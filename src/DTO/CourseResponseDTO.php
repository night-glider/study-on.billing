<?php

namespace App\DTO;

use App\Entity\Course;
use App\Enum\CourseEnum;

class CourseResponseDTO
{
    public string $code;
    public string $name;
    public float $price;
    public string $type;

    public function __construct(Course $course)
    {
        $this->code = $course->getCode();
        $this->name = $course->getName();
        $this->type = CourseEnum::NAMES[$course->getType()];
        if ($this->type != CourseEnum::FREE_NAME) {
            $this->price = $course->getPrice();
        }
    }
}
