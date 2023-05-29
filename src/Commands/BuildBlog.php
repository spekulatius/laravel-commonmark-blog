<?php

namespace Spekulatius\LaravelCommonmarkBlog\Commands;

use Carbon\Carbon;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use romanzipp\Seo\Conductors\Types\ManifestAsset;
use romanzipp\Seo\Structs\Link;
use romanzipp\Seo\Structs\Meta;
use romanzipp\Seo\Structs\Meta\Article;
use romanzipp\Seo\Structs\Meta\OpenGraph;
use romanzipp\Seo\Structs\Meta\Twitter;
use romanzipp\Seo\Structs\Script;
use romanzipp\Seo\Structs\Struct;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class BuildBlog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:build {source_path?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Builds the blog from the source files.';

    /**
     * @var Environment
     */
    protected $environment = null;

    /**
     * @var CommonMarkConverter
     */
    protected $converter = null;

    /**
     * @var string
     */
    protected $sourcePath = null;

    public function __construct()
    {
        parent::__construct();

        // Prepare the environment with the custom extensions.
        $this->environment = Environment::createCommonMarkEnvironment();
        foreach (config('blog.extensions') as $extension) {
            $this->environment->addExtension($extension);
        }

        // Create the converter.
        $this->converter = new CommonMarkConverter(
            config('blog.converter_config', []),
            $this->environment,
        );
    }

    /**
     * Execute the console command.
     *
     * @return int
     * @throws \League\CommonMark\Exception\CommonMarkException
     */
    public function handle(): int
    {
        // Prep
        $this->bootstrap();

        // Note the start
        $this->info('Building from ' . $this->sourcePath);
        $this->newLine();

        // Release the embargo files
        $this->releaseFiles($this->sourcePath);

        // Identify and convert the files
        $generatedArticles = $this->convertFiles($this->sourcePath);

        // Push the generated articles into the cache, if configured.
        $this->populateCache($generatedArticles);

        // Note the end
        $this->newLine();
        $this->info('Build completed.');

        // Done.
        return 0;
    }

    /**
     * Prepares the environment
     */
    protected function bootstrap(): void
    {
        // Get the source path: either argument, configuration value or nothing.
        $sourcePath = $this->argument('source_path') ?? config('blog.source_path');
        if (is_null($sourcePath)) {
            $this->error('No source path defined.');
            die;
        }
        $this->sourcePath = $sourcePath;

        // Checks
        if (is_null(config('blog.article_base_template'))) {
            $this->error('No article base template defined.');
            die;
        }
        if (is_null(config('blog.list_base_template'))) {
            $this->error('No list base template defined.');
            die;
        }
        if (is_null(config('blog.list_per_page'))) {
            $this->error('No list per_page count defined.');
            die;
        }
    }

    /**
     * Finds embargo files and releases them (if due).
     *
     * @param string $sourcePath
     * @return void
     */
    protected function releaseFiles(string $sourcePath): void
    {
        // 1. Find the files matching the extension ".[0-9].emb.md"
        // 2. Iterate through the files
        // 3. Release them, if due.
        $files = $this->findFiles($sourcePath, '*.emb.md');
        $releaseFiles = [];
        foreach ($files as $file) {
            // Load the file to check the frontmatter, if it's ready for release.
            $article = YamlFrontMatter::parse(file_get_contents($file->getRealPath()));

            // Check if the file is ready for release.
            if ((new Carbon($article->matter()['modified']))->isPast()) {
                $releaseFiles[] = $file;
            }
        }

        // Iterate through the files in reverse (1 before 2) and move them
        $this->info(count($releaseFiles) . ' files to release');
        foreach (array_reverse($releaseFiles) as $file) {
            // Log the file
            $this->info('- ' . $file->getRelativePathname());

            // Move the file over to replace the original file.
            rename(
                $file->getRealPath(),
                preg_replace('/\.\d+\.emb\.md/', '.md', $file->getRealPath())
            );
        }
    }

    /**
     * Finds and converts all files to process.
     *
     * @param string $sourcePath
     * @return array
     * @throws \League\CommonMark\Exception\CommonMarkException
     */
    protected function convertFiles(string $sourcePath): array
    {
        // Mirror the complete structure over to create the folder structure as needed.
        (new Filesystem)->mirror($sourcePath, public_path());

        // Identify the files to process (without `.[0-9].emb.md` files)
        $sourceFiles = $this->findFiles($sourcePath, '*.md')
            ->filter(function ($file) {
                return strpos($file->getRelativePathname(), '.emb.md') === false;
            });

        // Sort files by type (article or list)
        $files = ['articles' => [], 'lists' => []];
        foreach ($sourceFiles as $file) {
            $files[
                Str::endsWith($file->getRelativePathname(), 'index.md') ? 'lists' : 'articles'
            ][] = $file;
        }

        // Convert the articles
        $generatedArticles = [];
        $this->newLine();
        $this->info(count($files['articles']) . ' articles considered for conversion');
        foreach ($files['articles'] as $articleFile) {
            // Convert the file and store it directly in the public folder.
            if ($this->shouldConvertArticle($articleFile)) {
                $generatedArticles[] = $this->convertArticle($articleFile);
            }

            // Delete the copied over instance of the file
            unlink(public_path($articleFile->getRelativePathname()));
        }

        // Convert the lists
        $this->newLine();
        $this->info(count($files['lists']) . ' lists to convert');
        foreach ($files['lists'] as $listFile) {
            // Convert the file and store it directly in the public folder.
            $this->convertList($listFile, $generatedArticles);

            // Delete the copied over instance of the file
            unlink(public_path($listFile->getRelativePathname()));
        }

        // Return the list of generated articles for later caching.
        return $generatedArticles;
    }

    /**
     * Pushes an array (including frontmatter) in the cache for other usage.
     *
     * @param array $generatedArticles
     * @return void
     */
    protected function populateCache(array $generatedArticles): void
    {
        // Store the generated articles in the cache for other usage.
        if (config('blog.cache.key', null)) {
            $this->newLine();
            $this->info('Stored generated articles in cache');

            Cache::put(
                config('blog.cache.key'),
                $generatedArticles,
                config('blog.cache.expiry', 86400),
            );
        }
    }

    /**
     * Finds all files to process.
     *
     * Overwrite this method to access other sources than the filesystem.
     *
     * @param string $path
     * @param string $extension
     * @return Finder
     */
    protected function findFiles(string $path, string $extension): Finder
    {
        // Find all files which meet the scope requirements
        return (new Finder)->files()->name($extension)->in($path);
    }

    /**
     * Checks if a given article file should be converted.
     *
     * @param SplFileInfo $file
     * @return bool
     * @throws \League\CommonMark\Exception\CommonMarkException
     */
    protected function shouldConvertArticle(SplFileInfo $file): bool
    {
        $data = $this->prepareData($file->getRealPath());

        // Check if this article should be converted or is still unpublished.
        return
            isset($data['published']) &&
            (new Carbon($data['published']))->isPast();
    }

    /**
     * Convert a given article source file into ready-to-serve HTML document.
     *
     * @param SplFileInfo $file
     * @return array
     * @throws \League\CommonMark\Exception\CommonMarkException
     */
    protected function convertArticle(SplFileInfo $file): array
    {
        $this->info('- ' . $file->getRelativePathname());

        // Prepares the data
        $data = $this->prepareData($file->getRealPath());

        // Define the target directory and create it (optionally).
        $targetURL = preg_replace('/\.md$/', '/', $file->getRelativePathname());
        $targetDirectory = public_path($targetURL);
        if (!file_exists($targetDirectory)) {
            mkdir($targetDirectory);
        }

        // Render the file using the blade file and write it as index.htm into the directory.
        isset($data['locale']) ? app()->setLocale($data['locale']) : '';
        file_put_contents(
            $targetDirectory . '/index.htm',
            view(config('blog.article_base_template'), $data)->render()
        );

        // Return the generated header information with some additional details for internal handling.
        return array_merge([
            'absolute_url' => $this->makeURLAbsolute($targetURL),
            'generated_url' => $targetURL,
        ], $data);
    }

    /**
     * Prepares the data for a file conversion.
     * This allows you to use the data separately.
     *
     * @param string $filename
     * @return array
     * @throws \League\CommonMark\Exception\CommonMarkException
     */
    public function prepareData(string $filename): array
    {
        // Split frontmatter and the commonmark parts.
        $article = YamlFrontMatter::parse(file_get_contents($filename));
        $description = '';
        if ($article->matter('description')) {
            $description = $this->converter->convert($article->matter('description'));
        }

        // Prepare the information to hand to the view - the frontmatter and headers+content.
        return array_merge(
            array_merge(config('blog.defaults', []), $article->matter()),
            [
                'header' => $this->prepareLaravelSEOHeaders($article->matter()),
                'content' => $this->converter->convert($article->body()),
                'description' => $description,
            ]
        );
    }

    /**
     * Convert a given source list file into a set of ready-to-serve HTML documents.
     *
     * @param SplFileInfo $file
     * @param array $generatedArticles
     * @throws \League\CommonMark\Exception\CommonMarkException
     */
    protected function convertList(SplFileInfo $file, array $generatedArticles)
    {
        // Split frontmatter and the commonmark parts.
        $page = YamlFrontMatter::parse(file_get_contents($file->getRealPath()));

        // Define the target directory and create it (optionally).
        $targetURL = preg_replace('/\/index\.md$/', '/', $file->getRelativePathname());
        $this->info('- ' . $targetURL);

        // Find all related pages, sort them by date and chunk them up into pages.
        $chunkedArticles = collect($generatedArticles)
            // Only use the pages below this URL
            ->reject(function($item) use ($targetURL) {
                return !Str::startsWith($item['generated_url'], $targetURL);
            })

            // Sort by date by default
            ->sortByDesc('modified')

            // Chunk the results into pages
            ->chunk(config('blog.list_per_page', 12));

        // Process each chunk into a page
        $totalPages = $chunkedArticles->count();
        $chunkedArticles->each(function($pageArticles, $index) use ($page, $targetURL, $totalPages) {
            $this->info('  creating page ' . ($index + 1) . ' of ' . $totalPages);

            // Generate a page for each chunk.
            $finalTargetURL = $targetURL . (($index === 0) ? '' : ($index + 1) . '/');
            $targetDirectory = public_path($finalTargetURL);
            if (!file_exists($targetDirectory)) {
                mkdir($targetDirectory);
            }

            // Remove hreflang for pages > 1
            $frontmatter = $page->matter();
            if ($index > 0 && isset($frontmatter['hreflang'])) {
                unset($frontmatter['hreflang']);
            }

            // Prepare the information to hand to the view - the frontmatter and headers+content.
            $data = array_merge(
                array_merge(config('blog.defaults', []), $frontmatter),
                [
                    // Header and content.
                    'header' => $this->prepareLaravelSEOHeaders(array_merge(
                        $frontmatter,
                        ['canonical' => $this->makeURLAbsolute($finalTargetURL)]
                    )),
                    'content' => $this->converter->convert($page->body()),

                    // Articles and pagination information
                    'base_url' => $this->makeURLAbsolute($targetURL),
                    'articles' => $pageArticles,
                    'total_pages' => $totalPages,
                    'current_page' => $index + 1,
                ]
            );

            // Render the file and write it.
            isset($data['locale']) ? app()->setLocale($data['locale']) : '';
            file_put_contents(
                $targetDirectory . '/index.htm',
                view(config('blog.list_base_template'), $data)->render()
            );

            // Copy the index.htm to 1/index.htm, if it's the first page.
            // Saves lots of cases in the pagination.
            if ($index === 0) {
                if (!file_exists($targetDirectory . '/1')) {
                    mkdir($targetDirectory . '/1');
                }
                copy($targetDirectory . '/index.htm', $targetDirectory . '/1/index.htm');
            }
        });
    }

    /**
     * Filters and prepares the headers using Laravel SEO
     *
     * @see https://github.com/romanzipp/Laravel-SEO
     *
     * @param array $frontmatter
     * @return string
     */
    protected function prepareLaravelSEOHeaders(array $frontmatter): string
    {
        // Merge the defaults in.
        $frontmatter = array_merge(config('blog.defaults', []), $frontmatter);

        // Include the mix assets, if activated.
        $this->includeMixAssets();

        // Fill in some cases - e.g. keywords, dates, etc.
        $this->fillIn($frontmatter);

        // Add all custom structs from the list in.
        seo()->addMany(array_values(array_filter($frontmatter, function ($entry) {
            return $entry instanceof Struct;
        })));

        // Filter any methods which aren't allowed or misconfigured.
        seo()->addFromArray(array_filter($frontmatter, function($value, $key) {
            return (
                    is_string($value) &&
                    in_array($key, [
                        'charset',
                        'viewport',
                        'title',
                        'description',
                        'image',
                        'canonical',
                    ])
                ||
                    is_array($value) &&
                    in_array($key, [
                        'og',
                        'twitter',
                        'meta',
                    ])
            );
        }, ARRAY_FILTER_USE_BOTH));

        // Render the header
        $headerTags = seo()->render();

        // Reset any previously set structs after the view is rendered.
        seo()->clearStructs();

        // Return the combined result, rendered.
        return $headerTags;
    }

    /**
     * Helper to include the mix assets.
     */
    protected function includeMixAssets(): void
    {
        // Add the preloading for Laravel elements in.
        if (config('blog.mix.active')) {
            // Add the prefetching in.
            $manifestAssets = seo()
                ->mix()
                ->map(static function(ManifestAsset $asset): ?ManifestAsset {
                    $asset->url = config('app.url') . $asset->url;

                    return $asset;
                })
                ->load(
                    !is_null(config('blog.mix.manifest_path')) ?
                        config('blog.mix.manifest_path') : public_path('mix-manifest.json')
                )
                ->getAssets();

            // Add the actual assets in.
            foreach ($manifestAssets as $asset) {
                if ($asset->as === 'style') {
                    seo()->add(Link::make()->rel('stylesheet')->href($asset->url));
                }
                if ($asset->as === 'script') {
                    seo()->add(
                        Script::make()
                            ->src($asset->url)
                            ->attr('async')
                    );
                }
            }
        }
    }

    /**
     * Helper to fill in some commonly expected functionality such as image, canonical, etc.
     *
     * @param array $frontmatter
     */
    protected function fillIn(array $frontmatter): void
    {
        // Keywords
        if (isset($frontmatter['keywords'])) {
            // Allow for both array and string to be passed.
            // Arrays will be converted to strings here.
            $keywords = is_array($frontmatter['keywords']) ?
                join(', ', $frontmatter['keywords']) : $frontmatter['keywords'];

            seo()->add(Meta::make()->name('keywords')->content($keywords));
        }

        // Published
        if (isset($frontmatter['published'])) {
            $date = (new Carbon($frontmatter['published']));
            seo()->addMany([
                Article::make()->property('published_time')->content($date->toAtomString()),
            ]);
        }

        // Ensure the canonical becomes "twitter:url" and "og:url"
        if (isset($frontmatter['canonical'])) {
            seo()->addMany([
                OpenGraph::make()
                    ->property('url')
                    ->content($this->makeURLAbsolute($frontmatter['canonical'])),
                Twitter::make()
                    ->name('url')
                    ->content($this->makeURLAbsolute($frontmatter['canonical'])),
            ]);
        }

        // Modified
        if (isset($frontmatter['modified'])) {
            // Prep the date string
            $date = (new Carbon($frontmatter['modified']))->toAtomString();

            // Add in
            seo()->addMany([
                Article::make()->property('modified_time')->content($date),
                OpenGraph::make()->property('updated_time')->content($date),
            ]);
        }

        // hreflang: alternative languages
        if (isset($frontmatter['hreflang'])) {
            // Prepare the "x-default" entries.
            $hreflangs = [];

            // Other hreflang versions
            seo()->addMany(collect($frontmatter['hreflang'])->map(function ($uri, $lang) use (&$hreflangs) {
                $hreflangs[$lang] = $this->makeURLAbsolute($uri);

                return Link::make()
                    ->rel('alternate')
                    ->attr('hreflang', $lang)
                    ->href($this->makeURLAbsolute($uri));
            })->toArray());

            // Self-reference hreflang
            if (isset($frontmatter['locale']) && isset($frontmatter['canonical'])) {
                $hreflangs[$frontmatter['locale']] = $this->makeURLAbsolute($frontmatter['canonical']);

                seo()->add(Link::make()
                    ->rel('alternate')
                    ->attr('hreflang', $frontmatter['locale'])
                    ->href($this->makeURLAbsolute($frontmatter['canonical']))
                );
            }

            // Set the x-default entry, if it exists.
            $xDefault = config('blog.hreflang_default') ?? null;
            if ($xDefault && isset($hreflangs[$xDefault])) {
                seo()->add(Link::make()
                    ->rel('alternate')
                    ->attr('hreflang', 'x-default')
                    ->href($hreflangs[$xDefault])
                );
            }
        }
    }

    /**
     * Turns a URI into an absolute URL
     *
     * @param string $uri
     * @return string
     */
    protected function makeURLAbsolute(string $uri): string
    {
        return Str::startsWith($uri, 'http') ? $uri : (
            config('app.url') .
            (Str::endsWith(config('app.url'), '/') ? $uri : Str::start($uri, '/'))
        );
    }
}
