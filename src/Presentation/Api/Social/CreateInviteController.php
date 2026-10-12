<?php

declare(strict_types=1);

namespace App\Presentation\Api\Social;

use App\Application\Social\CreateInvite\CreateInviteHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/friends/invites', name: 'api_friends_invites_create', methods: ['POST'], format: 'json')]
final class CreateInviteController extends AbstractController
{
    public function __invoke(CreateInviteHandler $createInvite): JsonResponse
    {
        return $this->json($createInvite(), Response::HTTP_CREATED);
    }
}
