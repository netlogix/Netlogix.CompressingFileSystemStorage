# Netlogix.CompressingFileSystemStorage
Flow package that provides a WritableFileSystemStorage with zlib compression. This storage takes
care of compressing and decompressing data on the fly, so no special handling is required from
the application side.

Internally, PHP stream wrappers are used to deal with compression. Please refer to the [PHP documentation](https://www.php.net/manual/en/wrappers.compression.php).

## Installation
```sh
composer require netlogix/compressingfilesystemstorage
```

## Configuration
A Flow storage has to be configured with the `CompressingWritableFileSystemStorage`:

```yaml
Neos:
  Flow:
    resource:
      storages:
        nlxCompressingFileSystemStorage_zlib:
          storage: Netlogix\CompressingFileSystemStorage\CompressingWritableFileSystemStorage
          storageOptions:
            path: '%FLOW_PATH_DATA%Persistent/CompressingWritableFileSystemStorage/'
            # Can be any PHP stream wrapper, see https://www.php.net/manual/en/wrappers.compression.php
            streamWrapper: 'compress.zlib'
```
