# Change Log
All notable changes to this project will be documented in this file.
Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## [Unreleased][unreleased]

#### Changed

* Update nette libs to version 3.0.0 (BC break)

* Removed support for PHP 5.6, 7.0 and hhvm (BC Break)

## 1.15.0 - 2019-03-13

#### Added

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
