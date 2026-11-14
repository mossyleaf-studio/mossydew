<?php

declare(strict_types=1);

namespace App\Presentation\Api\Planning;

use App\Application\Planning\ReorderProjects\ReorderProjects;
use App\Application\Planning\ReorderProjects\ReorderProjectsHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

#[Route('/api/projects/order', name: 'api_projects_order', methods: ['PUT'], format: 'json')]
final class ReorderProjectsController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, ReorderProjectsHandler $reorder): Response
    {
        $reorder(new ReorderProjects(array_map(Ulid::fromString(...), $payload->ids)));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
