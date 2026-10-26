---
title: 'Example: pagination'
description: 'Runnable Nette API example with typed page and per_page query params, defaults and allowed values, prev and next links, and an output schema for every status code.'
---

# Example: pagination

`GET /api/v1/products?page=2&per_page=5` returns one page of 23 products with links to the previous and next page.

<ProjectSource name="pagination" :files="['config/common.neon', 'app/Api/ProductListHandler.php', 'e2e.sh']" />

<Diagram name="examples-pagination-1" alt="page and per_page are read as integers with defaults; per_page outside 5, 10 or 20 answers 400; a page past the end answers 404; otherwise 200 with items and links" />

| Request | Response |
| --- | --- |
| `/api/v1/products` | `200`, page 1, 10 items, `next` link |
| `?page=3&per_page=10` | `200`, 3 items, no `next` |
| `?per_page=7` | `400 Field contains not available value(s)` |
| `?page=0` or `?page=abc` | `400` from the handler |
| `?page=9` | `404`, last page is 3 |

## The handler

<<< @/../examples/pagination/app/Api/ProductListHandler.php

## The services

<<< @/../examples/pagination/config/common.neon{yaml}

## The test

<<< @/../examples/pagination/e2e.sh{bash}

## FAQ

### Why does page=abc reach the handler?

A value that is not a number becomes `null` for an `INTEGER` param in 3.x, and the default does not apply then. The handler checks it. See [Input params](/guide/params).

### Why does every status code have an output?

Output validation accepts a response only when an output matches its code and schema. Without the `400` and `404` outputs, those responses would turn into `500` in development. See [Outputs](/guide/outputs).

### Are the allowed values compared as numbers?

`setAvailableValues([5, 10, 20])` checks the value that arrived in the query, so `per_page=7` answers `400` before the handler runs.

### Why links in the body and not a Link header?

Both work. A body is easier to read in the console; for a header, inject `Nette\Http\IResponse` as in the [versions example](/examples/versions).

### How do I paginate a database?

Use `per_page` as `LIMIT` and `(page - 1) * per_page` as `OFFSET`, and one `COUNT(*)` for the total.
