<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;
    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }
    public function load(ObjectManager $manager): void
    {
        $new_user = new User();
        $new_user->setEmail("user@gmail.com");
        $new_user->setPassword($this->passwordHasher->hashPassword(
            $new_user,
            'user'
        ));
        $new_user->setRoles(["ROLE_USER"]);
        $new_user->setBalance(25.4);
        $manager->persist($new_user);

        $new_user = new User();
        $new_user->setEmail("admin@gmail.com");
        $new_user->setPassword($this->passwordHasher->hashPassword(
            $new_user,
            'admin'
        ));
        $new_user->setRoles(["ROLE_SUPER_ADMIN"]);
        $new_user->setBalance(51.2);
        $manager->persist($new_user);

        // $product = new Product();
        // $manager->persist($product);

        $manager->flush();
    }
}
