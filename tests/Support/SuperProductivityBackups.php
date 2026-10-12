<?php

declare(strict_types=1);

namespace App\Tests\Support;

final class SuperProductivityBackups
{
    /**
     * @return array<string, mixed>
     */
    public static function sample(): array
    {
        return [
            'task' => [
                'ids' => ['vet', 'mix', 'mix-vocals', 'tax', 'old', 'loose', 'call'],
                'entities' => [
                    'vet' => ['id' => 'vet', 'title' => 'Book the vet', 'isDone' => false, 'projectId' => 'INBOX_PROJECT', 'tagIds' => ['TODAY'], 'subTaskIds' => [], 'created' => 1791187200000],
                    'mix' => ['id' => 'mix', 'title' => 'Mix the single', 'notes' => 'Bus compression', 'isDone' => false, 'projectId' => 'music', 'tagIds' => ['EM_URGENT', 'EM_IMPORTANT'], 'subTaskIds' => ['mix-vocals'], 'dueDay' => '2026-10-08', 'deadlineDay' => '2026-10-10'],
                    'mix-vocals' => ['id' => 'mix-vocals', 'title' => 'Vocals', 'isDone' => false, 'parentId' => 'mix', 'tagIds' => [], 'subTaskIds' => []],
                    'tax' => ['id' => 'tax', 'title' => 'File taxes', 'isDone' => false, 'projectId' => 'work', 'tagIds' => ['EM_IMPORTANT'], 'subTaskIds' => [], 'dueWithTime' => 1791329400000],
                    'old' => ['id' => 'old', 'title' => 'Done already', 'isDone' => true, 'projectId' => 'work', 'tagIds' => [], 'subTaskIds' => []],
                    'loose' => ['id' => 'loose', 'title' => 'Water plants', 'isDone' => false, 'projectId' => 'work', 'tagIds' => [], 'subTaskIds' => [], 'dueDay' => 'someday'],
                    'call' => ['id' => 'call', 'title' => 'Call the bank', 'isDone' => false, 'projectId' => 'work', 'tagIds' => ['EM_IMPORTANT'], 'subTaskIds' => []],
                ],
            ],
            'project' => [
                'ids' => ['INBOX_PROJECT', 'music', 'work'],
                'entities' => [
                    'INBOX_PROJECT' => ['id' => 'INBOX_PROJECT', 'title' => 'Inbox'],
                    'music' => ['id' => 'music', 'title' => 'Musique'],
                    'work' => ['id' => 'work', 'title' => 'Travail'],
                ],
            ],
            'boards' => [
                'boardCfgs' => [
                    ['id' => 'EISENHOWER_MATRIX', 'panels' => [
                        ['id' => 'URGENT_AND_IMPORTANT', 'taskIds' => ['mix']],
                        ['id' => 'NOT_URGENT_AND_IMPORTANT', 'taskIds' => ['call', 'tax']],
                    ]],
                ],
            ],
        ];
    }
}
