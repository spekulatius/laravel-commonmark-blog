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

class BuildSite extends Command
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
    protected $description = 'Builds the site from the source files.';

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
    protected $source_path = null;

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
        $source_path = $this->argument('source_path') ?? config('blog.source_path');
        if (is_null($source_path)) {
            $this->error('No source path defined.');
            die;
        }
        $this->source_path = $source_path;

        // Checks
        if (is_null(config('blog.base_template'))) {
            $this->error('No base template defined.');
            die;
        }


        // Prepare the enviroment with the custom extensions.
        $this->environment = Environment::createCommonMarkEnvironment();
        foreach (config('blog.extensions') as $extension) {
            $this->environment->addExtension($extension);
        }

        // Create the converter.
        $this->converter = new CommonMarkConverter([], $this->environment);
    }

    /**
     * Finds and converts all files to process.
     *
     * @return void
     */
    protected function convertFiles()
    {
        $this->info('Building from ' . $this->source_path);

        // Mirror the complete structure over to create the folder structure as needed.
        (new Filesystem)->mirror($this->source_path, public_path());

        // Identify the files to process
        foreach ($this->findFiles($this->source_path) as $file) {
            // Convert the file and store it directly in the public folder.
            $this->convertArticle(config('blog.base_template'), $file);

            // Delete the copied over instance of the file
            unlink(public_path($file->getRelativePathname()));
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
     * Convert a given article into ready-to-ship HTML document.
     *
     * @param string $template
     * @param SplFileInfo $file
     */
    protected function convertArticle(string $template, SplFileInfo $file)
    {
        $this->info('Converting ' . $file->getRelativePathname());

        // Split frontmatter and the commonmark parts.
        $article = YamlFrontMatter::parse(file_get_contents($file->getRealPath()));

        // Prepare the information to hand to the view - the frontmatter and headers+content.
        $data = array_merge(
            array_merge(config('blog.defaults', []), $article->matter()),
            [
                'header' => $this->prepareLaravelSEOHeaders($article->matter()),
                'content' => $this->converter->convertToHtml($article->body()),
            ]
        );

        // Define the target directory and create it (optionally).
        $target_directory = public_path(preg_replace('/\.md$/', '', $file->getRelativePathname()));
        if (!file_exists($target_directory)) {
            mkdir($target_directory);
        }

        // Render the file using the blade file and write it as index.html into the directory.
        file_put_contents($target_directory . '/index.html', view($template, $data)->render());
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

        // Fill in some cases - e.g. image, canonical, etc.
        $this->fillIn($frontmatter);

        // Add all custom structs from the list in.
        seo()->addMany(array_values(array_filter($frontmatter, function ($entry) {
            return $entry instanceof Struct;
        })));

        // Filter any methods which aren't allowed for misconfigured.
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
        $header_tags = seo()->render();

        // Reset any previously set structs after the view is rendered.
        seo()->clearStructs();

        // Return the combined result, rendered.
        return $header_tags;
    }

    /**
     * Helper to include the mix assets.
     */
    protected function includeMixAssets()
    {
        // Add the preloading for Laravel elements in.
        if (config('blog.mix.active')) {
            // Add the prefetching in.
            $manifest_assets = seo()
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
            foreach ($manifest_assets as $asset) {
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
