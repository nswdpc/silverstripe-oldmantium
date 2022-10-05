# Reinforced Cloudflare support for Silverstripe websites

This module provides some extra sharp additions for Silverstripe using Cloudflare as a frontend

### Features

- Versioned DataObject purging, when that DataObject can be represented by one or more URLs
- Purge hosts, tags, prefixes (for [Enterprise]([https://api.cloudflare.com/#zone-purge-files-by-cache-tags,-host-or-prefix) Cloudflare accounts)
- Permissions for administration access to purging
- Purge all in zone via a queued job

## Requirements

See [composer.json](./composer.json) for specifics.

+ Versioned
+ Cloudflare PHP SDK
+ Oldman
+ MultivalueField
+ Queued Jobs

Site publish/unpublish URL purging is undertaken by [symbiote/silverstripe-oldman](https://github.com/symbiote/silverstripe-oldman)


## Installation

```shell
composer require nswdpc/silverstripe-oldmantium
```

## License

BSD-3-Clause

See [License](./LICENSE.md)

## Documentation

* [Documentation](./docs/en/001_index.md)


## Configuration


### Authentication Token

Documentation: https://developers.cloudflare.com/api/get-started/create-token/

1. Create Custom Token, give it a name
1. Zone / Cache Purge / Purge
1. Include Zone / Specific Zone / < zone >
1. Client IP Address filtering: restrict access to token
1. TTL, if required

```yaml
Symbiote\Cloudflare\Cloudflare:
  enabled: true
  auth_token: '<auth_token>'
  zone_id: '<zone_id>'
  # Optional, specify a URL to use instead of Director::baseURL()
  base_url: 'https://www.example.com/'
```

### Authentication Key (legacy)

Documentation: https://developers.cloudflare.com/api/get-started/keys/

```yaml
Symbiote\Cloudflare\Cloudflare:
  enabled: true
  email: 'cloudflare@email'
  auth_key: '<auth_key>'
  zone_id: '<zone_id>'
  # Optional, specify a URL to use instead of Director::baseURL()
  base_url: 'https://www.example.com/'
```

### Versioned DataObject

Give a Versioned `DataObject` the ability to purge from Cloudfront cache

```yaml
My\Namespaced\Record:
  extensions:
    - 'NSWDPC\Utilities\Cloudflare\DataObjectPurgeable'
```

When `My\Namespaced\Record` is published or unpublished, the corresponding URLCachePurgeJob will be created as a queued job.


## Maintainers

> Add maintainers here or include [authors in composer](https://getcomposer.org/doc/04-schema.md#authors)

## Bugtracker

> Link to the the issue/bug tracker URL

## Development and contribution

If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.
