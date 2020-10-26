![Laravel Commonmark Blog Library](header.jpg)

# [Laravel Commonmark Blog](https://github.com/spekulatius/laravel-commonmark-blog)

**🚧️ This project isn't production-ready! It's in active development. Until 1.0 breaking changes are to be expected at any time! Use at own risk 🚧️**

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/spekulatius/laravel-commonmark-blog.svg?style=flat-square)](https://packagist.org/packages/spekulatius/laravel-commonmark-blog)

A simple filesystem-based, SEO-optimized blog for Laravel using [Commonmark](https://commonmark.org) and [Laravel SEO](https://github.com/romanzipp/Laravel-SEO).


## Goals & Main Concepts

The goal of this package is to separate the blog content from the application while keeping the content hosted under the root domain (e.g. `project.com/blog` instead of `blog.project.com`). This is preferred from an SEO point of view.

Maximal performance is achieved by avoiding rendering and passing content through the framework. The framework is only used to prepare and render the blog content. The rendered files are written directly to the `public/`-directory to avoid hitting the application entirely. This way, the blog achieves static-site performance levels.

For each file a directory with an `index.htm` is created to avoid additional server configuration. For example, the file `blog/my-article.md` would be stored as `blog/my-article/index.htm`. Most web-server are configured to serve these files directly.

With a focus on SEO, CommonMark is the logical choice: It is highly extensible allowing for any customization you might need to rank.


## Features

- Support of both articles and article-listing pages.
- **CommonMark**: [PHP CommonMark](https://github.com/thephpleague/commonmark) to support extensibility. By default, all `.md` files are converted to HTML files. The HTML files are stored in the `public/`-directory. Other such as `.markdown` are ignored.
- **Frontmatter** can be defined as global defaults in [`config/blog.php`](https://github.com/spekulatius/laravel-commonmark-blog/blob/main/config/blog.php) and on a part-article basis.
- **Assets** such as videos, images, etc. as well as any other files are copied over 1:1.

There is also an [example repository demonstrating the blog](https://github.com/spekulatius/laravel-commonmark-blog-example) further.

### SEO-Enhancements

There are several SEO-improvements included or easily configurable via extensions:

 - Meta-tags, Twitter Card and Facebook Open-Graph from the post-frontmatter or globally
 - Adding lazy-loading attributes to images (optional via extension)
 - Global definitions of `rel`-attributes for root-domain, sub-domains, and external links (optional via extension)

SEO improvements are usually active by default or can be configured using the config file.

#### Planned / Considered

The following extension/improvements are considered for the blog package:

 - Image-Optimization,
 - Schema.org entries using [Spatie/schema-org](https://github.com/spatie/schema-org).


## How to Use This Package

Below are examples on how to use the blog package.

### How to Add a Simple Post

Any blog page is following a simple structure using Frontmatter & Commonmark.

YAML Frontmatter is used to define post-level information such as titles, social sharing images, etc.:

```yaml
---
title: "The Love of Code"
description: "Why I love to code."
image: "/images/code.jpg"
---

# The Love Of Code

....
```

Default values can be set using the key `defaults` in the config file. A great resource on what to include is [joshbuchea/HEAD](https://github.com/joshbuchea/HEAD).

### How to Add a Simple Listing Page

Listing pages can be created by adding a page called `index.md` in a directory. With this, the blade-rendering function of gets the following parameters passed in:

 - the complete frontmatter (merged from the `defaults` in [`config/blog.php`](https://github.com/spekulatius/laravel-commonmark-blog/blob/main/config/blog.php) and the current page' frontmatter (same as regular articles),
 - the CommonMark-rendered content of the listing page as `content`,
 - the `total_pages` as the number of pages,
 - the `current_page` for the number of the page, and
 - the `articles` for the current page.

With this information your Blade-file should be able to render a complete page. For listing pages a directory will be created and the required files will be added to cover the pagination. In addition the numbered page-files an index file is added to allow a "root"-page without page number.

If three listing pages with articles need to be created the following files would be created:

```
domain.com/blog/index.htm
domain.com/blog/1.htm
domain.com/blog/2.htm
domain.com/blog/3.htm
```

Most web-servers will serve these as:

```
domain.com/blog
domain.com/blog/1
domain.com/blog/2
domain.com/blog/3
```

Note:
- By default the articles includes also articles in further nested directories below.
- All pages will automatically receive a canonical URL according to the page number.
- The first page (here `/blog/1`) is only a copy of the `index.htm` to allow accessing it with number. It automatically contains a canoncial URL to the variation without page number (here: `/blog`).


## Requirements & Installation

### Requirements

- PHP 7.2 or higher. PHP8 untested.
- Laravel 7. Support for 8 coming soon.
- Automatic serving of `index.htm` files by your web-server (default for Nginx)

### Installation

This package is distributed using composer. If you aren't using composer you probably already know how to install a package. Here the steps for composer-based installation:

```bash
composer require spekulatius/laravel-commonmark-blog
```

Next, publish the configuration file:

```bash
php artisan vendor:publish --provider="Spekulatius\LaravelCommonmarkBlog\CommonmarkBlogServiceProvider" --tag="blog-config"
```

Review, extend and adjust the configuration under `config/blog.php` as needed. The required mimimum is a `BLOG_SOURCE_PATH` and some default frontmatter.

### Adding Commonmark Extensions

You can add Commonmark extensions to your configuration file under `extensions`:

```php
'extensions' => [
    new \SimonVomEyser\CommonMarkExtension\LazyImageExtension(),
],
```

Make sure to run the required composer install commands for the extensions before. Packages are usually not required by default.


## Usage: Rendering of the Blog Posts

The build of the blog is done using an [Artisan](https://laravel.com/docs/7.x/artisan) command:

```bash
php artisan blog:build
```

You can optionally pass some parameters, see `php artisan help blog:build` for details.

Usually, this step would be triggered as part of the deployment process. You can set up two repositories (one for your project and one for your blog) and let both trigger the build as needed.

You could also schedule the command in your `app/Console/Kernel.php` to ensure regular updates.

**Hint:** Make sure to [update your sitemap.xml](https://github.com/bringyourownideas/laravel-sitemap) after each build.

Naturally, the way you integrate the blog in your project depends on the deployment tools and process.


## Contributing & License

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

Released under the MIT license. Please see [License File](LICENSE.md) for more information.
