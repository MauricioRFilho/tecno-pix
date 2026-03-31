<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller;

use Hyperf\Swagger\Annotation as OA;

#[OA\HyperfServer(name: 'http')]
#[OA\Tag(name: 'System', description: 'Rotas de sistema')]
class IndexController extends AbstractController
{
    #[OA\Get(
        path: '/',
        operationId: 'rootIndex',
        summary: 'Endpoint inicial da API',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Resposta padrao da API',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'method', type: 'string', example: 'GET'),
                        new OA\Property(property: 'message', type: 'string', example: 'Hello Hyperf.'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function index()
    {
        $user = $this->request->input('user', 'Hyperf');
        $method = $this->request->getMethod();

        return [
            'method' => $method,
            'message' => "Hello {$user}.",
        ];
    }
}
