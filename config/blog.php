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
    | Base Template
    |--------------------------------------------------------------------------
    |
    | This blade file will the base-template for the blog entries.
    |
    | For example "layouts/blog" would point to "resources/views/layouts/blog.blade.php".
    |
    */

    'base_template' => env('BLOG_BASE_TEMPLATE', null),

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

        // Custom manifest path (ignore, if default is used)
        'manifest_path' => public_path('mix-manifest.json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default values for frontmatter values.
    |--------------------------------------------------------------------------
    |
    | This allows setting default values for frontmatter.
    | These defaults are overwritten by post-frontmatter (if defined).
    |
    | @see:
    | - https://github.com/spekulatius/laravel-commonmark-blog#frontmatter
    | - https://github.com/romanzipp/Laravel-SEO/blob/master/docs/1-INDEX.md#add-from-array-format-addfromarray
    |
    */

    'defaults' => [
        // Charset and viewport.
        'charset' => 'utf-8',
        'viewport' => 'width=device-width, initial-scale=1',

        // Example #1:
        'title' => env('APP_NAME'),
        // calls:
        // seo()->title(env('APP_NAME'))


        // Example #2:
        // 'image' => env('APP_URL') . '/sharing.png',
        //
        // will call
        //
        // seo()->image(env('APP_URL') . '/sharing.png')


        // Example #3:
        //
        // 'og' => [
        //     'site_name' => 'Laravel'
        // ]
        //
        // calls:
        //
        // seo()->og('site_name', 'Laravel')
        //
        // to render:
        //
        // <meta name="og:site_name" content="Laravel" />
    ],

    /*
    |--------------------------------------------------------------------------
    | Commonmark Extensions
    |--------------------------------------------------------------------------
    |
    | Additional commonmark extension to load.
    |
    | @see:
    | - https://commonmark.thephpleague.com/1.5/extensions/overview/
    | - https://github.com/spekulatius/laravel-commonmark-blog#adding-commonmark-extensions
    |
    */

    'extensions' => [
        // 1. Run: composer require simonvomeyser/commonmark-ext-lazy-image
        // 2. Uncomment:
        // new \SimonVomEyser\CommonMarkExtension\LazyImageExtension(),
    ],

];
