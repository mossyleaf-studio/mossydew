<?php

declare(strict_types=1);

namespace App\Application\Planning\ShowInjustLink;

use App\Application\Identity\CurrentUser;
use App\Domain\Identity\AccessTokenRepository;
use App\Domain\Planning\Injust\InjustLinkRepository;

final readonly class ShowInjustLinkHandler
{
    public function __construct(
        private AccessTokenRepository $tokens,
        private InjustLinkRepository $links,
        private CurrentUser $currentUser,
    ) {
    }

    public function __invoke(): InjustLinkView
    {
        $owner = $this->currentUser->get();

        return new InjustLinkView(
            $this->tokens->of($owner)?->createdAt()->format(\DATE_ATOM),
            $this->links->of($owner)?->feedAt()?->format(\DATE_ATOM),
            count($this->links->pending($owner)),
        );
    }
}
