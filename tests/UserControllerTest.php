<?php

namespace App\Tests;

use App\Entity\User;
use App\Service\PaymentService;
use App\Tests\AbstractTest;
use App\DataFixtures\AppFixtures;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserControllerTest extends AbstractTest
{
    private string $authURL = '/api/v1/auth';
    private string $registerURL = '/api/v1/register';
    private string $getCurrentUserURL = '/api/v1/users/current';
    private string $userEmail = 'user@gmail.com';
    private string $userPassword = 'user';

    protected function getFixtures(): array
    {
        return [
            new AppFixtures(
                $this->getContainer()->get(UserPasswordHasherInterface::class),
                $this->getContainer()->get(PaymentService::class)
            )
        ];
    }
    public function testAuthSuccess()
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->userEmail,
            "password" => $this->userPassword
        ]);
        $this->assertResponseCode(Response::HTTP_OK);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotNull($responseData['token']);
    }
    public function testAuthEmptyUsername()
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => "",
            "password" => "235346545"
        ]);
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
    }
    public function testAuthEmptyPassword()
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => "example@example.com",
            "password" => ""
        ]);
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
    }
    public function testAuthInvalidUsername()
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => "example@example.com",
            "password" => $this->userPassword
        ]);
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("Invalid credentials.", $responseData['message']);
    }
    public function testAuthInvalidPassword()
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->userEmail,
            "password" => "3456378465926"
        ]);
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("Invalid credentials.", $responseData['message']);
    }
    public function testRegisterSuccess()
    {
        $client = static::getClient();
        $email = 'example@example.com';
        $password = 'password';

        $client->jsonRequest('POST', $this->registerURL, [
            "username" => $email,
            "password" => $password
        ]);
        $this->assertResponseCode(Response::HTTP_CREATED);

        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotNull($responseData['token']);
        $this->assertSame(1, $this->getEntityManager()->getRepository(User::class)->count(['email' => $email]));

        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $email,
            "password" => $password
        ]);
        $this->assertResponseCode(Response::HTTP_OK);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotNull($responseData['token']);
    }
    public function testRegisterEmptyUsername()
    {
        $client = static::getClient();
        $password = 'password';
        $client->jsonRequest('POST', $this->registerURL, [
            "username" => "",
            "password" => $password,
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("Поле Email должно быть заполнено", $responseData['errors'][0]);
    }
    public function testRegisterEmptyPassword()
    {
        $client = static::getClient();
        $email = 'example@example.com';
        $client->jsonRequest('POST', $this->registerURL, [
            "username" => $email,
            "password" => ""
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("Поле Password должно быть заполнено", $responseData['errors'][0]);
    }
    public function testRegisterInvalidPassword()
    {
        $client = static::getClient();
        $email = 'example@example.com';
        $client->jsonRequest('POST', $this->registerURL, [
            "username" => $email,
            "password" => "12345"
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("Поле Password должно содержать минимум 6 символов", $responseData['errors'][0]);
    }
    public function testRegisterInvalidUsername()
    {
        $client = static::getClient();
        $password = 'password';
        $client->jsonRequest('POST', $this->registerURL, [
            "username" => "@",
            "password" => $password
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("Неверный Email адрес", $responseData['errors'][0]);
    }
    public function testRegisterBusyUsername()
    {
        $client = static::getClient();
        $password = 'password';
        $client->jsonRequest('POST', $this->registerURL, [
            "username" => $this->userEmail,
            "password" => $password
        ]);
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("Email уже существует", $responseData['errors'][0]);
    }
    public function testGetCurrentUserSuccess()
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->userEmail,
            "password" => $this->userPassword
        ]);
        $this->assertResponseCode(Response::HTTP_OK);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $token = $responseData['token'];
        $this->assertNotNull($responseData['token']);
        $client->request('GET', $this->getCurrentUserURL, [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $this->assertResponseCode(Response::HTTP_OK);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($this->userEmail, $responseData['username']);
        $this->assertTrue(in_array('ROLE_USER', $responseData['roles'], true));
    }
    public function testGetCurrentUserFailed()
    {
        $client = static::getClient();
        $client->jsonRequest('POST', $this->authURL, [
            "username" => $this->userEmail,
            "password" => $this->userPassword
        ]);
        $this->assertResponseCode(Response::HTTP_OK);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotNull($responseData['token']);

        $client->request('GET', $this->getCurrentUserURL, [], [], []);
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED);
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals("JWT Token not found", $responseData['message']);
    }
}
