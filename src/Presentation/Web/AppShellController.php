<?php

declare(strict_types=1);

namespace App\Presentation\Web;

use App\Presentation\ApiPreload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{path}', name: 'app', requirements: ['path' => '(?!api/|widget/|integrations/|build|_|login|logout|sw\.js|manifest).*'], defaults: ['path' => ''], methods: ['GET'], priority: -100)]
final class AppShellController extends AbstractController
{
    private const array ALWAYS = ['/api/projects', '/api/player'];

    public function __construct(private readonly ApiPreload $preload)
    {
    }

    public function __invoke(string $path): Response
    {
        return $this->render('app.html.twig', [
            'preloaded' => $this->preload->json([...self::ALWAYS, ...$this->pageUrls(trim($path, '/'))]),
        ]);
    }

    /**
     * @return list<string>
     */
    private function pageUrls(string $path): array
    {
        if (1 === preg_match('#^projects/([0-9A-HJKMNP-TV-Z]{26})$#', $path, $matches)) {
            return ['/api/projects/'.$matches[1].'/tasks'];
        }

        return match ($path) {
            '', 'today' => ['/api/tasks/today'],
            'upcoming' => ['/api/tasks/upcoming'],
            'inbox' => ['/api/tasks/inbox'],
            'matrix' => ['/api/matrix'],
            'done' => ['/api/tasks/done'],
            'stats' => ['/api/stats'],
            'herbarium' => ['/api/herbarium'],
            'greenhouse' => ['/api/greenhouse'],
            'achievements' => ['/api/achievements'],
            'settings' => ['/api/integrations/injust'],
            'friends' => ['/api/friends'],
            default => [],
        };
    }
}
