<?php

declare(strict_types=1);

namespace App\Presentation\Api\Mystery;

use App\Application\Mystery\TakeNotes\TakeNotes;
use App\Application\Mystery\TakeNotes\TakeNotesHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/mystery/investigations/{id}/notes', name: 'api_mystery_notes', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class TakeNotesController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] NotesPayload $payload, TakeNotesHandler $takeNotes): Response
    {
        $takeNotes(new TakeNotes($id, $payload->placements, $payload->crosses));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
