<?php

namespace App\Controller;

use App\DTO\CourseRequestDTO;
use App\DTO\CourseResponseDTO;
use App\Entity\Course;
use App\Enum\CourseEnum;
use App\Repository\CourseRepository;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CourseController extends AbstractController
{
    private ObjectManager $entityManager;
    private Serializer $serializer;


    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->serializer = SerializerBuilder::create()->build();
    }

    /**
     * @Route("/api/v1/courses", name="app_courses", methods={"GET"})
     * @OA\Get(
     *     summary="Получение информации о всех курсах",
     *     description="Получение информации о всех курсах"
     * )
     * @OA\Response(
     *     response=200,
     *     description="информация о всех курсах",
     *     @OA\JsonContent(
     *        @OA\Examples(
     *          summary="Информация о всех курсах",
     *          example="test",
     *          value={{"code":"godot_for_noobs","type":"free"},{"code":"unity_medium","price":20,"type":"buy"}}
     *          )
     *     )
     * )
     * @OA\Tag(name="Course")
     */
    public function getCourses(CourseRepository $courseRepository): JsonResponse
    {
        $result = [];
        $courses = $courseRepository->findAll();
        foreach ($courses as $course) {
            $result[] = new CourseResponseDTO($course);
        }
        return new JsonResponse($result);
    }

    /**
     * @Route("/api/v1/courses/{code}", name="app_course", methods={"GET"})
     * @OA\Get(
     *     summary="Получение информации о конкретном курсе",
     *     description="Получение информации о конкретном курсе"
     * )
     * @OA\Response(
     *     response=200,
     *     description="информация о курсе",
     *     @OA\JsonContent(
     *        @OA\Examples(
     *          summary="Информация о курсе",
     *          example="test",
     *          value={"code":"unity_medium","price":20,"type":"buy"}
     *          )
     *     )
     * )
     * @OA\Response(
     *          response=404,
     *          description="Not found",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     )
     * @OA\Tag(name="Course")
     */
    public function getCourse(string $code, CourseRepository $courseRepository)
    {
        $course = $courseRepository->findOneBy(["code" => $code]);
        if (!$course) {
            return new JsonResponse(['errors' => "Курс $code не найден"], Response::HTTP_NOT_FOUND);
        }
        return new JsonResponse(new CourseResponseDTO($course));
    }

    /**
     * @Route("api/v1/courses/new", name="api_new_course", methods={"POST"})
     * @OA\Post(
     *     description="New course",
     *     tags={"Course"},
     *     @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="name",
     *          type="string",
     *          description="Название курса",
     *          example="совершенно новый курс",
     *        ),
     *        @OA\Property(
     *          property="code",
     *          type="string",
     *          description="Уникальный код курса",
     *          example="totally_new_course",
     *        ),
     *        @OA\Property(
     *          property="type",
     *          type="int",
     *          description="Тип курса",
     *          example="1",
     *        ),
     *        @OA\Property(
     *          property="price",
     *          type="float",
     *          description="Цена курса",
     *          example="23.1",
     *        ),
     *     )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="Успешное добавление",
     *          @OA\JsonContent(
     *              schema="PayInfo",
     *              type="object",
     *              @OA\Property(property="success", type="boolean"),
     *          )
     *     ),
     *     @OA\Response(
     *          response=401,
     *          description="UNAUTHORIZED",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     ),
     *     @OA\Response(
     *          response=400,
     *          description="Название не может быть пустым",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="У курса должна быть цена",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     ),
     *     @OA\Response(
     *          response=409,
     *          description="Курс с этим кодом уже существует",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     ),
     * )
     * @Security(name="Bearer")
     */
    public function new(Request $request, JWTTokenManagerInterface $jwtManager, TokenStorageInterface $tokenStorageInterface, CourseRepository $courseRepository)
    {
        if (!$tokenStorageInterface->getToken()) {
            return new JsonResponse(['errors' => 'Нет токена'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$this->getUser() || !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles(), true)) {
            return new JsonResponse(['errors' => 'Пользователь не авторизован'], Response::HTTP_UNAUTHORIZED);
        }
        $course = $this->serializer->deserialize($request->getContent(), CourseRequestDTO::class, 'json');
        if ($course->name == null) {
            return new JsonResponse(['errors' => 'Название не может быть пустым'], Response::HTTP_BAD_REQUEST);
        }
        if ($course->type == CourseEnum::FREE) {
            $course->price = null;
        } else {
            if ($course->price == null) {
                return new JsonResponse(['errors' => 'Курс платный, укажите цену'], Response::HTTP_FORBIDDEN);
            }
        }
        if ($courseRepository->count(['code' => $course->code]) > 0) {
            return new JsonResponse(['errors' => 'Курс с таким кодом уже существует'], Response::HTTP_CONFLICT);
        }
        $courseRepository->add(Course::fromDTO($course), true);
        return new JsonResponse(['success' => true], Response::HTTP_CREATED);
    }

    /**
     * @Route("api/v1/courses/{code}/edit", name="api_edit_course", methods={"POST"})
     * @OA\Post(
     *     description="New course",
     *     tags={"Course"},
     *     @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="name",
     *          type="string",
     *          description="Название курса",
     *          example="совершенно новый курс",
     *        ),
     *        @OA\Property(
     *          property="code",
     *          type="string",
     *          description="Уникальный код курса",
     *          example="totally_new_course",
     *        ),
     *        @OA\Property(
     *          property="type",
     *          type="int",
     *          description="Тип курса",
     *          example="1",
     *        ),
     *        @OA\Property(
     *          property="price",
     *          type="float",
     *          description="Цена курса",
     *          example="23.1",
     *        ),
     *     )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="Успешное редактирование",
     *          @OA\JsonContent(
     *              schema="PayInfo",
     *              type="object",
     *              @OA\Property(property="success", type="boolean"),
     *          )
     *     ),
     *     @OA\Response(
     *          response=401,
     *          description="UNAUTHORIZED",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     ),
     *     @OA\Response(
     *          response=400,
     *          description="Название не может быть пустым",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     ),
     *     @OA\Response(
     *          response=403,
     *          description="У курса должна быть цена",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     ),
     *     @OA\Response(
     *          response=409,
     *          description="Курс с этим кодом уже существует",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     ),
     * )
     * @Security(name="Bearer")
     */
    public function edit(string $code, Request $request, JWTTokenManagerInterface $jwtManager, TokenStorageInterface $tokenStorageInterface, CourseRepository $courseRepository)
    {
        if (!$tokenStorageInterface->getToken() || !in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles(), true)) {
            return new JsonResponse(['errors' => 'Нет токена'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$this->getUser()) {
            return new JsonResponse(['errors' => 'Пользователь не авторизован'], Response::HTTP_UNAUTHORIZED);
        }
        $course = $this->serializer->deserialize($request->getContent(), CourseRequestDTO::class, 'json');
        if ($course->name == null) {
            return new JsonResponse(['errors' => 'Название не может быть пустым'], Response::HTTP_BAD_REQUEST);
        }
        if ($course->type == CourseEnum::FREE) {
            $course->price = null;
        } else {
            if ($course->price == null) {
                return new JsonResponse(['errors' => 'Курс платный, укажите цену'], Response::HTTP_FORBIDDEN);
            }
        }
        $edited_course = $courseRepository->findOneBy(['code' => $code]);
        if ($edited_course == null) {
            return new JsonResponse(['errors' => 'Курс с таким кодом не существует'], Response::HTTP_CONFLICT);
        }
        $edited_course->updateFromDTO($course);
        $courseRepository->add($edited_course, true);
        return new JsonResponse(['success' => true], Response::HTTP_OK);
    }



    /**
     * @Route("/api/v1/courses/{code}/pay", name="app_course_pay", methods={"POST"})
     * @OA\Post(
     *     summary="Покупка курса",
     *     description="Покупка курса"
     * )
     *      @OA\Response(
     *          response=200,
     *          description="Успешная оплата",
     *          @OA\JsonContent(
     *              schema="PayInfo",
     *              type="object",
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(property="course_type", type="string"),
     *          )
     *     ),
     *     @OA\Response(
     *          response=401,
     *          description="UNAUTHORIZED",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     ),
     *     @OA\Response(
     *          response=404,
     *          description="NOT FOUND",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     ),
     *     @OA\Response(
     *          response=406,
     *          description="Недостаточно средств",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     ),
     *     @OA\Response(
     *          response=409,
     *          description="Пользователь уже купил курс",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="error", type="string")
     *          )
     *     )
     * @OA\Tag(name="Course")
     * @Security(name="Bearer")
     */
    public function payCourse(string $code, PaymentService $paymentService, CourseRepository $courseRepository)
    {
        $course = $courseRepository->findOneBy(['code' => $code]);
        if (!$course) {
            return new JsonResponse(['errors' => "Курс $code не найден"], Response::HTTP_NOT_FOUND);
        }
        $response_body = [
            'success' => true,
            'course_type' => CourseEnum::NAMES[$course->getType()],
        ];
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['errors' => 'Пользователь не авторизован'], Response::HTTP_UNAUTHORIZED);
        }
        try {
            $transaction = $paymentService->payment($user, $course);
            $expires = $transaction->getExpirationDate() ?: null;
            if ($expires) {
                $response_body["expires"] = $expires;
            }
            return new JsonResponse($response_body, Response::HTTP_OK);
        } catch (\Exception $exception) {
            return new JsonResponse(['errors' => $exception->getMessage()], Response::HTTP_NOT_ACCEPTABLE);
        }
    }
}
