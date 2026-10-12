<?php

declare(strict_types=1);

namespace App\Presentation\Api\Integrations;

use App\Application\Planning\ReceiveInjustFeed\InjustFeed;
use App\Application\Planning\ReceiveInjustFeed\ReceiveInjustFeed;
use App\Application\Planning\ReceiveInjustFeed\ReceiveInjustFeedHandler;
use App\Domain\Planning\Exception\UnreadableFeed;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/integrations/injust/feed', name: 'integrations_injust_feed', methods: ['PUT'], format: 'json')]
final class ReceiveInjustFeedController extends AbstractController
{
    public function __invoke(Request $request, ReceiveInjustFeedHandler $receive): Response
    {
        try {
            $feed = $request->toArray();
        } catch (JsonException) {
            throw new UnreadableFeed();
        }
        $receive(new ReceiveInjustFeed(InjustFeed::read($feed)));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
