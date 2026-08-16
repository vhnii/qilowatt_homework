<?php

declare(strict_types=1);

namespace App;

final class GitInfo
{
    public static function currentSha(string $repoRoot): ?string
    {
        $head = @file_get_contents($repoRoot . '/.git/HEAD');

        if ($head === false) {
            return null;
        }

        $head = trim($head);

        // A detached HEAD stores the SHA itself rather than a ref.
        if (!str_starts_with($head, 'ref: ')) {
            return $head;
        }

        $ref = substr($head, 5);
        $sha = @file_get_contents($repoRoot . '/.git/' . $ref);

        if ($sha !== false) {
            return trim($sha);
        }

        return self::fromPackedRefs($repoRoot, $ref);
    }

    private static function fromPackedRefs(string $repoRoot, string $ref): ?string
    {
        $packed = @file_get_contents($repoRoot . '/.git/packed-refs');

        if ($packed === false) {
            return null;
        }

        foreach (explode("\n", $packed) as $line) {
            $line = trim($line);

            if (str_ends_with($line, ' ' . $ref)) {
                return explode(' ', $line, 2)[0];
            }
        }

        return null;
    }
}
