<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\DI;

use Nette\DI\Definitions\Statement;
use Nette\DI\InvalidConfigurationException;
use Tomaj\NetteApi\Logger\LogMasking;
use Tomaj\NetteApi\Presenters\ApiPresenter;

/**
 * Turns api.logging.maskHeaders and api.logging.maskQueryParams into ApiPresenter setup calls, with errors that name the neon key
 *
 * @internal used by NetteApiExtension at container compile time
 *
 * @phpstan-type Entries object{names: list<string>|null, add: list<string>, remove: list<string>}
 * @phpstan-type HeaderEntries object{names: list<string>|null, add: list<string>, remove: list<string>, patterns: bool|list<string>|object{add: list<string>, remove: list<string>}}
 */
final class LoggingConfig
{
    /** ApiPresenter methods that change what each api.logging key sets */
    private const array PRESENTER_METHODS = [
        'maskHeaders' => ['setMaskedLogHeaders', 'addMaskedLogHeader', 'removeMaskedLogHeader', 'setMaskedLogHeaderPatterns', 'addMaskedLogHeaderPattern', 'removeMaskedLogHeaderPattern'],
        'maskQueryParams' => ['setMaskedLogQueryParams', 'addMaskedLogQueryParam', 'removeMaskedLogQueryParam'],
        'masking' => ['setLogMasking'],
    ];

    private const array PRESENTER_WHAT = [
        'maskHeaders' => 'the masked headers',
        'maskQueryParams' => 'the masked query parameters',
        'masking' => 'the masking mode',
    ];

    private const array PRESENTER_EXAMPLE = [
        'maskHeaders' => 'for example addMaskedLogHeader(X-Session) is maskHeaders: {add: [X-Session]}',
        'maskQueryParams' => 'for example addMaskedLogQueryParam(session_id) is maskQueryParams: {add: [session_id]}',
        'masking' => 'for example setLogMasking(Tomaj\\NetteApi\\Logger\\LogMaskMode::Partial) is masking: partial',
    ];

    /**
     * The extension adds its calls after the presenter's own setup, so they would silently replace what the setup changed
     *
     * @param Statement[] $setup the setup of the ApiPresenter service
     * @param 'maskHeaders'|'maskQueryParams'|'masking' $key
     */
    public static function checkPresenterSetup(array $setup, string $key): void
    {
        foreach ($setup as $statement) {
            $entity = $statement->getEntity();
            $method = is_array($entity) ? $entity[1] : null;
            if (in_array($method, self::PRESENTER_METHODS[$key], true)) {
                throw new InvalidConfigurationException(sprintf(
                    'api.logging.%s and the setup of the %s service call %s() both change %s. The extension runs after the setup and would replace it. Keep one place: move the setup call into api.logging.%s (%s), or leave api.logging.%s out.',
                    $key,
                    ApiPresenter::class,
                    $method,
                    self::PRESENTER_WHAT[$key],
                    $key,
                    self::PRESENTER_EXAMPLE[$key],
                    $key,
                ));
            }
        }
    }

    /**
     * @param bool|list<string>|HeaderEntries $value
     * @return list<array{string, list<mixed>}>
     */
    public static function headers(bool|array|object $value): array
    {
        $where = 'api.logging.maskHeaders';
        $example = 'for example maskHeaders: {add: [X-Session], remove: [Set-Cookie]}';
        if ($value === true) {
            return [];
        }
        if ($value === false) {
            return [['setMaskedLogHeaders', [[]]]];
        }
        if (is_array($value)) {
            self::checkNames($value, $where, $example, true, LogMasking::headerNameProblem(...));
            return [['setMaskedLogHeaders', [$value]]];
        }

        $patterns = $value->patterns;
        $setup = self::entries($value, LogMasking::DEFAULT_HEADERS, $where, $example, LogMasking::headerNameProblem(...), 'MaskedLogHeader', 'headers', $patterns !== true, match (true) {
            $patterns === false => [],
            is_array($patterns) => $patterns,
            default => LogMasking::DEFAULT_HEADER_PATTERNS,
        });
        $wherePatterns = $where . '.patterns';
        $examplePatterns = 'for example patterns: {add: [x-signature-*], remove: [*key*]}';
        if ($patterns === false) {
            $setup[] = ['setMaskedLogHeaderPatterns', [[]]];
        } elseif (is_array($patterns)) {
            self::checkNames($patterns, $wherePatterns, $examplePatterns, true, LogMasking::headerPatternProblem(...));
            $setup[] = ['setMaskedLogHeaderPatterns', [$patterns]];
        } elseif (is_object($patterns)) {
            $patterns = (object)['names' => null, 'add' => $patterns->add, 'remove' => $patterns->remove];
            $setup = [...$setup, ...self::entries($patterns, LogMasking::DEFAULT_HEADER_PATTERNS, $wherePatterns, $examplePatterns, LogMasking::headerPatternProblem(...), 'MaskedLogHeaderPattern', 'header patterns', false)];
        }
        return $setup;
    }

