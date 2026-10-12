<?php

declare(strict_types=1);

namespace App\Presentation\Api\Social;

use App\Application\Social\ListFriends\ListFriendsHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/friends', name: 'api_friends_list', methods: ['GET'], format: 'json')]
final class ListFriendsController extends AbstractController
{
    public function __invoke(ListFriendsHandler $listFriends): JsonResponse
    {
        return $this->json($listFriends());
    }
}
