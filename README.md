# sblum Exception Enricher Bundle

[![Build Status](https://github.com/sblum/exception-enricher/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/sblum/exception-enricher/actions)
[![Latest Stable Version](https://poser.pugx.org/sblum/exception-enricher/v/stable)](https://packagist.org/packages/sblum/exception-enricher)
[![License](https://poser.pugx.org/sblum/exception-enricher/license)](https://packagist.org/packages/sblum/exception-enricher)

Symfony bundle which enriches exceptions with some additional information like the request URL, the logged in user's name etc.

## Requirements

* [PHP 8.1](http://php.net/releases/8_1_0.php) or greater
* [Symfony 6.0](https://symfony.com/roadmap/6.0)
* [Monolog](https://packagist.org/packages/monolog/monolog) v2 for version 1.x, v3 for version 2.x

## Installation

1. Require the bundle with Composer:

    ```sh
    composer require sblum/exception-enricher
    ```

1. If not already happened automatically, enable the bundle in `config/bundles.php` by adding it to the array:

    ```php
    ExceptionEnricher\ExceptionEnricherBundle::class => ['all' => true],
    ```
