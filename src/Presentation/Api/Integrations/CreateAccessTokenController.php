<?php

declare(strict_types=1);

namespace App\Presentation\Api\Integrations;

use App\Application\Identity\CreateAccessToken\CreateAccessTokenHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/integrations/injust/token', name: 'api_integrations_injust_token_create', methods: ['POST'], format: 'json')]
final class CreateAccessTokenController extends AbstractController
{
    public function __invoke(CreateAccessTokenHandler $createToken): JsonResponse
    {
        return $this->json(['token' => $createToken()], Response::HTTP_CREATED);
    }
}
