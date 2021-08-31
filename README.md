# sblum Exception Enricher Bundle

[![Build Status](https://travis-ci.org/sblum/exception-enricher.svg?branch=master)](https://travis-ci.org/sblum/exception-enricher)
[![Latest Stable Version](https://poser.pugx.org/sblum/exception-enricher/v/stable)](https://packagist.org/packages/sblum/exception-enricher)
[![License](https://poser.pugx.org/sblum/exception-enricher/license)](https://packagist.org/packages/sblum/exception-enricher)

Symfony bundle which enriches exceptions with some additional information like the request URL, the logged in user's name etc.

## Requirements

* [PHP 7.4](http://php.net/releases/7_4_0.php) or greater
* [Symfony 4.4](https://symfony.com/roadmap/4.4) or [Symfony 5.3](https://symfony.com/roadmap/5.3)

## Installation

1. Require the bundle with Composer:

    ```sh
    composer require sblum/exception-enricher
    ```

1. If not already happened automatically, enable the bundle in `config/bundles.php` by adding it to the array:

    ```php
    ExceptionEnricher\ExceptionEnricherBundle::class => ['all' => true],
    ```
