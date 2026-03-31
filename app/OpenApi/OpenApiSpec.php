<?php

declare(strict_types=1);

namespace App\OpenApi;

use Hyperf\Swagger\Annotation as OA;

#[OA\Info(
    version: '0.1.0',
    title: 'Tecno Pix API',
    description: 'Documentacao inicial da API de conta digital com saque PIX.'
)]
#[OA\Server(
    url: 'http://127.0.0.1:9501',
    description: 'API local'
)]
#[OA\Tag(name: 'System', description: 'Rotas de sistema')]
#[OA\Tag(name: 'Accounts', description: 'Rotas de consulta de contas')]
class OpenApiSpec
{
}

