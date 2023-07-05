<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Service\PaymentService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;
    private PaymentService $paymentService;
    public function __construct(UserPasswordHasherInterface $passwordHasher, PaymentService $paymentService)
    {
        $this->passwordHasher = $passwordHasher;
        $this->paymentService = $paymentService;
    }
    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail("user@gmail.com");
        $user->setPassword($this->passwordHasher->hashPassword(
            $user,
            'user'
        ));
        $user->setRoles(["ROLE_USER"]);
        $manager->persist($user);
        $this->paymentService->deposit($user, 100.50);

        $userNoMoney = new User();
        $userNoMoney->setEmail("no_money@gmail.com");
        $userNoMoney->setPassword($this->passwordHasher->hashPassword(
            $userNoMoney,
            'no_money'
        ));
        $userNoMoney->setRoles(["ROLE_USER"]);
        $manager->persist($userNoMoney);

        $admin = new User();
        $admin->setEmail("admin@gmail.com");
        $admin->setPassword($this->passwordHasher->hashPassword(
            $admin,
            'admin'
        ));
        $admin->setRoles(["ROLE_SUPER_ADMIN"]);
        $manager->persist($admin);
        $this->paymentService->deposit($admin, 35.75);

        $course_godot = new Course();
        $course_godot->setPrice(0.0)
            ->setType(0)
            ->setCode("Godot4beginner")
            ->setName("Godot 4 для начинающих");
        $manager->persist($course_godot);

        $course_unity = new Course();
        $course_unity->setPrice(20.0)
            ->setType(1)
            ->setCode("unity_beginner")
            ->setName("Unity для начинающих");
        $manager->persist($course_unity);

        $course_ue = new Course();
        $course_ue->setPrice(10.0)
            ->setType(2)
            ->setCode("UE5pro")
            ->setName("UE5 для профи");
        $manager->persist($course_ue);

        $this->paymentService->payment($user, $course_unity);
        $this->paymentService->payment($admin, $course_ue);

        $newTransaction = new Transaction();
        $newTransaction->setCourse($course_ue)
            ->setValue(10)
            ->setType(0)
            ->setCreationDate(new \DateTime())
            ->setCustomer($userNoMoney)
            ->setExpirationDate(new \DateTime());
        $manager->persist($newTransaction);

        $manager->flush();
    }
}
