<?php

declare(strict_types=1);

namespace App\Presentation\Api\Identity;

use App\Application\Identity\ChooseIcons\ChooseIconsHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/me/icons', name: 'api_me_icons', methods: ['PUT'], format: 'json')]
final class ChooseIconsController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] IconsPayload $payload, ChooseIconsHandler $chooseIcons): Response
    {
        $chooseIcons($payload->icons);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
