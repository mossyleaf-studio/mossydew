<?php

declare(strict_types=1);

namespace App\Presentation\Api\Widget;

use App\Application\Identity\RevokeDeviceToken\RevokeDeviceToken;
use App\Application\Identity\RevokeDeviceToken\RevokeDeviceTokenHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;

#[Route('/widget/token', name: 'widget_token_revoke', methods: ['DELETE'], format: 'json')]
final class RevokeDeviceTokenController extends AbstractController
{
    public function __invoke(Request $request, RevokeDeviceTokenHandler $revokeToken): Response
    {
        $revokeToken(new RevokeDeviceToken((string) new HeaderAccessTokenExtractor()->extractAccessToken($request)));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
