<?php

declare(strict_types=1);

namespace App\Presentation\Api\Integrations;

use App\Application\Planning\ShowInjustJournal\ShowInjustJournalHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/integrations/injust/journal', name: 'integrations_injust_journal', methods: ['GET'], format: 'json')]
final class ShowInjustJournalController extends AbstractController
{
    public function __invoke(ShowInjustJournalHandler $showJournal): JsonResponse
    {
        return $this->json($showJournal());
    }
}
