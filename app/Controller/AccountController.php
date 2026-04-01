<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Account;
use App\Support\Uuid;
use Hyperf\Swagger\Annotation as OA;

#[OA\HyperfServer(name: 'http')]
#[OA\Tag(name: 'Accounts', description: 'Consultas de contas')]
class AccountController extends AbstractController
{
    #[OA\Get(
        path: '/accounts',
        operationId: 'listAccounts',
        summary: 'Lista as contas cadastradas',
        tags: ['Accounts'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de contas',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Account')
                )
            ),
        ]
    )]
    public function index()
    {
        $accounts = Account::query()
            ->orderBy('created_at')
            ->get();

        return $this->response->json($accounts);
    }

    #[OA\Post(
        path: '/accounts',
        operationId: 'createAccount',
        summary: 'Cria uma conta',
        tags: ['Accounts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Conta Nova'),
                    new OA\Property(property: 'balance', type: 'number', format: 'float', example: 1000),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Conta criada',
                content: new OA\JsonContent(ref: '#/components/schemas/Account')
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
    public function store()
    {
        $payload = $this->request->all();
        $errors = [];

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            $errors['name'][] = 'The name field is required.';
        } elseif (mb_strlen($name) > 120) {
            $errors['name'][] = 'The name field must not be greater than 120 characters.';
        }

        $balanceRaw = $payload['balance'] ?? '0';
        $balance = '0.00';
        if (! is_string($balanceRaw) && ! is_int($balanceRaw) && ! is_float($balanceRaw)) {
            $errors['balance'][] = 'The balance must be a number.';
        } else {
            $balanceString = trim((string) $balanceRaw);
            if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $balanceString)) {
                $errors['balance'][] = 'The balance format is invalid.';
            } elseif (bccomp($balanceString, '0', 2) < 0) {
                $errors['balance'][] = 'The balance must be at least 0.';
            } else {
                $parts = explode('.', $balanceString, 2);
                $balance = count($parts) === 1
                    ? $parts[0] . '.00'
                    : $parts[0] . '.' . str_pad($parts[1], 2, '0');
            }
        }

        if ($errors !== []) {
            return $this->response
                ->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $errors,
                ])
                ->withStatus(422);
        }

        $account = Account::query()->create([
            'id' => Uuid::v4(),
            'name' => $name,
            'balance' => $balance,
        ]);

        return $this->response->json($account)->withStatus(201);
    }
}
