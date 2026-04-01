<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\AccountWithdraw;
use Hyperf\Swagger\Annotation as OA;

#[OA\HyperfServer(name: 'http')]
#[OA\Tag(name: 'Operations', description: 'Consultas de operacoes')]
class OperationController extends AbstractController
{
    #[OA\Get(
        path: '/operations',
        operationId: 'listOperations',
        summary: 'Lista operacoes de saque',
        tags: ['Operations'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de operacoes',
                content: new OA\JsonContent(type: 'array')
            ),
        ]
    )]
    public function index()
    {
        $limit = (int) $this->request->query('limit', 50);
        $limit = max(1, min(200, $limit));

        $accountId = (string) $this->request->query('account_id', '');

        $query = AccountWithdraw::query()
            ->with(['pix'])
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($accountId !== '') {
            $query->where('account_id', $accountId);
        }

        $operations = $query->get()->map(static function (AccountWithdraw $withdraw): array {
            return [
                'id' => (string) $withdraw->id,
                'account_id' => (string) $withdraw->account_id,
                'method' => (string) $withdraw->method,
                'amount' => (string) $withdraw->amount,
                'scheduled' => (bool) $withdraw->scheduled,
                'scheduled_for' => $withdraw->scheduled_for?->format('Y-m-d H:i:s'),
                'done' => (bool) $withdraw->done,
                'error' => (bool) $withdraw->error,
                'error_reason' => $withdraw->error_reason,
                'processed_at' => $withdraw->processed_at?->format('Y-m-d H:i:s'),
                'created_at' => $withdraw->created_at?->format('Y-m-d H:i:s'),
                'pix' => $withdraw->pix ? [
                    'type' => (string) $withdraw->pix->type,
                    'key' => (string) $withdraw->pix->key,
                ] : null,
            ];
        })->values();

        return $this->response->json($operations);
    }
}
