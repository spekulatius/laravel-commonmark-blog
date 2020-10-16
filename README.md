## 🚧️ This project isn't production-ready! It's in active development. Until 1.0 breaking changes are to be expected at any time! Use at own risk 🚧️


# [Laravel Commonmark Blog](https://github.com/spekulatius/laravel-commonmark-blog)

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Travis](https://img.shields.io/travis/spekulatius/laravel-commonmark-blog.svg?style=flat-square)]()
[![Total Downloads](https://img.shields.io/packagist/dt/spekulatius/laravel-commonmark-blog.svg?style=flat-square)](https://packagist.org/packages/spekulatius/laravel-commonmark-blog)

A simple filesystem-based, SEO-optimized blog for Laravel using [Commonmark](https://github.com/thephpleague/commonmark) and [Laravel SEO](https://github.com/romanzipp/Laravel-SEO).


## Goals

The goal of this package is to separate the blog content from the application while keeping the content hosted under the root domain (e.g. `project.com/blog` instead of `blog.project.com`). This is preferred from an SEO point of view.

Maximal performance is achieved by avoiding rendering and passing content through the framework. The framework is only used to prepare and render the blog content. The rendered files are written directly to the `public/`-directory to avoid hitting the application entirely. For now (see [#1](https://github.com/spekulatius/laravel-commonmark-blog/issues/1)), this requires tweaking the server configuration (see installation steps).

With a focus on SEO, CommonMark is the logical choice: It is highly extensible allowing for any customization you might need to rank.


## Features

- Converts all `.md` files to HTML files and stores them in the public folder. Other such as `.markdown` are ignored.
- Frontmatter can be defined as global defaults in [`config/blog.php`](https://github.com/spekulatius/laravel-commonmark-blog/blob/main/config/blog.php) and on a part-article basis.
- Assets such as videos, images, etc. as well as any other files are copied over 1:1.

### Simple Post Structure with Frontmatter & Commonmark: Everything in One Place

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

There is also an [example of a blog post](https://github.com/spekulatius/laravel-commonmark-blog/blob/main/example-article.md).

### SEO-Enhancements

There are several SEO-improvements included or easily configurable via extensions:

 - Meta-tags, Twitter Card and Facebook Open-Graph from the post-frontmatter or global
 - Adding lazy-loading attributes to images (optional via extension)
 - Global definitions of `rel`-attributes for root-domain, sub-domains, and external links (optional via extension)

SEO improvements are usually active by default or can be configured using the config file.

#### Planned / Considered

The following extension/improvements are considered for the blog package:

 - Image-Optimization,
 - Schema.org entries using [Spatie/schema-org](https://github.com/spatie/schema-org).


## Requirements & Installation

### Requirements

- PHP 7.2 or higher. PHP8 untested.
- Laravel 7. Support for 8 coming soon.

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

### Server Configuration

Usually, you want pretty URLs (without file-extensions). To achieve this you will need to configure your server slightly. The following shows how to do this for commonly used servers.

#### Nginx

```
location / {
    try_files $uri $uri/ $uri.html /index.php?$query_string;
}
```

Here `$url.html` ensures HTML files are looked for before handing the request through to the Laravel application.

#### Apache

TODO

#### Caddy

TODO


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
