<?php

declare(strict_types=1);

namespace App\Controller;

class DashboardController extends AbstractController
{
    public function index()
    {
        $path = BASE_PATH . '/storage/view/dashboard.html';
        $html = file_get_contents($path);

        if ($html === false) {
            return $this->response
                ->raw('Dashboard file not found.')
                ->withStatus(500)
                ->withHeader('content-type', 'text/plain; charset=utf-8');
        }

        return $this->response
            ->raw($html)
            ->withHeader('content-type', 'text/html; charset=utf-8');
    }
}