    /**
     * @param bool|list<string>|Entries $value
     * @return list<array{string, list<mixed>}>
     */
    public static function queryParams(bool|array|object $value): array
    {
        $where = 'api.logging.maskQueryParams';
        $example = 'for example maskQueryParams: {add: [session_id], remove: [code]}';
        if ($value === true) {
            return [];
        }
        if ($value === false) {
            return [['setMaskedLogQueryParams', [[]]]];
        }
        if (is_array($value)) {
            self::checkNames($value, $where, $example, true, LogMasking::queryParamProblem(...));
            return [['setMaskedLogQueryParams', [$value]]];
        }
        return self::entries($value, LogMasking::DEFAULT_QUERY_PARAMS, $where, $example, LogMasking::queryParamProblem(...), 'MaskedLogQueryParam', 'query parameters', false);
    }

    /**
     * @param Entries $value
     * @param list<string> $defaults
     * @param callable(string): ?string $problem
     * @param list<string> $patterns header patterns that may mask an entry missing in the list
     * @return list<array{string, list<mixed>}>
     */
    private static function entries(object $value, array $defaults, string $where, string $example, callable $problem, string $method, string $what, bool $other, array $patterns = []): array
    {
        $setup = [];
        $list = self::keyed($defaults);
        if ($value->names !== null) {
            self::checkNames($value->names, $where . '.names', $example, true, $problem);
            $setup[] = ['set' . $method . 's', [$value->names]];
            $list = self::keyed($value->names);
        }
        self::checkNames($value->add, $where . '.add', $example, false, $problem);
        self::checkNames($value->remove, $where . '.remove', $example, false, $problem);
        if (!$other && $value->names === null && $value->add === [] && $value->remove === []) {
            throw new InvalidConfigurationException(sprintf('%s is an empty map, so it changes nothing. Use true for the defaults, false to turn the masking off, or names, add or remove, %s.', $where, $example));
        }

        $lower = array_map(strtolower(...), $value->add);
        foreach ($value->add as $entry) {
            $setup[] = ['add' . $method, [$entry]];
            $list[strtolower($entry)] = $entry;
        }
        foreach ($value->remove as $index => $entry) {
            if (in_array(strtolower($entry), $lower, true)) {
                throw new InvalidConfigurationException(sprintf("%s.remove[%d] '%s' is also in %s.add, so it would be added and removed again. Keep it in one of them.", $where, $index, $entry, $where));
            }
            if (!isset($list[strtolower($entry)])) {
                throw new InvalidConfigurationException(sprintf('%s.remove[%d]: %s', $where, $index, LogMasking::notInListProblem($entry, $list, $what, $patterns)));
            }
            $setup[] = ['remove' . $method, [$entry]];
        }
        return $setup;
    }

    /**
     * @param list<string> $names
     * @param callable(string): ?string $problem
     */
    private static function checkNames(array $names, string $where, string $example, bool $replaces, callable $problem): void
    {
        if ($names === [] && $replaces) {
            throw new InvalidConfigurationException(sprintf('%s is an empty list. To turn the masking off write false; to change the defaults use add or remove, %s.', $where, $example));
        }
        foreach ($names as $index => $name) {
            $reason = $problem($name);
            if ($reason !== null) {
                throw new InvalidConfigurationException(sprintf("%s[%d] '%s': %s.", $where, $index, $name, $reason));
            }
        }
    }

    /**
     * @param list<string> $values
     * @return array<string, string>
     */
    private static function keyed(array $values): array
    {
        $keyed = [];
        foreach ($values as $value) {
            $keyed[strtolower($value)] = $value;
        }
        return $keyed;
    }
}
