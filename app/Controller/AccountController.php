<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Account;
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
}
