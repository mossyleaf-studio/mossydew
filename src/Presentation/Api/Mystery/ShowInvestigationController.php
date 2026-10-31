<?php

declare(strict_types=1);

namespace App\Presentation\Api\Mystery;

use App\Application\Mystery\ShowInvestigation\ShowInvestigationHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mystery', name: 'api_mystery', methods: ['GET'], format: 'json')]
final class ShowInvestigationController extends AbstractController
{
    public function __invoke(ShowInvestigationHandler $showInvestigation): JsonResponse
    {
        return $this->json($showInvestigation());
    }
}
