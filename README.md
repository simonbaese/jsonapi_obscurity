# JSON:API Obscurity

A **Drupal** module to handle an obscurity prefix for JSON:API requests.

## :wave: Introduction

:wrench: This module depends on `jsonapi`. Requires at least `PHP 8.0`.

:warning: This is an **alpha** implementation.  
:warning: **Obscurity** is not **Security**.  
:warning: Please be advised that there is no security garantuee.

An obscurity prefix for your JSON:API routes is an effective way to deny automated attacks - see [Security considerations of the JSON:API module](https://www.drupal.org/docs/core-modules-and-themes/core-modules/jsonapi-module/security-considerations#s-5-security-through-obscurity-secret-base-path). The common way to add an obscurity prefix is to modify the JSON:API base bath. This practice is problematic because the _secret_ prefix leaks into the system. For example, the prefix will be displayed in the logs for all access denied requests or in the meta information delivered by the JSON:API module.

This module steps in very early in the request event handling. It validates the obscurity prefix for JSON:API requests and then reinitializes the request with the prefix-stripped path. If the obscurity can not be verified, it will throw a `NotFoundHttpException`. 

There is also some basic support for language code path prefix negotiation. 

## :whale: Usage

After installation you should set the obscurity prefix in `sites/default/services.yml` as follows:

``` yml
parameters:
  jsonapi_obscurity.prefix: '/12345'
```

The module makes some assumptions, when handling a request:
- The `jsonapi.base_path` should _not_ be equal to a standard langcode - see `LanguageManager::getStandardLanguageList()`.
- The `JsonApiObscuritySubscriber` service runs before any other service that requires route information.
- The `OPTIONS` request is still available for the plain JSON:API routes.
- The site only uses standard langcodes, when negotiating languages via a path prefix.

Using this module breaks the functionality of the `EntityToJsonApi` service provided by the `jsonapi_extras` module. This service is used by the `jsonapi_boost` module to warm caches. The repository includes a simple patch (`obscurity-prefix-in-entitytojsonapi-service.patch`) to prepend the obscurity prefix for the requests in the service. The tests of the effected modules may fail after applying the patch.

## :seedling: Contact 

I am open to further develop this module and to discuss your considerations and needs - feel free to contact me.
