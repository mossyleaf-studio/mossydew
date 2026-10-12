<?php

declare(strict_types=1);

namespace App\Presentation\Api\Planning;

use App\Application\Planning\ImportSuperProductivity\ImportSuperProductivity;
use App\Application\Planning\ImportSuperProductivity\ImportSuperProductivityHandler;
use App\Application\Planning\ImportSuperProductivity\SuperProductivityBackup;
use App\Domain\Planning\Exception\UnreadableBackup;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/imports/super-productivity', name: 'api_imports_super_productivity', methods: ['POST'], format: 'json')]
final class ImportSuperProductivityController extends AbstractController
{
    public function __invoke(Request $request, ImportSuperProductivityHandler $import): JsonResponse
    {
        try {
            $backup = $request->toArray();
        } catch (JsonException) {
            throw new UnreadableBackup();
        }

        return $this->json($import(new ImportSuperProductivity(SuperProductivityBackup::read($backup))));
    }
}
