---
title: 'Example: testing'
description: 'Runnable Nette API example with PHPUnit tests for a handler, its params, its output schemas and its authorization, without a web server.'
---

# Example: testing

An article endpoint and four PHPUnit test classes, each testing one layer on its own.

<ProjectSource name="testing" :files="['tests/HandlerTest.php', 'tests/ParamsTest.php', 'tests/OutputTest.php', 'tests/AuthorizationTest.php', 'app/Api/ArticleDetailHandler.php', 'e2e.sh']" />

<Diagram name="examples-testing-1" alt="HandlerTest calls handle() with params, ParamsTest validates raw input, OutputTest checks the response against the outputs, AuthorizationTest calls authorized() with headers" />

| Test | What it checks |
| --- | --- |
| `HandlerTest` | `handle()` with a found and a missing article |
| `ParamsTest` | `id` is read from the query, a missing one is `Field is required` |
| `OutputTest` | every response matches an output, a wrong shape does not |
| `AuthorizationTest` | `BearerTokenAuthorization` with a token, without one and from another IP |

## The tested handler

<<< @/../examples/testing/app/Api/ArticleDetailHandler.php

## Handler

Call `handle()` with the params as the presenter would pass them.

<<< @/../examples/testing/tests/HandlerTest.php

## Params

<<< @/../examples/testing/tests/ParamsTest.php

## Outputs

<<< @/../examples/testing/tests/OutputTest.php

## Authorization

<<< @/../examples/testing/tests/AuthorizationTest.php

## The test script

<<< @/../examples/testing/e2e.sh{bash}

## FAQ

### Why is the id param untyped?

`GetInputParam` reads `filter_input()`, which sees nothing on the command line, so it falls back to the raw `$_GET` value without converting the type. A typed `INTEGER` param would fail in PHPUnit and pass over HTTP. The handler casts with `(int)` instead.

### Do I need a web server for these tests?

No, every test builds the objects directly. `e2e.sh` adds one HTTP request so the wiring in `common.neon` is tested too.

### How do I test the whole presenter?

Create `ApiPresenter` from the container and call `run()` with a `Nette\Application\Request`, or send real requests as the other examples do.

### Why test outputs?

In production outputs are not checked, so a test is the place where a changed response is caught. See [Outputs](/guide/outputs).

### Which PHPUnit version?

11.5, installed as a dev dependency of the example.
