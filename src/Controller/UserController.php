<?php

namespace App\Controller;

use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use PHP_CodeSniffer\Tokenizers\JS;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\DTO\UserDTO;
use App\Entity\User;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

use function Sodium\add;

class UserController extends AbstractController
{
    private Serializer $serializer;
    private ValidatorInterface $validator;
    private UserPasswordHasherInterface $passwordHasher;
    private ObjectManager $entityManager;
    private TokenStorageInterface $tokenStorageInterface;
    private JWTTokenManagerInterface $jwtManager;

    public function __construct(
        ManagerRegistry $doctrine,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface $tokenStorageInterface,
        JWTTokenManagerInterface $jwtManager
    ) {
        $this->entityManager = $doctrine->getManager();
        $this->serializer = SerializerBuilder::create()->build();
        $this->validator = $validator;
        $this->passwordHasher = $passwordHasher;
        $this->tokenStorageInterface = $tokenStorageInterface;
        $this->jwtManager = $jwtManager;
    }
    /**
     * @Route("/api/v1/auth", name="api_auth", methods={"POST"})
     * @OA\Post(
     *     path="/api/v1/auth",
     *     summary="Аутентификация пользователя и получение JWT-токена",
     *     description="Аутентификация пользователя и получение JWT-токена"
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="username",
     *          type="string",
     *          description="email пользователя",
     *          example="user@gmail.com",
     *        ),
     *        @OA\Property(
     *          property="password",
     *          type="string",
     *          description="пароль пользователя",
     *          example="user",
     *        ),
     *     )
     *)
     * @OA\Response(
     *     response=200,
     *     description="Аутентификация пользователя и получение JWT-токена",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="token",
     *          type="string",
     *        ),
     *     @OA\Property(
     *          property="refresh_token",
     *          type="string",
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response=401,
     *     description="Ошибка аутентификации",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="code",
     *          type="string",
     *          example="401"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Invalid credentials."
     *        ),
     *     )
     * )
     * @OA\Tag(name="User")
     */
    public function auth(): JsonResponse
    {
        return $this->json([]);
    }

    /**
     * @Route("/api/v1/register", name="api_register", methods={"POST"})
     * @OA\Post(
     *     path="/api/v1/register",
     *     summary="Регистрация пользователя и получение JWT-токена",
     *     description="Регистрация пользователя и получение JWT-токена"
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="username",
     *          type="string",
     *          description="email пользователя",
     *          example="new_user@gmail.com",
     *        ),
     *        @OA\Property(
     *          property="password",
     *          type="string",
     *          description="пароль пользователя",
     *          example="new_user",
     *        ),
     *     )
     *  )
     * )
     * @OA\Response(
     *     response=201,
     *     description="Успешная регистрация",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="token",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="roles",
     *          type="array",
     *          @OA\Items(
     *              type="string",
     *          ),
     *        ),
     *     ),
     * )
     * @OA\Response(
     *     response=400,
     *     description="Ошибка валидации",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="errors",
     *          type="array",
     *          @OA\Items(
     *                  type="string",
     *              )
     *          )
     *        )
     *     )
     * )
     * @OA\Tag(name="User")
     */
    public function register(
        Request $request,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager
    ): JsonResponse {
        $DTO_user = $this->serializer->deserialize($request->getContent(), UserDTO::class, 'json');
        $errors = $this->validator->validate($DTO_user);
        if (count($errors) > 0) {
            $errors_array = [];
            foreach ($errors as $error) {
                $errors_array[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errors_array], Response::HTTP_BAD_REQUEST);
        }
        if ($this->entityManager->getRepository(User::class)->findOneByEmail($DTO_user->getUsername())) {
            return new JsonResponse(['errors' => ['Email уже существует']], Response::HTTP_BAD_REQUEST);
        }

        $user = User::getFromDTO($DTO_user);

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $user->getPassword())
        );

        $refreshToken = $refreshTokenGenerator->createForUserWithTtl(
            $user,
            (new \DateTime())->modify('+1 month')->getTimestamp()
        );
        $refreshTokenManager->save($refreshToken);

        $this->entityManager->getRepository(User::class)->add($user, true);

        return new JsonResponse([
            'token' => $this->jwtManager->create($user),
            'roles' => $user->getRoles(),
        ], Response::HTTP_CREATED);
    }

    /**
     * @Route("/api/v1/users/current", name="api_get_current_user", methods={"GET"})
     * @OA\Get(
     *     path="/api/v1/users/current",
     *     summary="Получение информации о текущем пользователе",
     *     description="Получение информации о текущем пользователе"
     * )
     * @OA\Response(
     *     response=200,
     *     description="Получение информации о текущем пользователе",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="username",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="roles",
     *          type="array",
     *          @OA\Items(
     *              type="string"
     *          )
     *        ),
     *        @OA\Property(
     *          property="balance",
     *          type="number",
     *          format="float"
     *        )
     *     )
     * )
     * @OA\Response(
     *     response=401,
     *     description="Ошибка аутентификации",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="code",
     *          type="string",
     *          example="401"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Invalid credentials."
     *        ),
     *     )
     * )
     * @OA\Tag(name="User")
     * @Security(name="Bearer")
     */
    public function getCurrentUser(Request $request): JsonResponse
    {
        $decodedJwtToken = $this->jwtManager->decode($this->tokenStorageInterface->getToken());
        $user = $this->entityManager->getRepository(User::class)->findOneByEmail($decodedJwtToken['email']);
        return new JsonResponse([
            'username' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'balance' => $user->getBalance(),
        ], Response::HTTP_OK);
    }
}
