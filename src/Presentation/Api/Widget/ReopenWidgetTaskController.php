<?php

declare(strict_types=1);

namespace App\Presentation\Api\Widget;

use App\Application\Planning\ReopenTask\ReopenTaskHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/widget/tasks/{id}/reopen', name: 'widget_tasks_reopen', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class ReopenWidgetTaskController extends AbstractController
{
    public function __invoke(Ulid $id, ReopenTaskHandler $reopenTask): Response
    {
        $reopenTask($id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
