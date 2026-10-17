<?php

declare(strict_types=1);

namespace App\Presentation\Api\Planning;

use App\Application\Planning\ClassifyTask\ClassifyTask;
use App\Application\Planning\ClassifyTask\ClassifyTaskHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/tasks/{id}/classify', name: 'api_tasks_classify', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class ClassifyTaskController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] ClassifyPayload $payload, ClassifyTaskHandler $classifyTask): JsonResponse
    {
        return $this->json($classifyTask(new ClassifyTask($id, $payload->quadrant, $payload->withSubtasks)));
    }
}
