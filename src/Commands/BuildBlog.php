<?php

namespace Spekulatius\LaravelCommonmarkBlog\Commands;

use Carbon\Carbon;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use romanzipp\Seo\Conductors\Types\ManifestAsset;
use romanzipp\Seo\Structs\Link;
use romanzipp\Seo\Structs\Meta;
use romanzipp\Seo\Structs\Meta\Article;
use romanzipp\Seo\Structs\Meta\Canonical;
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

        // Prepare the enviroment with the custom extensions.
        $this->environment = Environment::createCommonMarkEnvironment();
        foreach (config('blog.extensions') as $extension) {
            $this->environment->addExtension($extension);
        }

        // Create the converter.
        $this->converter = new CommonMarkConverter(
            config('blog.config'),
            $this->environment,
        );
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Prep
        $this->bootstrap();

        // Identify and convert the files
        $this->convertFiles();

        // Done.
        return 0;
    }

    /**
     * Prepares the environment
     */
    protected function bootstrap()
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
     * Finds and converts all files to process.
     *
     * @return void
     */
    protected function convertFiles()
    {
        $this->info('Building from ' . $this->sourcePath);

        // Mirror the complete structure over to create the folder structure as needed.
        (new Filesystem)->mirror($this->sourcePath, public_path());

        // Identify the files to process and sort them.
        $files = ['articles' => [], 'lists' => []];
        foreach ($this->findFiles($this->sourcePath) as $file) {
            // Sort the fiels into lists and articles to process them in order below.
            $files[
                Str::endsWith($file->getRelativePathname(), 'index.md') ? 'lists' : 'articles'
            ][] = $file;
        }

        // Convert the articles
        $generatedArticles = [];
        foreach ($files['articles'] as $articleFile) {
            // Convert the file and store it directly in the public folder.
            if ($this->shouldConvertArticle($articleFile)) {
                $generatedArticles[] = $this->convertArticle($articleFile);
            }

            // Delete the copied over instance of the file
            unlink(public_path($articleFile->getRelativePathname()));
        }

        // Convert the lists
        foreach ($files['lists'] as $listFile) {
            // Convert the file and store it directly in the public folder.
            $this->convertList($listFile, $generatedArticles);

            // Delete the copied over instance of the file
            unlink(public_path($listFile->getRelativePathname()));
        }

        // Store the generated articles in the cache for other usage.
        if (config('blog.cache.key', null)) {
            $this->info('Stored generated articles in cache');

            Cache::put(
                config('blog.cache.key'),
                $generatedArticles,
                config('blog.cache.expiry', 86400),
            );
        }

        $this->info('Build completed.');
    }

    /**
     * Finds all files to process.
     *
     * Overwrite this method to access other sources than the filesystem.
     *
     * @param string $path
     * @return array
     */
    protected function findFiles(string $path)
    {
        // Find all files which meet the scope requirements
        return (new Finder)->files()->name('*.md')->in($path);
    }

    /**
     * Checks if a given article file should be converted.
     *
     * @param SplFileInfo $file
     * @return bool
     */
    protected function shouldConvertArticle(SplFileInfo $file)
    {
        $data = $this->prepareData($file->getRealPath());

        // Check if this article should be converted or is still unpublished.
        return
            isset($data['published']) &&
            Carbon::createFromFormat(
                config('blog.date_format', 'Y-m-d H:i:s'),
                $data['published']
            )->isPast();
    }

    /**
     * Convert a given article source file into ready-to-serve HTML document.
     *
     * @param SplFileInfo $file
     * @return array
     */
    protected function convertArticle(SplFileInfo $file)
    {
        $this->info('Converting Article ' . $file->getRelativePathname());

        // Prepares the data
        $data = $this->prepareData($file->getRealPath());

        // Define the target directory and create it (optionally).
        $targetURL = preg_replace('/\.md$/', '/', $file->getRelativePathname());
        $targetDirectory = public_path($targetURL);
        if (!file_exists($targetDirectory)) {
            mkdir($targetDirectory);
        }

        // Render the file using the blade file and write it as index.htm into the directory.
        file_put_contents(
            $targetDirectory . '/index.htm',
            view(config('blog.article_base_template'), $data)->render()
        );

        // Return the generated header information with some additional details for internal handling.
        return array_merge([
            'absolute_url' => Str::finish(env('APP_URL'), '/') . $targetURL,
            'generated_url' => $targetURL,
        ], $data);
    }

    /**
     * Prepares the data for a file conversion.
     * This allows you to use the data separately.
     *
     * @param string $filename
     * @return array
     */
    public function prepareData(string $filename)
    {
        // Split frontmatter and the commonmark parts.
        $article = YamlFrontMatter::parse(file_get_contents($filename));

        // Prepare the information to hand to the view - the frontmatter and headers+content.
        return array_merge(
            array_merge(config('blog.defaults', []), $article->matter()),
            [
                'header' => $this->prepareLaravelSEOHeaders($article->matter()),
                'content' => $this->converter->convertToHtml($article->body()),
            ]
        );
    }

    /**
     * Convert a given source list file into a set of ready-to-serve HTML documents.
     *
     * @param SplFileInfo $file
     * @param array $generatedArticles
     */
    protected function convertList(SplFileInfo $file, array $generatedArticles)
    {
        $this->info('Preparing List ' . $file->getRelativePathname());

        // Split frontmatter and the commonmark parts.
        $page = YamlFrontMatter::parse(file_get_contents($file->getRealPath()));

        // Define the target directory and create it (optionally).
        $targetURL = preg_replace('/\/index\.md$/', '/', $file->getRelativePathname());

        // Find all related pages and sort them by date
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
            $this->info('Creating page ' . ($index + 1) . ' of ' . $totalPages);

            // Generate a page for each chunk.
            $finalTargetURL = $targetURL . (($index === 0) ? '' : ($index + 1) . '/');
            $targetDirectory = public_path($finalTargetURL);
            if (!file_exists($targetDirectory)) {
                mkdir($targetDirectory);
            }

            // Prepare the information to hand to the view - the frontmatter and headers+content.
            $data = array_merge(
                array_merge(config('blog.defaults', []), $page->matter()),
                [
                    // Header and content.
                    'header' => $this->prepareLaravelSEOHeaders(array_merge(
                        $page->matter(),
                        ['canonical' => Str::finish(env('APP_URL'), '/') . $finalTargetURL]
                    )),
                    'content' => $this->converter->convertToHtml($page->body()),

                    // Articles and pagination information
                    'base_url' => Str::finish(env('APP_URL'), '/') . $targetURL,
                    'articles' => $pageArticles,
                    'total_pages' => $totalPages,
                    'current_page' => $index + 1,
                ]
            );

            // Render the file and write it.
            file_put_contents(
                $targetDirectory . '/index.htm',
                view(config('blog.list_base_template'), $data)->render()
            );

            // Copy the index.htm to 1/index.htm, if it's the first page. Saves lots of cases in the pagination.
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
    protected function prepareLaravelSEOHeaders(array $frontmatter)
    {
        // Merge the defaults in.
        $frontmatter = array_merge(config('blog.defaults', []), $frontmatter);

        // Include the mix assets, if actived.
        $this->includeMixAssets();

        // Fill in some cases - e.g. keywords, dates, etc.
        $this->fillIn($frontmatter);

        // Add all custom structs from the list in.
        seo()->addMany(array_values(array_filter($frontmatter, function ($entry) {
            return $entry instanceof Struct;
        })));

        // Filter any methods which aren't allowed or misconfigured.
        seo()->addFromArray(array_filter($frontmatter, function($value, $key) {
            return is_string($value) && (
                in_array($key, [
                    'charset',
                    'viewport',
                    'title',
                    'description',
                    'image',
                    'canonical',
                ]) || in_array($key, [
                    'og',
                    'twitter',
                    'meta',
                ]) && is_array($value)
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
    protected function includeMixAssets()
    {
        // Add the preloading for Laravel elements in.
        if (config('blog.mix.active')) {
            // Add the prefetching in.
            $manifestAssets = seo()
                ->mix()
                ->map(static function(ManifestAsset $asset): ?ManifestAsset {
                    $asset->url = env('APP_URL') . $asset->url;

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
                    seo()->add(Script::make()->src($asset->url));
                }
            }
        }
    }

    /**
     * Helper to fill in some commonly expected functionality such as image, canonical, etc.
     *
     * @param array $frontmatter
     */
    protected function fillIn(array $frontmatter)
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
            seo()->addMany([
                Article::make()->property('published_time')->content(
                    Carbon::createFromFormat(
                        config('blog.date_format', 'Y-m-d H:i:s'),
                        $frontmatter['published']
                    )->toAtomString()
                ),
            ]);
        }

        // Ensure the canoncial becomes "twitter:url" and "og:url"
        if (isset($frontmatter['canonical'])) {
            seo()->addMany([
                OpenGraph::make()->property('url')
                    ->content($frontmatter['canonical']),
                Twitter::make()->name('url')
                    ->content($frontmatter['canonical']),
            ]);
        }

        // Modified
        if (isset($frontmatter['modified'])) {
            // Prep the date string
            $date = Carbon::createFromFormat(
                config('blog.date_format', 'Y-m-d H:i:s'),
                $frontmatter['modified']
            )->toAtomString();

            // Add in
            seo()->addMany([
                Article::make()->property('modified_time')->content($date),
                OpenGraph::make()->property('updated_time')->content($date),
            ]);
        }
    }
}
