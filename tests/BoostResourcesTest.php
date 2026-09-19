<?php

use Illuminate\Support\Facades\Blade;

/**
 * Guideline files are rendered by Laravel Boost with a Laravel\Boost\Install\GuidelineAssist
 * instance available as $assist. Boost is not a dependency of this package, so the render
 * tests use a minimal stand-in that mirrors the helpers used in the guidelines.
 */
function laraqlBoostAssist(): object
{
    return new class
    {
        public function artisanCommand(string $command): string
        {
            return "php artisan {$command}";
        }

        public function hasSkillsEnabled(): bool
        {
            return true;
        }

        public function hasMcpEnabled(): bool
        {
            return true;
        }
    };
}

function laraqlBoostPath(string $path = ''): string
{
    return __DIR__.'/../resources/boost/'.ltrim($path, '/');
}

/**
 * @return array<int, string>
 */
function laraqlBoostSkillDirectories(): array
{
    $directories = glob(laraqlBoostPath('skills').'/*', GLOB_ONLYDIR);

    return $directories === false ? [] : $directories;
}

function laraqlBoostSkillFrontmatter(string $skillPath): string
{
    // Normalize line endings first: on Windows (core.autocrlf) the files are checked
    // out with CRLF, and `.+` would capture the trailing carriage return.
    $contents = str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($skillPath.'/SKILL.md'));

    expect(preg_match('/^---\R(.*?)\R---\R/s', $contents, $matches))->toBe(1);

    return $matches[1];
}

it('ships boost guidelines that render as blade', function () {
    $path = laraqlBoostPath('guidelines/core.blade.php');

    expect($path)->toBeFile();

    $rendered = Blade::render((string) file_get_contents($path), ['assist' => laraqlBoostAssist()]);

    expect($rendered)
        ->toContain('# LaraQL')
        ->toContain('nodesol/laraql')
        ->toContain('php artisan lighthouse:print-schema')
        ->toContain('@paginate(defaultCount: 10)')
        ->toContain('@scoutFilters')
        ->toContain('@hasMany')
        ->toContain('@rules(apply: ["required", "max:200"])')
        ->not->toContain('$assist')
        ->not->toContain('@verbatim')
        ->not->toContain('@endverbatim');
});

it('ships boost skills with valid frontmatter', function () {
    $skillDirectories = laraqlBoostSkillDirectories();

    expect($skillDirectories)->not->toBeEmpty();

    foreach ($skillDirectories as $skillPath) {
        $directory = basename($skillPath);
        $frontmatter = laraqlBoostSkillFrontmatter($skillPath);

        preg_match('/^name:\s*(.+)$/m', $frontmatter, $name);
        preg_match('/^description:\s*(.+)$/m', $frontmatter, $description);

        expect(trim($name[1] ?? ''))->toBe($directory)
            ->and($directory)->toMatch('/^[a-z0-9]+(-[a-z0-9]+)*$/')
            ->and(trim($description[1] ?? ''))->not->toBeEmpty()
            ->and(strlen(trim($description[1] ?? '')))->toBeLessThanOrEqual(1024);
    }
});

it('links every boost skill support file from its SKILL.md', function () {
    foreach (laraqlBoostSkillDirectories() as $skillPath) {
        $skill = (string) file_get_contents($skillPath.'/SKILL.md');

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($skillPath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            // Strip the skill directory and normalize the separators so the relative
            // path can be matched against the markdown on any platform.
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($skillPath) + 1));

            if ($relative === 'SKILL.md') {
                continue;
            }

            expect($skill)->toContain($relative);
        }
    }
});

it('mentions every shipped boost skill in the guidelines', function () {
    $guidelines = Blade::render(
        (string) file_get_contents(laraqlBoostPath('guidelines/core.blade.php')),
        ['assist' => laraqlBoostAssist()]
    );

    foreach (laraqlBoostSkillDirectories() as $skillPath) {
        expect($guidelines)->toContain(basename($skillPath));
    }
});
