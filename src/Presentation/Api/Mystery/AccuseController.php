<?php

declare(strict_types=1);

namespace App\Presentation\Api\Mystery;

use App\Application\Mystery\Accuse\Accuse;
use App\Application\Mystery\Accuse\AccuseHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/mystery/investigations/{id}/accusation', name: 'api_mystery_accusation', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class AccuseController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] AccusationPayload $payload, AccuseHandler $accuse): Response
    {
        $accuse(new Accuse($id, $payload->placements, $payload->murderer));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
