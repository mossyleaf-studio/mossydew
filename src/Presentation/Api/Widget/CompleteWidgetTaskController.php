<?php

declare(strict_types=1);

namespace App\Presentation\Api\Widget;

use App\Application\Planning\CompleteTask\CompleteTaskHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/widget/tasks/{id}/complete', name: 'widget_tasks_complete', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class CompleteWidgetTaskController extends AbstractController
{
    public function __invoke(Ulid $id, CompleteTaskHandler $completeTask): Response
    {
        $completeTask($id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
