<?php

namespace App\Console\Commands;

use App\Models\HelpArticle;
use App\Models\HelpArticleRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

class HelpSync extends Command
{
    protected $signature = 'help:sync';

    protected $description = 'Sync Markdown help files from resources/docs/ into the database.';

    public function handle(): int
    {
        $docsPath = resource_path('docs');

        if (! is_dir($docsPath)) {
            $this->error("Docs directory not found: {$docsPath}");
            return self::FAILURE;
        }

        $files = glob("{$docsPath}/*.md");

        if (empty($files)) {
            $this->warn('No Markdown files found in resources/docs/');
            return self::SUCCESS;
        }

        $synced = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $slug = pathinfo($file, PATHINFO_FILENAME);
            $raw = file_get_contents($file);

            [$frontmatter, $content] = $this->parseFrontmatter($raw);

            if ($frontmatter === null) {
                $this->warn("  ✗ {$slug} — no frontmatter block found");
                $skipped++;
                continue;
            }

            $missing = array_filter(['title', 'description', 'routes'], fn ($k) => empty($frontmatter[$k]));

            if ($missing) {
                $this->warn("  ✗ {$slug} — missing required frontmatter: " . implode(', ', $missing));
                $skipped++;
                continue;
            }

            $routes = (array) $frontmatter['routes'];

            DB::transaction(function () use ($slug, $frontmatter, $content, $routes) {
                $article = HelpArticle::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'title'       => $frontmatter['title'],
                        'description' => $frontmatter['description'],
                        'content'     => trim($content),
                        'tags'        => isset($frontmatter['tags']) ? (array) $frontmatter['tags'] : [],
                        'app_version' => $frontmatter['version'] ?? null,
                        'last_updated' => isset($frontmatter['updated']) ? $frontmatter['updated'] : null,
                    ]
                );

                // Re-sync routes: delete and re-insert.
                HelpArticleRoute::where('help_article_id', $article->id)->delete();

                foreach ($routes as $routeName) {
                    HelpArticleRoute::create([
                        'help_article_id' => $article->id,
                        'route_name'      => $routeName,
                    ]);
                }
            });

            $count = count($routes);
            $this->line("  ✓ {$slug} ({$count} " . ($count === 1 ? 'route' : 'routes') . ')');
            $synced++;
        }

        // Remove DB articles whose source file no longer exists.
        $activeSlugs = array_map(fn ($f) => pathinfo($f, PATHINFO_FILENAME), $files);
        $removed = HelpArticle::whereNotIn('slug', $activeSlugs)->delete();

        $this->newLine();
        $this->info("Synced: {$synced}  Skipped: {$skipped}  Removed: {$removed}");

        return self::SUCCESS;
    }

    /**
     * Split a Markdown file into [frontmatter array|null, body string].
     */
    private function parseFrontmatter(string $raw): array
    {
        if (! str_starts_with(ltrim($raw), '---')) {
            return [null, $raw];
        }

        $pattern = '/^---\s*\n(.*?)\n---\s*\n(.*)/s';

        if (! preg_match($pattern, ltrim($raw), $matches)) {
            return [null, $raw];
        }

        try {
            $frontmatter = Yaml::parse($matches[1]);
        } catch (\Exception) {
            return [null, $raw];
        }

        return [$frontmatter, $matches[2]];
    }
}
