<?php

declare(strict_types=1);

namespace App\Presentation\Api\Social;

use App\Application\Social\SetGoal\SetGoal;
use App\Application\Social\SetGoal\SetGoalHandler;
use App\Domain\Shared\Day;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/goals', name: 'api_goals_set', methods: ['POST'], format: 'json')]
final class SetGoalController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] SetGoalPayload $payload, SetGoalHandler $setGoal): JsonResponse
    {
        return $this->json($setGoal(new SetGoal($payload->title, $payload->target, Day::of($payload->endsOn), $payload->friendIds)), Response::HTTP_CREATED);
    }
}
