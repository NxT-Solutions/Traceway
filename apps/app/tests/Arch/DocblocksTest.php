<?php

declare(strict_types=1);

/*
| Pest's arch rules read the names in code, not in docblocks. A docblock type
| that comes through an import is still checked, because the import is code.
| A namespaced name written out in a docblock tag is not, so none may be.
*/

test('a docblock names a class through an import, so the arch rules see it', function (): void {
    $app = dirname(__DIR__, 2).'/app';
    $written = [];

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
            if (! is_array($token) || $token[0] !== T_DOC_COMMENT) {
                continue;
            }

            preg_match_all('/(?:^|\s)@[a-z][\w-]*[ \t]+([^\n]*)/', $token[1], $tags);

            foreach ($tags[1] as $tag) {
                if (preg_match('/(?<![\w\\\\])\\\\?[A-Z]\w*(?:\\\\\w+)+/', $tag, $name) === 1) {
                    $written[] = substr($file->getPathname(), strlen($app) - 3).':'.$token[2].' '.$name[0];
                }
            }
        }
    }

    expect($written)->toBe([]);
});
