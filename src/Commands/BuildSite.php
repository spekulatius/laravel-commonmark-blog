<?php

namespace Spekulatius\LaravelCommonmarkBlog\Commands;

use League\CommonMark\Environment;
use League\CommonMark\CommonMarkConverter;
use Illuminate\Console\Command;
use romanzipp\Seo\Structs\Link;
use romanzipp\Seo\Structs\Script;
use romanzipp\Seo\Conductors\Types\ManifestAsset;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Spatie\YamlFrontMatter\YamlFrontMatter;

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


        // Add the preloading for Laravel elements in.
        if (config('blog.mix.active')) {
            // Add the prefetching in.
            $manifest_assets = seo()
                ->mix()
                ->map(static function(ManifestAsset $asset): ?ManifestAsset {
                    $asset->url = env('APP_URL') . $asset->url;

                    return $asset;
                })
                ->load(config('blog.mix.manifest_path'))
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
            $this->convertFile(
                config('blog.base_template'),
                $file->getRealPath(),
                public_path(preg_replace('/md$/', 'html', $file->getRelativePathname()))
            );

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
     * Convert a given source file into ready-to-ship HTML document.
     *
     * @param string $template
     * @param string $source_file
     * @param string $target_file
     */
    protected function convertFile(string $template, string $source_file, string $target_file)
    {
        $this->info('Converting ' . $source_file);

        // Split frontmatter and the commonmark parts.
        $article = YamlFrontMatter::parse(file_get_contents($source_file));

        // Prepare the information to hand to the view - the frontmatter and headers+content.
        $data = array_merge(
            array_merge(config('blog.defaults', []), $article->matter()),
            [
                'header' => $this->prepareLaravelSEOHeaders($article->matter()),
                'content' => $this->converter->convertToHtml($article->body()),
            ]
        );

        // Render the file using the blade file and write it.
        file_put_contents($target_file, view($template, $data)->render());
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

        // Fill in some cases - e.g. image, canonical, etc.
        // @todo

        // Add all custom structs from the list in.
        seo()->addMany(array_values(array_filter($frontmatter, function ($entry) {
            return $entry instanceof \romanzipp\Seo\Structs\Struct;
        })));

        // Filter any methods which aren't allowed for misconfigured.
        seo()->addFromArray(array_filter($frontmatter, function($value, $key) {
            return is_string($value) && (
                in_array($key, [
                    'charset',
                    'viewport',
                    'title',
                    'description',
                ]) || in_array($key, [
                    'og',
                    'twitter',
                    'meta',
                ]) && is_array($value)
            );
        }, ARRAY_FILTER_USE_BOTH));

        // Return the combined result, rendered.
        return seo()->render();
    }
}
