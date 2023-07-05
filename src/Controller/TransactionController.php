<?php

namespace App\Controller;

use App\DTO\TransactionResponseDTO;
use App\Entity\Transaction;
use App\Enum\TransactionEnum;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class TransactionController extends AbstractController
{
    private ObjectManager $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/api/v1/transactions", name="app_transactions", methods={"GET"})
     * @OA\Get(
     *     summary="Получение информации о транзакциях",
     *     description="Получение информации о транзакциях"
     * )
     * @OA\Tag(name="Transactions")
     *  @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=false,
     *         description="Тип транзакций 0 - покупки, 1 - пополнения",
     *         @OA\Property(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="code",
     *         in="query",
     *         required=false,
     *         description="Код курса",
     *         @OA\Property(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="skip_expired",
     *         in="query",
     *         required=false,
     *         description="Пропускать просроченные записи",
     *         @OA\Property(type="boolean")
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="Данные о транзакциях",
     *          @OA\JsonContent(
     *              @OA\Examples(
     *                  summary="Данные о транзакциях",
     *                  example="test",
     *                  value={{"id":54,"type":"deposit","value":100.5,"creationDate":"2023-07-0405:56:16"},{"id":56,"course":"unity_beginner","type":"payment","value":20,"creationDate":"2023-07-0405:56:17"},{"id":58,"course":"UE5pro","type":"payment","value":10,"creationDate":"2023-07-0405:58:45","expirationDate":"2023-07-1105:58:45"},{"id":59,"course":"Godot4beginner","type":"payment","value":0,"creationDate":"2023-07-0407:30:54"}}
     *              )
     *          )
     *     ),
     *     @OA\Response(
     *          response=401,
     *          description="UNAUTHORIZED",
     *          @OA\JsonContent(
     *              @OA\Examples(
     *              summary="Информация о курсе",
     *              example="test",
     *              value={"code": 401,"message": "JWT Token not found"}
     *              )
     *          )
     *     )
     *
     * @Security(name="Bearer")
     */
    public function getTransactions(Request $request, JWTTokenManagerInterface $jwtManager, TokenStorageInterface $tokenStorageInterface): JsonResponse
    {
        if (!$tokenStorageInterface->getToken()) {
            return new JsonResponse(['errors' => 'Нет токена'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$this->getUser()) {
            return new JsonResponse(['errors' => 'Пользователь не авторизован'], Response::HTTP_UNAUTHORIZED);
        }
        $type = $request->query->get('type');
        $code = $request->query->get('code');
        $skip_expired = $request->query->get('skip_expired');
        $transactions = $this->entityManager->getRepository(Transaction::class)->findByFilters($this->getUser(), $type, $code, $skip_expired);
        $result = [];
        foreach ($transactions as $transaction) {
            $result[] = new TransactionResponseDTO($transaction);
        }
        return new JsonResponse($result, Response::HTTP_OK);
    }
}
