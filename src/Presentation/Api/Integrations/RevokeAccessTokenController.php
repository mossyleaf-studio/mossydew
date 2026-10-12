<?php

declare(strict_types=1);

namespace App\Presentation\Api\Integrations;

use App\Application\Identity\RevokeAccessToken\RevokeAccessTokenHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/integrations/injust/token', name: 'api_integrations_injust_token_revoke', methods: ['DELETE'], format: 'json')]
final class RevokeAccessTokenController extends AbstractController
{
    public function __invoke(RevokeAccessTokenHandler $revokeToken): Response
    {
        $revokeToken();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
