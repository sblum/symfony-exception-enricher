# sblum Exception Enricher Bundle

[![Build Status](https://github.com/sblum/exception-enricher/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/sblum/exception-enricher/actions)
[![Latest Stable Version](https://poser.pugx.org/sblum/exception-enricher/v/stable)](https://packagist.org/packages/sblum/exception-enricher)
[![License](https://poser.pugx.org/sblum/exception-enricher/license)](https://packagist.org/packages/sblum/exception-enricher)

Symfony bundle which enriches exceptions with some additional information like the request URL, the logged in user's name etc.

## Requirements

* [PHP 8.3](https://www.php.net/releases/8.3/) or greater
* Symfony 6.4, 7.4 or 8.1 (Symfony 8 needs PHP 8.4)
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

## What gets logged

Every log record gets these `extra` fields, as far as they are available:

| Field | Content |
|---|---|
| `request_uri` | method and URI, e.g. `POST /api/upload` |
| `request_user_agent` | the `User-Agent` header |
| `request_ip` | client IP of the main request |
| `session_id` | ID of the current session |
| `username` | identifier of the logged-in user |
| `request_post_data` | POST data, **only for records of level `WARNING` and above** |

`request_post_data` contains the serialized form fields, or else the request body. To keep a single request from filling the log (e.g. a file upload) and to keep credentials out of it:

* it is cut off after 2048 characters, with a note of the full length (`… [truncated, 7340080 characters]`)
* values of keys containing `password`, `passwd`, `secret`, `token`, `jwt`, `authorization`, `apikey`, `api_key` or `credential` (case-insensitive, also nested, in form fields and JSON bodies) are replaced by `***`; such a value is replaced as a whole, also when it is a list or an object
* a body that looks like JSON but can't be decoded (broken, or nested too deeply) can't be masked, so it is left out: `[JSON body omitted: it could not be decoded to mask secrets, N characters]`
