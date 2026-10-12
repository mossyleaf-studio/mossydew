<?php

declare(strict_types=1);

namespace App\Presentation\Api\Integrations;

use App\Application\Planning\ShowInjustLink\ShowInjustLinkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/integrations/injust', name: 'api_integrations_injust', methods: ['GET'], format: 'json')]
final class ShowInjustLinkController extends AbstractController
{
    public function __invoke(ShowInjustLinkHandler $showLink): JsonResponse
    {
        return $this->json($showLink());
    }
}
