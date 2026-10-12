<?php

declare(strict_types=1);

namespace App\Application\Planning\ImportSuperProductivity;

use App\Domain\Planning\Exception\UnreadableBackup;

final readonly class SuperProductivityBackup
{
    private const string INBOX = 'INBOX_PROJECT';
    private const string URGENT = 'EM_URGENT';
    private const string IMPORTANT = 'EM_IMPORTANT';
    private const string TODAY = 'TODAY';
    private const string MATRIX = 'EISENHOWER_MATRIX';

    /**
     * @param list<SuperProductivityTask> $tasks
     */
    private function __construct(public array $tasks)
    {
    }

    /**
     * @param array<mixed> $backup
     */
    public static function read(array $backup): self
    {
        $data = is_array($backup['data'] ?? null) ? $backup['data'] : $backup;
        $entities = self::map($data['task'] ?? null)['entities'] ?? null;
        if (!is_array($entities)) {
            throw new UnreadableBackup();
        }

        $projects = [];
        foreach (self::map(self::map($data['project'] ?? null)['entities'] ?? null) as $id => $project) {
            $title = self::text(self::map($project)['title'] ?? null);
            if (null !== $title) {
                $projects[(string) $id] = $title;
            }
        }

        $order = self::matrixOrder($data);
        $tasks = [];
        foreach ($entities as $id => $entity) {
            $task = self::map($entity);
            $title = self::text($task['title'] ?? null);
            if (null === $title || true === ($task['isDone'] ?? false)) {
                continue;
            }
            $parentId = self::text($task['parentId'] ?? null);
            $parent = null === $parentId ? [] : self::map($entities[$parentId] ?? null);
            $projectId = self::text($task['projectId'] ?? null) ?? self::text($parent['projectId'] ?? null);
            $tags = array_filter(self::list($task['tagIds'] ?? null), 'is_string');

            $tasks[] = new SuperProductivityTask(
                (string) $id,
                $title,
                self::text($task['notes'] ?? null),
                null === $projectId || self::INBOX === $projectId ? null : ($projects[$projectId] ?? null),
                $parentId,
                in_array(self::URGENT, $tags, true),
                in_array(self::IMPORTANT, $tags, true),
                $order[(string) $id] ?? PHP_INT_MAX,
                self::day($task['dueDay'] ?? null),
                self::instant($task['dueWithTime'] ?? null),
                in_array(self::TODAY, $tags, true),
                self::day($task['deadlineDay'] ?? null),
                self::instant($task['deadlineWithTime'] ?? null),
                self::instant($task['created'] ?? null),
            );
        }

        return new self($tasks);
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, int>
     */
    private static function matrixOrder(array $data): array
    {
        $order = [];
        foreach (self::list(self::map($data['boards'] ?? null)['boardCfgs'] ?? null) as $board) {
            $board = self::map($board);
            if (self::MATRIX !== ($board['id'] ?? null)) {
                continue;
            }
            foreach (self::list($board['panels'] ?? null) as $panel) {
                foreach (array_values(array_filter(self::list(self::map($panel)['taskIds'] ?? null), 'is_string')) as $position => $taskId) {
                    $order[$taskId] ??= $position;
                }
            }
        }

        return $order;
    }

    /**
     * @return array<mixed>
     */
    private static function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && '' !== trim($value) ? $value : null;
    }

    private static function day(mixed $value): ?string
    {
        return is_string($value) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : null;
    }

    private static function instant(mixed $value): ?\DateTimeImmutable
    {
        return is_int($value) || is_float($value) ? new \DateTimeImmutable('@'.intdiv((int) $value, 1000)) : null;
    }
}
