# Flysystem Adapter for Backblaze B2 Cloud Storage

[![Author](http://img.shields.io/badge/author-@megandavidson-blue.svg?style=flat-square)](https://twitter.com/abisinthe#41) [![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

## Installation

```bash
composer require insanekitty/flysystem-b2-cloud-storage
```

## Usage

Visit [Backblaze](https://www.backblaze.com/b2/cloud-storage.html) and signup for their B2 Cloud Storage, get your "account id" and generate your "application key".

~~~ php

use League\Flysystem\Filesystem;
use Insanekitty\BackblazeB2\Client;
use Insanekitty\Flysystem\BackblazeB2\BackblazeB2Adapter;

$client = new Client($accountId, $applicationKey);
$adapter = new BackblazeB2Adapter($client);

$filesystem = new Filesystem($adapter);

~~~
