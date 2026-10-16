<?php

declare(strict_types=1);

namespace App\Presentation\Api\Widget;

use App\Application\Planning\ListWidgetTasks\ListWidgetTasksHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/widget/today', name: 'widget_today', methods: ['GET'], format: 'json')]
final class ListWidgetTasksController extends AbstractController
{
    public function __invoke(ListWidgetTasksHandler $listTasks): JsonResponse
    {
        return $this->json($listTasks());
    }
}
