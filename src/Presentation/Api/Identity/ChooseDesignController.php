<?php

declare(strict_types=1);

namespace App\Presentation\Api\Identity;

use App\Application\Identity\ChooseDesign\ChooseDesignHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/me/design', name: 'api_me_design', methods: ['PUT'], format: 'json')]
final class ChooseDesignController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] DesignPayload $payload, ChooseDesignHandler $chooseDesign): Response
    {
        $chooseDesign($payload->design);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
