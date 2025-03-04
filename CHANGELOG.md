# Change Log
All notable changes to this project will be documented in this file.
Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## [Unreleased][unreleased]

## 3.0.0

### Changed
* Support for semantic versioning api.
* [BC] DefaultHandler response code 404 instead 400
* [BC] Added Container to API Decider
* [BC] Output Configurator, Allows different methods for output configuration. Needs to be added to config services.
* [BC] Error handler, Allows for custom error handling of handle method. Needs to be added to config services.
* Query configurator rework

### Added
* CorsPreflightHandlerInterface - resolve multiple service registered handler error
* Lazy API handlers

## 2.12.0

### Added
* Button to copy `Body` content in api console
* Ability to disable schema validation and provide additional error info with get parameters. 

### Changed
* Handler tag wrapper has changed class from `btn` to `label`

### Fixed
* Allowed equal definitions in different handlers in OpenAPIHandler

## 2.11.0

### Added

* Support for symfony/yaml ^5.0 and ^6.0
* Support for JsonSerializable payload in JsonApiResponse
* Support for latte/latte ^3.0 (Added Latte extension for ApiLink)

## 2.10.1

### Fixed

* Fix *null* `$body` for php 8.0

## 2.10.0 - 2023-03-21

### Added

* Allow to set expiration to false in JsonApiResponse and XmlApiResponse
* Render body of response if there is a debug message: Tracy Debug Bar

### Fixed

* Fixed request url separator if url already contain some param

## 2.9.0 - 2022-11-10

### Added

* Support for generating examples for RawInputParam by OpenApiHandler
* PHP 8.1 support (`league/fractal` 0.20.1)
* PHP 8.2 support
* Support for `league/fractal` ~0.17 (Possible BC if you use Fractal classes, you have to update typehints)

### Changed

* If output doesn't match any output schema, error is just logged and output is returned in production mode

### Fixed

* Missing expiration params in XmlApiResponse
* Missing body request for PUT methost

## 2.8.0 - 2022-06-15

### Changed

* ApiLinkMacro now uses latte filter to avoid use presenter context (BC break in nette/application 3.1)

### Fixed

* BaseUrl in OpenApiHandler

## 2.7.0 - 2022-04-07

### Added

* RedirectResponse which implements ResponseInterface
* Available values now can have description in Console and OpenApiHandler. Just use associative array - keys are available values, values are their description

### Fixed

* Yaml format for OpenApiHandler is available only if symfony/yaml is installed
* RedirectOutput use new RedirectResponse
* Property with name `type` is now available in json schema

## 2.6.0 - 2021-10-08

### Updated

