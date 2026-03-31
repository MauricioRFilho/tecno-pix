<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\WithdrawRequest;
use App\Request\WithdrawValidationException;
use App\Service\Exception\AccountNotFoundException;
use App\Service\Exception\InvalidPixKeyException;
use App\Service\Exception\InsufficientBalanceException;
use App\Service\Exception\InvalidScheduleException;
use App\Service\Exception\UnsupportedWithdrawMethodException;
use App\Service\WithdrawProcessorDispatcher;
use App\Service\WithdrawService;
use Hyperf\Swagger\Annotation as OA;

#[OA\HyperfServer(name: 'http')]
#[OA\Tag(name: 'Withdraw', description: 'Solicitacao de saque')]
class WithdrawController extends AbstractController
{
    #[OA\Post(
        path: '/account/{accountId}/balance/withdraw',
        operationId: 'createWithdraw',
        summary: 'Solicita um saque para uma conta',
        tags: ['Withdraw'],
        parameters: [
            new OA\Parameter(name: 'accountId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['method', 'amount', 'pix'],
                properties: [
                    new OA\Property(property: 'method', type: 'string', example: 'pix'),
                    new OA\Property(property: 'amount', type: 'number', format: 'float', example: 100.50),
                    new OA\Property(
                        property: 'pix',
                        properties: [
                            new OA\Property(property: 'type', type: 'string', example: 'email'),
                            new OA\Property(property: 'key', type: 'string', example: 'cliente@example.com'),
                        ],
                        type: 'object'
                    ),
                    new OA\Property(property: 'schedule', type: 'string', format: 'date-time', nullable: true),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Solicitacao aceita para processamento',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Withdraw request accepted.'),
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'account_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'method', type: 'string', example: 'pix'),
                        new OA\Property(property: 'amount', type: 'string', example: '100.50'),
                        new OA\Property(property: 'scheduled', type: 'boolean', example: false),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Payload ou regra de negocio invalida',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Conta nao encontrada',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Account not found.'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function store(
        string $accountId,
        WithdrawRequest $withdrawRequest,
        WithdrawService $withdrawService,
        WithdrawProcessorDispatcher $withdrawProcessorDispatcher
    )
    {
        try {
            $payload = $withdrawRequest->validate($accountId, $this->request->all());
        } catch (WithdrawValidationException $exception) {
            return $this->response
                ->json([
                    'message' => $exception->getMessage(),
                    'errors' => $exception->errors(),
                ])
                ->withStatus(422);
        }

        try {
            $withdraw = $withdrawService->create($payload);
        } catch (AccountNotFoundException $exception) {
            return $this->response->json(['message' => $exception->getMessage()])->withStatus(404);
        } catch (InsufficientBalanceException | InvalidScheduleException | UnsupportedWithdrawMethodException | InvalidPixKeyException $exception) {
            return $this->response->json(['message' => $exception->getMessage()])->withStatus(422);
        }

        if (! $withdraw['scheduled']) {
            $withdrawProcessorDispatcher->dispatch($withdraw['id']);
        }

        return $this->response
            ->json([
                'message' => 'Withdraw request accepted.',
                'id' => $withdraw['id'],
                'account_id' => $withdraw['account_id'],
                'method' => $withdraw['method'],
                'amount' => $withdraw['amount'],
                'scheduled' => $withdraw['scheduled'],
            ])
            ->withStatus(202);
    }
}
