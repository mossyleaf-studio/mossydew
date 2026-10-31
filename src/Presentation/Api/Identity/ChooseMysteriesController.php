<?php

declare(strict_types=1);

namespace App\Presentation\Api\Identity;

use App\Application\Identity\ChooseMysteries\ChooseMysteriesHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/me/mysteries', name: 'api_me_mysteries', methods: ['PUT'], format: 'json')]
final class ChooseMysteriesController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] MysteriesPayload $payload, ChooseMysteriesHandler $chooseMysteries): Response
    {
        $chooseMysteries($payload->enabled);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
