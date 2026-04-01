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
        summary: 'Tela inicial com atalhos de navegacao',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Landing page com links para Swagger e Dashboard'
            ),
        ]
    )]
    public function index()
    {
        $path = BASE_PATH . '/storage/view/index.html';
        $html = file_get_contents($path);

        if ($html === false) {
            return $this->response
                ->raw('Index file not found.')
                ->withStatus(500)
                ->withHeader('content-type', 'text/plain; charset=utf-8');
        }

        return $this->response
            ->raw($html)
            ->withHeader('content-type', 'text/html; charset=utf-8');
    }
}
