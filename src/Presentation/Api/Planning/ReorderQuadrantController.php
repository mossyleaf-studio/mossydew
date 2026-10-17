<?php

declare(strict_types=1);

namespace App\Presentation\Api\Planning;

use App\Application\Planning\ReorderQuadrant\ReorderQuadrant;
use App\Application\Planning\ReorderQuadrant\ReorderQuadrantHandler;
use App\Domain\Planning\Quadrant;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

#[Route('/api/matrix/{quadrant}/order', name: 'api_matrix_order', methods: ['PUT'], format: 'json')]
final class ReorderQuadrantController extends AbstractController
{
    public function __invoke(Quadrant $quadrant, #[MapRequestPayload] OrderPayload $payload, ReorderQuadrantHandler $reorder): Response
    {
        $reorder(new ReorderQuadrant($quadrant, array_map(Ulid::fromString(...), $payload->ids), $payload->withSubtasks));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
