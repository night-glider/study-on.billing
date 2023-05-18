<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

class UserDTO
{
    /**
     * @Assert\NotBlank(message="Поле Email должно быть заполнено")
     * @Assert\Email( message="Неверный Email адрес" )
     */
    private ?string $username = null;
    /**
     * @Assert\NotBlank(message="Поле Password должно быть заполнено")
     * @Assert\Length(min="6", minMessage="Поле Password должно содержать минимум 6 символов", max=100, maxMessage="Password должен иметь не более 100 символов")
     */
    private ?string $password = null;

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }


    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }
}
