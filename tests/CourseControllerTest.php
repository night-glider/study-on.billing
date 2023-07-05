<?php

namespace App\Tests;

use App\Entity\User;
use App\Entity\Course;
use App\Enum\CourseEnum;
use App\Entity\Transaction;
use App\Service\PaymentService;
use App\DataFixtures\AppFixtures;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CourseControllerTest extends AbstractTest
{
    private string $authURL = '/api/v1/auth';
    private string $adminEmail = 'admin@gmail.com';
    private string $adminPassword = 'admin';
    private string $fixtureEmail = 'user@gmail.com';
    private string $fixturePassword = 'user';
    private string $fixtureEmailNoMoney = 'no_money@gmail.com';
    private string $fixturePasswordNoMoney = 'no_money';

    protected function getFixtures(): array
    {
        return [
            new AppFixtures(
                $this->getContainer()->get(UserPasswordHasherInterface::class),
                $this->getContainer()->get(PaymentService::class),
            )
        ];
    }
    public function testGetCourses(): void
    {
        $client = static::getClient();

        $client->jsonRequest('GET', '/api/v1/courses');
        $response = $client->getResponse();
        $courses = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertResponseOk();

        $this->assertCount(3, $courses);
    }

    public function testGetCourse(): void
    {
        $client = static::getClient();

        $entityManager = $this->getEntityManager();
        $courseRepository = $entityManager->getRepository(Course::class);
        $free_course = $courseRepository->findOneBy(['type' => CourseEnum::FREE]);
        $client->request('GET', '/api/v1/courses/' . $free_course->getCode());
        $this->assertResponseOk();

        $course = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals($free_course->getCode(), $course['code']);
        $this->assertEquals(CourseEnum::FREE, CourseEnum::VALUES[$course['type']]);
    }

    public function testPayCourse(): void
    {
        $client = static::getClient();

        $entityManager = $this->getEntityManager();
        $courseRepository = $entityManager->getRepository(Course::class);
        $pay_course = $courseRepository->findOneBy(['type' => CourseEnum::RENT]);

        // Пользователь, у которого есть средства
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->fixtureEmail,
            "password" => $this->fixturePassword
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        // Успешная оплата курса
        $client->jsonRequest('POST', '/api/v1/courses/' . $pay_course->getCode() . '/pay', [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token'],
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json'
        ]);
        $this->assertResponseCode(Response::HTTP_OK);
        $payInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(1, $payInfo['success']);
    }

    public function testPayCourseNoMoney(): void
    {
        $client = static::getClient();

        $entityManager = $this->getEntityManager();
        $courseRepository = $entityManager->getRepository(Course::class);
        $pay_course = $courseRepository->findOneBy(['type' => CourseEnum::RENT]);

        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->fixtureEmailNoMoney,
            "password" => $this->fixturePasswordNoMoney
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->jsonRequest('POST', '/api/v1/courses/' . $pay_course->getCode() . '/pay', [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token'],
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json'
        ]);
        $this->assertResponseCode(Response::HTTP_NOT_ACCEPTABLE);
    }

    public function testPayCourseNotFound(): void
    {
        $client = static::getClient();

        // Пользователь, у которого есть средства
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->fixtureEmail,
            "password" => $this->fixturePassword
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->jsonRequest('POST', '/api/v1/courses/utyuewtr/pay', [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token'],
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json'
        ]);
        $this->assertResponseCode(Response::HTTP_NOT_FOUND);
    }

    public function testPayCourseNoToken(): void
    {
        $client = static::getClient();

        $entityManager = $this->getEntityManager();
        $courseRepository = $entityManager->getRepository(Course::class);
        $pay_course = $courseRepository->findOneBy(['type' => CourseEnum::RENT]);

        // Пользователь, у которого есть средства
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->fixtureEmail,
            "password" => $this->fixturePassword
        ]);

        $client->jsonRequest('POST', '/api/v1/courses/' . $pay_course->getCode() . '/pay', [], );
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
    }

    public function testAddCourse(): void
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->adminEmail,
            "password" => $this->adminPassword
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->jsonRequest(
            'POST',
            '/api/v1/courses/new',
            [
                'name' => 'test name',
                'code' => 'test code',
                'price' => 10.0,
                'type' => 2
            ],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token'],
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json'
            ],
        );
        $this->assertResponseCode(Response::HTTP_CREATED);
        $this->assertTrue(json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['success']);
    }

    public function testAddCourseWithNoPrice(): void
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->adminEmail,
            "password" => $this->adminPassword
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->jsonRequest(
            'POST',
            '/api/v1/courses/new',
            [
                'name' => 'test name',
                'code' => 'test code',
                'price' => 0,
                'type' => 2
            ],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token'],
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json'
            ],
        );
        $this->assertResponseCode(Response::HTTP_FORBIDDEN);
    }

    public function testAddCourseWithEmptyName(): void
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->adminEmail,
            "password" => $this->adminPassword
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->jsonRequest(
            'POST',
            '/api/v1/courses/new',
            [
                'name' => '',
                'code' => 'test code',
                'price' => 10.0,
                'type' => 2
            ],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token'],
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json'
            ],
        );
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);
    }

    public function testAddCourseWithNotUniqueCode(): void
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->adminEmail,
            "password" => $this->adminPassword
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->jsonRequest(
            'POST',
            '/api/v1/courses/new',
            [
                'name' => 'test name',
                'code' => 'unity_beginner',
                'price' => 10.0,
                'type' => 2
            ],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token'],
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json'
            ],
        );
        $this->assertResponseCode(Response::HTTP_CONFLICT);
    }

    public function testAddCourseWithoutToken(): void
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->fixtureEmail,
            "password" => $this->fixturePassword
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->jsonRequest(
            'POST',
            '/api/v1/courses/new',
            [
                'name' => 'test name',
                'code' => 'test code',
                'price' => 10.0,
                'type' => 2
            ],
        );
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
    }

    public function testEditCourse(): void
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->adminEmail,
            "password" => $this->adminPassword
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->jsonRequest(
            'POST',
            '/api/v1/courses/unity_beginner/edit',
            [
                'name' => 'test name',
                'code' => 'test code',
                'price' => 10.0,
                'type' => 2
            ],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token'],
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json'
            ],
        );
        $this->assertResponseCode(Response::HTTP_OK);
        $this->assertTrue(json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['success']);
    }

    public function testEditCourseNoPrice(): void
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->adminEmail,
            "password" => $this->adminPassword
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->jsonRequest(
            'POST',
            '/api/v1/courses/unity_beginner/edit',
            [
                'name' => 'test name',
                'code' => 'test code',
                'price' => 0.0,
                'type' => 2
            ],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token'],
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json'
            ],
        );
        $this->assertResponseCode(Response::HTTP_FORBIDDEN);
    }

    public function testEditCourseNoToken(): void
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->fixtureEmail,
            "password" => $this->fixturePassword
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->jsonRequest(
            'POST',
            '/api/v1/courses/unity_beginner/edit',
            [
                'name' => 'test name',
                'code' => 'test code',
                'price' => 10.0,
                'type' => 2
            ],
        );
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
    }

    public function testEditCourseNotFound(): void
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->adminEmail,
            "password" => $this->adminPassword
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->jsonRequest(
            'POST',
            '/api/v1/courses/test buy/edit',
            [
                'name' => 'test name',
                'code' => 'test code',
                'price' => 10.0,
                'type' => 2
            ],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token'],
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json'
            ],
        );
        $this->assertResponseCode(Response::HTTP_CONFLICT);
    }
}