* Use relaxed TokenRepositoryInterface in BearerTokenAuthorization ([more info here](https://github.com/tomaj/nette-api/pull/112))

## 2.5.0 - 2021-09-17

### Fixed

* Empty console input fixed by adding checkbox for each parameter
* Handling wrong input for Get and Post InputParam
* File and cookie parameters in Open API handler
* Correct error shown when putting non-OutputInterface into ApiHandlerInterface::outputs()

## 2.4.0 - 2021-04-24

### Added

* User can set own form renderer for API console and own template file(s) for API console and for API listing

## 2.3.1 - 2021-04-13

### Fixed

* Added RateLimit to ApiDecider::addApi

## 2.3.0 - 2021-01-20

### Changed

* Form in API console is rendered with BootstrapVerticalRenderer instead of BootstrapRenderer (labels are over fields instead of left side)

### Added

* Added API key authentication (query, header, cookie) - see https://swagger.io/docs/specification/authentication/api-keys/

* Added missing strict_types declarations

### Fixed

* Open API handler warnings about unused security schemes

* ApiPresenter detailed error unit test

* Compatibility with latte 2.9.0

## 2.2.0 - 2020-08-27

### Added

* Added Basic authentication

## 2.1.0 - 2020-05-12

### Changed

* Rewritten ApiPresenter

### Added

* Added API rate limit 
* Added custom headers to API console
* Added field for timeout to API console
* OpenAPI handler
* Information about RESTful urls

### Fixed

* Fixed sending empty string in multi params
* UrlEncoding values sending through get param inputs
* Fixed static url part `/api/` in console
* Fixed generating urls in console for RESTful urls using ApiLink and EndpointInterface

## 2.0.1 - 2020-03-24

### Fixed

* Fixed return types for ConsoleRequest::processParam() and ConsoleResponse::getResponseHeaders()

## 2.0.0 - 2019-06-12

### Changed

* Updated nette libs to version 3.0.0 (BC break)
* Added typehints (BC break)
* Splitted InputParam to multiple subclasses (BC break)
* Removed type TYPE_POST_JSON_KEY (BC break)
* Wrong input now returns code 400 instead of 500 (BC break if somebody checks return code)
* Replaced handler information array triplet (endpoint, handler, authorization) with class Api (BC break for API console usage)
* Renamed some methods from ApiDecider (BC break)
* Pretty JSON output in API console - without escaping unicode and slashes

### Added

* Added type JsonInputParam with scheme as replacement for type TYPE_POST_JSON_KEY
* Detailed error for wrong input if debugger is enabled
* Added summary (short description), description, tags and deprecated flag for API handlers
* Added description, default value and example for input params
* Added output validator

### Removed

* Removed support for PHP 5.6, 7.0 and hhvm (BC Break)
* Removed deprecated class ApiResponse (BC Break)

## 1.17.0 - 2020-08-27

### Changed

* Detailed error message enabled only for non production mode

## 1.16.0 - 2019-06-19

### Added

* Added ApiLink Macro

## 1.15.0 - 2019-03-13

### Added

* Added possibility to set own scope to Manager
* Added JSON validation - if JSON is invalid, throw "wrong input" error instead of setting params to null

## 1.14.0 - 2018-08-02

### Added

* Added possibility to set own headers for CorsPreflightHandler
* Added expiration to JsonApiResponse

## 1.13.2 - 2018-03-23

### Fixed

* Removed names of components because of commit in nette/component-model (https://github.com/nette/component-model/commit/1fb769f4602cf82694941530bac1111b3c5cd11b)

## 1.13.1 - 2018-03-16

### Fixed

* Fixed console for POST_JSON_KEY(s)

## 1.13.0 - 2018-03-15

### Added

* New InputParam type - POST_JSON_KEY for parsing key from json data in post body
* Updated leaguge/fractal and other packages minor upgrade

## 1.12.0 - 2018-02-08

### Fixed

* Fixed console POST FIELDS output for integer values
* Fixed parsing headers on nginx - missing method `getallheaders`

## 1.11.0 - 2017-05-12

### Added

* Added possibility to send current PHPSESSID via API Web Console
* Removed Content-Type from CORS headers

## 1.10.0 - 2016-12-14

### Added

* Added X-Requested-With header to Cors handler

## 1.9.1 - 2016-11-28

### Fixed

* Added possibility to send empty fields via console request

## 1.9.0 - 2016-11-08

### Added

* Added support for PUT fields

## 1.8.1 - 2016-10-28

* Updated league/fractal library to 0.14.0

## 1.8.0 - 2016-09-28

### Added

* Added cors headers - Access-Control-Allow-Headers and Access-Control-Allow-Methods

## 1.7.0 - 2016-09-11

### Added

* Added default CORS preflight handler
* Added possibility to enable global preflight handler for all handlers with `enableGlobalPreflight()` on `ApiDecider` 

## 1.6.2 - 2016-07-22

### Fixed

* InputParam validation of multi params, if available values are defined

## 1.6.1 - 2016-07-05

### Changed

* End of support for php < 5.6
* Service of type Tomaj\NetteApi\Misc\IpDetectorInterface is no more required if service of type Tomaj\NetteApi\Logger\ApiLoggerInterface is not configured and used
* Service of type Tomaj\NetteApi\Logger\ApiLoggerInterface need not be named "apiLogger"

## 1.6.0 - 2016-05-11

### Added

* Added Content-Length header to Json and Xml responses

## 1.5.0 - 2016-05-04

### Changed

* ApiPresenter: getRequestDomain() returns port as well
* Updated JsonApiResponse - separate charset from content type as changed in nette/application 2.3.12
* Also change nette/application minimal version to 2.3.12

## 1.4.0 - 2016-04-01

### Changed

* changed rendering in API console when available values are set

### Fxed

* fixed negative values for response time in API web console

## 1.3.0 - 2016-02-17

### Added

* added tracy for error logging from ApiPresenter
* added tests for ApiPresenter

## 1.2.0 - 2016-02-11

### Added

* New ability to send new input types:
   - send RAW POST data
   - send COOKIES
   - send FILEs
   - (more info in readme section Inputs)
* all new types are available in test console for easy testing api calls

## 1.1.0 - 2016-01-15

### Added

* Added CORS support to ApiPresenter. Available options:
   - 'auto' - send back header Access-Control-Allow-Origin with domain that made request
   - '*' - send header with '*' - this will work fine if you dont need to send cookies via ajax calls to api with jquery $.ajax with xhrFields: { withCredentials: true } settings
   - 'off' - will not send any CORS header
   - other - any other value will be send in Access-Control-Allow-Origin header
* Rewritten few internal functions

## 1.0.1 - 2015-01-07

### Fixed

* Fixed parsing array variables from GET and POST when using multi InputParam

## 1.0.0 - 2016-01-06

### Added

First version that can be used for api. Contains

* Authorization
* Handling api request
* Logging
* UI Control for api listing
* UI Control for web console to test api endpoints
* Api versioning
