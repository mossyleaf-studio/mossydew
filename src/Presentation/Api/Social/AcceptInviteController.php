<?php

declare(strict_types=1);

namespace App\Presentation\Api\Social;

use App\Application\Social\AcceptInvite\AcceptInviteHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/friends/invites/{token}/accept', name: 'api_friends_invites_accept', requirements: ['token' => '[0-9a-f]{48}'], methods: ['POST'], format: 'json')]
final class AcceptInviteController extends AbstractController
{
    public function __invoke(string $token, AcceptInviteHandler $acceptInvite): JsonResponse
    {
        return $this->json($acceptInvite($token), Response::HTTP_CREATED);
    }
}
