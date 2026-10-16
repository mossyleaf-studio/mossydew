<?php

declare(strict_types=1);

namespace App\Presentation\Api\Widget;

use App\Application\Identity\IssueDeviceToken\IssueDeviceTokenHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/widget/token', name: 'api_widget_token_issue', methods: ['POST'], format: 'json')]
final class IssueDeviceTokenController extends AbstractController
{
    public function __invoke(IssueDeviceTokenHandler $issueToken): JsonResponse
    {
        return $this->json(['token' => $issueToken()], Response::HTTP_CREATED);
    }
}
