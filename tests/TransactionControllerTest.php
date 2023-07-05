<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\User;
use App\Enum\CourseEnum;
use App\Service\PaymentService;
use App\Tests\AbstractTest;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TransactionControllerTest extends AbstractTest
{
    private string $authURL = '/api/v1/auth';
    private string $fixtureEmail = 'user@gmail.com';
    private string $fixturePassword = 'user';

    protected function getFixtures(): array
    {
        return [
            new AppFixtures(
                $this->getContainer()->get(UserPasswordHasherInterface::class),
                $this->getContainer()->get(PaymentService::class),
            )
        ];
    }

    public function testGetTransactions(): void
    {
        $client = static::getClient();

        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->fixtureEmail,
            "password" => $this->fixturePassword
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->jsonRequest('GET', '/api/v1/transactions', [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token'],
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json'
        ]);
        $client->getResponse()->getContent();
        $transactionsInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $transactionsInfo);
    }
    public function testGetTransactionsTypeDepositSkipExp(): void
    {
        $client = static::getClient();

        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->fixtureEmail,
            "password" => $this->fixturePassword
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->jsonRequest(
            'GET',
            '/api/v1/transactions?type=1',
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token'],
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json'
            ]
        );
        $transactionsInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $transactionsInfo);
    }
    public function testGetTransactionsCode(): void
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->fixtureEmail,
            "password" => $this->fixturePassword
        ]);
        $userInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->jsonRequest(
            'GET',
            '/api/v1/transactions?code=unity_beginner',
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userInfo['token'],
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json'
            ]
        );
        $client->getResponse()->getContent();
        $transactionsInfo = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $transactionsInfo);
    }
    public function testGetTransactionsUnauthorized(): void
    {
        $client = static::getClient();

        $client->jsonRequest('GET', '/api/v1/transactions/');
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
    }
}
