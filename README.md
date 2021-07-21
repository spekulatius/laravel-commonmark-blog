![Laravel Commonmark Blog Library](header.jpg)

# [Laravel Commonmark Blog](https://github.com/spekulatius/laravel-commonmark-blog)

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/spekulatius/laravel-commonmark-blog.svg?style=flat-square)](https://packagist.org/packages/spekulatius/laravel-commonmark-blog)

A simple filesystem-based, SEO-optimized blog for Laravel using [Commonmark](https://commonmark.org) and [Laravel SEO](https://github.com/romanzipp/Laravel-SEO).


## Goals & Main Concepts

The goal of this package is to separate the blog content from the application while keeping the content hosted under the root domain (e.g. `project.com/blog` instead of `blog.project.com`). This is preferred from an SEO point of view.

Maximal performance is achieved by avoiding rendering and passing content through the framework. The framework is only used initially to prepare and render the blog content. The rendered files are written directly to the `public/`-directory to avoid hitting the application. Assuming correct server configuration, the blog achieves (near) static-site performance levels.

For each file, a directory with an `index.htm` is created to avoid additional server configuration. For example, the file `blog/my-article.md` would be stored as `blog/my-article/index.htm`. Most web-server are already configured to serve these files directly.

With a focus on SEO, CommonMark is the logical choice: It is highly extensible allowing for any customization you might need to rank. There is also an [example repository demonstrating the blog](https://github.com/spekulatius/laravel-commonmark-blog-example) further.


## Core Features

- Support of both articles and article-listing pages. The [example repo](https://github.com/spekulatius/laravel-commonmark-blog-example) shows how to.
- **CommonMark**: [PHP CommonMark](https://github.com/thephpleague/commonmark) to support extensibility. By default, all `.md` files are converted to HTML files. The HTML files are stored in the `public/`-directory. Other file extensions such as `.markdown` are ignored.
- **Frontmatter** can be defined as global defaults in [`config/blog.php`](https://github.com/spekulatius/laravel-commonmark-blog/blob/main/config/blog.php) and on a per-article basis.
- **Assets** such as videos, images, etc. as well as any other files are copied over 1:1.
- Information about the generated articles can be stored in the cache optionally. This allows adding elements dynamically to sidebars, footers, etc.
- **Automatic embargo**: Articles with publication dates in the future will not be converted. Manually added links are not checked and will be included by default. Files with the ending `*.emb.md` will be moved over within the same directory.
- **hreflang**: You can add an array with alternative language URLs to the frontmatter and it will be converted to hreflang tags. This way you can build multi-lingual sites.

### SEO-Enhancements

There are several SEO improvements included or easily configurable via extensions:

- Meta-tags, Twitter Card, and Facebook Open-Graph from the post-frontmatter or globally
- Adding lazy-loading attributes to images (optional via extension)
- Global definitions of `rel`-attributes for root-domain, sub-domains, and external links (optional via extension)

SEO improvements are usually active by default or can be configured using the config file.

#### Planned / Considered SEO-related enhancements

The following extension/improvements are considered for the blog package:

- Image-Optimization,
- Schema.org entries using [Spatie/schema-org](https://github.com/spatie/schema-org).


## How to Use This Package

Below are examples of how to use the blog package.

### How to Add a Post

Any blog page is following a simple structure using Frontmatter & Commonmark. The YAML Frontmatter is used to define post-level information such as titles, social sharing images, etc. with the CommonMark content following:

```yaml
---
title: "The Love of Code"
description: "Why I love to code."
image: "/images/code.jpg"
---

# The Love Of Code

....
```

Default values can be set under `defaults` in the config file. If you are unsure which headers to include consult [joshbuchea/HEAD](https://github.com/joshbuchea/HEAD).

### How to Add an Article Listing Page

Listing pages can be created by adding a file called `index.md` within a directory. With this, the rendering method gets the following parameters passed in:

 - the complete frontmatter (the current list page' frontmatter merged with the `defaults` from [`config/blog.php`](https://github.com/spekulatius/laravel-commonmark-blog/blob/main/config/blog.php),
 - the CommonMark-rendered content of the listing page as `$content`,
 - the `$total_pages` as the number of pages,
 - the `$current_page` for the number of the page,
 - the `$base_url` for the pagination pages, and
 - the `$articles` for the articles.

With this information, your Blade-file should be able to render a complete article listing page. In addition to the numbered page files an `index` file is added to allow a "root"-page without a page number. The following example explains this more.

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
- By default, the articles includes also articles in further nested directories below.
- All pages will automatically receive a canonical URL according to the page number.
- The first page (here `/blog/1`) is only a copy of the `index.htm` to allow access with a number. It automatically contains a canonical URL to the variation without page number (here: `/blog`).

### Multi-language blogs with `hreflang`

The blog module supports multi-lingual blogs using `hreflang`. Each language version of an article will live in a separate markdown file and is cross-references using `hreflang`:

**English article:**

```yaml
---
title: "The Love of Code"
description: "Why I love to code."
canonical: "/the-love-of-code/"

locale: "en"
hreflang:
    de: "/de/die-liebe-zum-programmieren/"
---

# The Love Of Code

....
```

**German article:**

```yaml
---
title: "Die Liebe zum Programmieren"
description: "Warum ich Programmieren liebe."
canonical: "/de/die-liebe-zum-programmieren/"

locale: "de"
hreflang:
    en: "/the-love-of-code/"
---

# Die Liebe zum Programmieren

....
```

**Please note:** This doesn't consider embargo (delayed publishing) at the moment. You will need to ensure that your site doesn't reference a not-yet-published article manually.

## Requirements & Installation

### Requirements

- PHP 7.3 or higher
- Laravel 6, 7 and 8
- Serving of `index.htm` files by your web-server (default for Nginx)

### Installation

This package is distributed using composer. If you aren't using composer you probably already know how to install a package. Here are the steps for composer-based installation:

```bash
composer require spekulatius/laravel-commonmark-blog
```

Next, publish the configuration file:

```bash
php artisan vendor:publish --provider="Spekulatius\LaravelCommonmarkBlog\CommonmarkBlogServiceProvider" --tag="blog-config"
```

Review, extend and adjust the configuration under `config/blog.php` as needed. The required minimum is a `BLOG_SOURCE_PATH` and some default frontmatter.

### Adding Commonmark Extensions

You can add Commonmark extensions to your configuration file under `extensions`:

```php
'extensions' => [
    new \SimonVomEyser\CommonMarkExtension\LazyImageExtension(),
],
```

Make sure to run the required composer install commands for the extensions before. Packages are usually not required by default.

In the configuration file `config/blog.php`, you can add additional configuration for the extensions under `config`.


## Usage: Rendering of the Blog Posts

The build of the blog is done using an [Artisan](https://laravel.com/docs/artisan) command:

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
