---
title: 'Príklad: testovanie'
description: 'Spustiteľný príklad Nette API s PHPUnit testami handlera, jeho parametrov, výstupných schém a autorizácie, bez webového servera.'
---

# Príklad: testovanie

Endpoint pre článok a štyri triedy PHPUnit testov, každá testuje jednu vrstvu samostatne.

<ProjectSource name="testing" :files="['tests/HandlerTest.php', 'tests/ParamsTest.php', 'tests/OutputTest.php', 'tests/AuthorizationTest.php', 'app/Api/ArticleDetailHandler.php', 'e2e.sh']" />

<Diagram name="examples-testing-1" alt="HandlerTest volá handle() s parametrami, ParamsTest validuje surový vstup, OutputTest overí odpoveď voči výstupom, AuthorizationTest volá authorized() s hlavičkami" />

| Test | Čo overuje |
| --- | --- |
| `HandlerTest` | `handle()` s nájdeným a chýbajúcim článkom |
| `ParamsTest` | `id` sa načíta z query, chýbajúce je `Field is required` |
| `OutputTest` | každá odpoveď zodpovedá výstupu, zlý tvar nie |
| `AuthorizationTest` | `BearerTokenAuthorization` s tokenom, bez neho a z inej IP |

## Testovaný handler

<<< @/../examples/testing/app/Api/ArticleDetailHandler.php

## Handler

Zavolajte `handle()` s parametrami tak, ako by ich odovzdal presenter.

<<< @/../examples/testing/tests/HandlerTest.php

## Parametre

<<< @/../examples/testing/tests/ParamsTest.php

## Výstupy

<<< @/../examples/testing/tests/OutputTest.php

## Autorizácia

<<< @/../examples/testing/tests/AuthorizationTest.php

## Testovací skript

<<< @/../examples/testing/e2e.sh{bash}

## Časté otázky

### Prečo parameter id nemá typ?

`GetInputParam` číta `filter_input()`, ktorý na príkazovom riadku nič nevidí, takže použije surovú hodnotu z `$_GET` bez prevodu typu. Typovaný parameter `INTEGER` by v PHPUnit zlyhal a cez HTTP prešiel. Handler preto pretypuje cez `(int)`.

### Potrebujem na tieto testy webový server?

Nie, každý test si objekty vytvorí priamo. `e2e.sh` pridá jednu HTTP požiadavku, aby sa otestovalo aj zapojenie v `common.neon`.

### Ako otestujem celý presenter?

Vytvorte `ApiPresenter` z kontajnera a zavolajte `run()` s `Nette\Application\Request`, alebo posielajte skutočné požiadavky ako ostatné príklady.

### Prečo testovať výstupy?

V produkcii sa výstupy nekontrolujú, takže zmenenú odpoveď zachytí práve test. Pozrite si [Výstupy](/sk/guide/outputs).

### Ktorá verzia PHPUnit?

11.5, nainštalovaná ako dev závislosť príkladu.
