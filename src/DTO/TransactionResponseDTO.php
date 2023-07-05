<?php

namespace App\DTO;

use App\Entity\Transaction;
use App\Enum\TransactionEnum;
use DateTimeImmutable;
use JMS\Serializer\Annotation as Serializer;

class TransactionResponseDTO
{
    public int $id;
    public string $course;
    public string $type;
    public float $value;
    public string $creationDate;
    public string $expirationDate;

    public function __construct(Transaction $transaction)
    {
        $this->id = $transaction->getId();
        if ($transaction->getCourse()) {
            $this->course = $transaction->getCourse()->getCode();
        }
        $this->type = TransactionEnum::NAMES[$transaction->getType()];
        $this->value = $transaction->getValue();
        $this->creationDate = $transaction->getCreationDate()->format('Y-m-d H:i:s');
        if ($transaction->getExpirationDate()) {
            $this->expirationDate = $transaction->getExpirationDate()->format('Y-m-d H:i:s');
        }
    }
}
