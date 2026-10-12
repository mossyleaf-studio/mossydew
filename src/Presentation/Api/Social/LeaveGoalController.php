<?php

declare(strict_types=1);

namespace App\Presentation\Api\Social;

use App\Application\Social\LeaveGoal\LeaveGoalHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/goals/{id}/leave', name: 'api_goals_leave', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class LeaveGoalController extends AbstractController
{
    public function __invoke(Ulid $id, LeaveGoalHandler $leaveGoal): Response
    {
        $leaveGoal($id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
