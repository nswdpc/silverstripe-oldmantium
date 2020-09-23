# Reinforced Cloudflare support for Silverstripe websites

> This module is in development, do not use in production

This module provides some extra sharp additions to for Silverstripe installs that use Cloudflare as a frontend

### Features

- Extension for DataObjects with URLs that need to be purged from time-to-time
- Regular purging based on a defined cache max-age
- Max-age per record or per class
- Purge URL(s) related to a record at a certain date
- Add one-off URL records to purge
- Permissions for administration access to purging
- Purge all in zone

## Requirements

See [composer.json](./composer.json) for specifics.

```json
"require": {
    "symbiote/silverstripe-oldman" : "^3",
    "cloudflare/sdk" : "^1.1",
    "symbiote/silverstripe-queuedjobs": "^4",
    "symbiote/silverstripe-multivaluefield" : "^5"
}
```

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

Given a a standard symbiote-oldman configuration:

```yaml
Symbiote\Cloudflare\Cloudflare:
  enabled: true
  email: 'cloudflare@email'
  auth_key: '<auth_key>'
  zone_id: '<zone_id>'
  # Optional, specify a URL to use instead of Director::baseURL()
  base_url: 'https://www.example.com/'
```

Give a `DataObject` the ability to purge from Cloudfront cache

```yaml
My\Namespaced\Thing:
  extensions:
    - 'NSWDPC\Utilities\Cloudflare\DataObjectPurgeable'
```

Configure all records of a DataObject to have a max-age:
```yaml
My\Namespaced\Thing:
  cache_max_age: 86400
```

This will create a queued job running every ```cache_max_age``` seconds that will purge `Thing` records if their LastEdited date is older than this time.


## Maintainers

> Add maintainers here or include [authors in composer](https://getcomposer.org/doc/04-schema.md#authors)

## Bugtracker

> Link to the the issue/bug tracker URL

## Development and contribution

If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.
