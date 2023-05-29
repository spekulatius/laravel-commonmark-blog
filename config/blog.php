<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Source Path
    |--------------------------------------------------------------------------
    |
    | In this path the package will look for md files to process.
    |
    */

    'source_path' => env('BLOG_SOURCE_PATH', null),

    /*
    |--------------------------------------------------------------------------
    | Base Templates
    |--------------------------------------------------------------------------
    |
    | These blade files will the base-templates for the blog entries and list pages.
    |
    | For example "layouts/blog" would point to "resources/views/layouts/blog.blade.php".
    |
    */

    'article_base_template' => env('BLOG_ARTICLE_BASE_TEMPLATE', null),
    'list_base_template' => env('BLOG_LIST_BASE_TEMPLATE', null),
    'list_per_page' => 12,

    /*
    |--------------------------------------------------------------------------
    | Hreflang x-default
    |--------------------------------------------------------------------------
    |
    | This allows to define which locale should be considered as x-default.
    |
    | This will automatically add it, if the localized version exists.
    |
    */

    'hreflang_default' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Commonmark Extensions
    |--------------------------------------------------------------------------
    |
    | Additional commonmark extension to load. Don't forget to install the composer dependency.
    |
    | @see:
    | - https://github.com/spekulatius/laravel-commonmark-blog#adding-commonmark-extensions
    | - https://commonmark.thephpleague.com/1.5/extensions/overview/
    |
    */

    'extensions' => [
        // 1. Run: composer require simonvomeyser/commonmark-ext-lazy-image
        // 2. Uncomment:
        // new \SimonVomEyser\CommonMarkExtension\LazyImageExtension(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Converter configuration
    |--------------------------------------------------------------------------
    |
    | This configuration will be passed to the converter.
    |
    | Here you can add extension configs.
    |
    */

    'converter_config' => [
    ],

    /*
    |--------------------------------------------------------------------------
    | Mix Assets (CSS & JS)
    |--------------------------------------------------------------------------
    |
    | This allows deactivating the automatic loading of the mix assets.
    |
    | You might want to set it false if you are separate admin-section which isn't user-facing.
    | In this case you need to add the includes manually in the template file.
    |
    */

    'mix' => [
        // Should the mix manifest be used to identify the assets?
        'active' => true,

        // Custom path to the Mix manifest (absolute). Ignore, if default is used.
        'manifest_path' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional Cache Storage
    |--------------------------------------------------------------------------
    |
    | The generated articles (not lists), can be stored in the cache for other usage.
    | This allows usage in sidebars, "read also"-sections, etc.
    |
    | This is deactive by default!
    | Set the cache key to a string, e.g. 'generated-articles' to activate it.
    |
    */

    'cache' => [
        // Name of the key to store it under. Null means deactive.
        'key' => null,

        // Seconds until expiry
        'expiry' => 86400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default values for frontmatter values.
    |--------------------------------------------------------------------------
    |
    | This allows setting default values for frontmatter for both articles and list pages alike.
    | These defaults are overwritten by file-level frontmatter (if defined).
    |
    | In addition, these values are passed into the view renderer
    |  to allow you access from your template file.
    |
    | @see:
    | - https://github.com/spekulatius/laravel-commonmark-blog#frontmatter
    | - https://github.com/joshbuchea/HEAD
    | - https://github.com/romanzipp/Laravel-SEO/blob/master/docs/1-INDEX.md#add-from-array-format-addfromarray
    |
    */

    'defaults' => [
        // Charset and viewport.
        'charset' => 'utf-8',
        'viewport' => 'width=device-width, initial-scale=1',

        // Title & Description
        'title' => env('APP_NAME'),
        // 'description' => 'Default Description',


        // Example #1:
        // 'image' => env('APP_URL') . '/sharing.png',
        //
        // .. with 'https://example.com' as APP_URL renders to ..
        //
        // <meta name="image" content="https://example.com/sharing.png" />
        // <meta name="twitter:image" content="https://example.com/sharing.png" />
        // <meta property="og:image" content="https://example.com/sharing.png" />


        // Example #2:
        // \romanzipp\Seo\Structs\Link::make()
        //     ->rel('webmention')
        //     ->href(env('APP_URL') . '/webmention'),
        //
        // .. will render as ..
        //
        // <link rel="webmention" href="https://example.com/webmention">


        // Example #3:
        //
        // 'og' => [
        //     'site_name' => 'Laravel',
        // ],
        //
        // .. renders ..
        //
        // <meta name="og:site_name" content="Laravel" />
    ],

];
