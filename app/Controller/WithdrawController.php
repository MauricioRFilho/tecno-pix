<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\WithdrawRequest;
use App\Request\WithdrawValidationException;
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
                description: 'Payload invalido',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function store(string $accountId, WithdrawRequest $withdrawRequest)
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

        return $this->response
            ->json([
                'message' => 'Withdraw request accepted.',
                'account_id' => $payload['account_id'],
                'method' => $payload['method'],
                'amount' => $payload['amount'],
                'scheduled' => $payload['schedule'] !== null,
            ])
            ->withStatus(202);
    }
}
