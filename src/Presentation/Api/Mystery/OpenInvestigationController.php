<?php

declare(strict_types=1);

namespace App\Presentation\Api\Mystery;

use App\Application\Mystery\OpenInvestigation\OpenInvestigationHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mystery/investigations', name: 'api_mystery_open', methods: ['POST'], format: 'json')]
final class OpenInvestigationController extends AbstractController
{
    public function __invoke(OpenInvestigationHandler $openInvestigation): JsonResponse
    {
        return $this->json($openInvestigation(), Response::HTTP_CREATED);
    }
}
