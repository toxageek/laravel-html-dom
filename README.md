# Laravel HTML DOM Parser

A fast, simple and reliable HTML document parser for Laravel. Created based on [PHP Simple HTML DOM Parser](https://simplehtmldom.sourceforge.io/docs/1.9/) 1.9.1

## Parse any HTML document

Laravel HTML DOM Parser handles any HTML document, even ones that are considered invalid by the HTML specification.

## Select elements using CSS selectors

Laravel HTML DOM Parser supports CSS style selectors to navigate the DOM, similar to jQuery.


## Installation


```bash
composer require "toxageek/laravel-html-dom"
```


## Publishing the config file

```bash
php artisan vendor:publish --provider="Toxageek\LaravelHtmlDom\LaravelHtmlDomServiceProvider" --tag="config"
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
