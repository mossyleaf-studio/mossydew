<?php

declare(strict_types=1);

namespace App\Application\Planning\ListWidgetTasks;

final readonly class WidgetView
{
    /**
     * @param array{background: string, accent: string, dark: bool}|null $theme
     * @param list<WidgetTaskView>                                       $tasks
     */
    public function __construct(
        public string $date,
        public ?array $theme,
        public array $tasks,
    ) {
    }
}
