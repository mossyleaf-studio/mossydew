<?php

declare(strict_types=1);

namespace App\Presentation\Api\Social;

use App\Application\Social\RemoveFriend\RemoveFriendHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/friends/{id}', name: 'api_friends_remove', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class RemoveFriendController extends AbstractController
{
    public function __invoke(Ulid $id, RemoveFriendHandler $removeFriend): Response
    {
        $removeFriend($id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
