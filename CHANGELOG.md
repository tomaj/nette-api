# Change Log
All notable changes to this project will be documented in this file.
Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## [Unreleased][unreleased]


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
