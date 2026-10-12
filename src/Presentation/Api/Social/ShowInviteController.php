<?php

declare(strict_types=1);

namespace App\Presentation\Api\Social;

use App\Application\Social\ShowInvite\ShowInviteHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/friends/invites/{token}', name: 'api_friends_invites_show', requirements: ['token' => '[0-9a-f]{48}'], methods: ['GET'], format: 'json')]
final class ShowInviteController extends AbstractController
{
    public function __invoke(string $token, ShowInviteHandler $showInvite): JsonResponse
    {
        return $this->json($showInvite($token));
    }
}
