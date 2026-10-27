---
title: Contributing
description: 'How to contribute to Nette API and to this documentation: running the tests and example e2e checks, editing diagrams, and previewing the docs locally.'
---

<!--@include: ../CONTRIBUTING.md-->

## This documentation

The site is in `docs/`, built with VitePress, Node 22:

```bash
cd docs
npm ci
npm run docs:dev      # http://localhost:5173/nette-api/
npm run docs:build
npm run docs:links    # check links in the built site
```

- Code on the example pages comes from the projects in `examples/` through `<<< @/../examples/...`; change the project, not the page. Each project has an `e2e.sh` that CI runs.
- Every English page has a Slovak twin under `docs/sk/`; `npm run check` fails when one is missing.
- Diagrams are Excalidraw files in `docs/diagrams/`, rendered to light and dark SVG with `npm run diagrams`.
- Every pull request gets a preview at `https://tomaj.github.io/nette-api/pr-preview/pr-<number>/`.

## FAQ

### How do I run the tests?

`composer test` runs PHPUnit. `composer coverage` also writes coverage reports to `build/` (needs Xdebug). Each example project also has an `e2e.sh`, see [Examples](/examples/).

### Do I have to update the documentation with my change?

Yes, a change in behaviour needs the docs updated, in English and Slovak.

### How do I see my documentation change before it is merged?

Run `npm run docs:dev` in `docs/`, or open the preview that every pull request gets.
