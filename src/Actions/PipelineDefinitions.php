<?php

namespace BBCli\BBCli\Actions;

/**
 * PipelineDefinitions
 * Reads the pipelines a bitbucket-pipelines.yml defines.
 *
 * Not a YAML parser: it reads the section and pattern names under the
 * top-level `pipelines:` key and skips everything beneath them, which is all
 * `bb pipeline list` needs. Constructs that would make those names
 * unreliable (flow style, aliases, merge keys, tab indentation) throw rather
 * than guess.
 */
class PipelineDefinitions
{
    /**
     * @param  string $yaml Contents of bitbucket-pipelines.yml.
     * @return array<string, array<int, string>> Section name => pattern names,
     *         in file order. `default` has no names.
     *
     * @throws \Exception
     */
    public static function parse($yaml)
    {
        $sections = [];
        $inPipelines = false;
        $sectionIndent = null;
        $childIndent = null;
        $current = null;

        foreach (preg_split('/\R/', $yaml) as $rawLine) {
            $line = rtrim(self::stripComment($rawLine));
            if (trim($line) === '') {
                continue;
            }

            $indent = strlen($line) - strlen(ltrim($line, ' '));

            if (!$inPipelines) {
                if ($indent === 0 && preg_match('/^pipelines\s*:(.*)$/', $line, $m)) {
                    self::assertBlockValue(trim($m[1]), 'pipelines');
                    $inPipelines = true;
                }
                continue;
            }

            if (preg_match('/^ *\t/', $line)) {
                throw new \Exception('Unsupported YAML in bitbucket-pipelines.yml: indentation uses a tab.', 1);
            }

            if ($indent === 0) {
                break;
            }

            $text = ltrim($line);

            if ($sectionIndent === null) {
                $sectionIndent = $indent;
            }

            if ($indent === $sectionIndent) {
                $key = self::parseKey($text, $rest);
                if ($key === null) {
                    continue;
                }
                if ($key !== 'default') {
                    self::assertBlockValue($rest, $key);
                }
                $current = $key;
                $childIndent = null;
                $sections[$current] = [];
            } elseif ($indent > $sectionIndent && $current !== null && $current !== 'default') {
                if ($childIndent === null) {
                    $childIndent = $indent;
                }
                if ($indent === $childIndent) {
                    $name = self::parseKey($text, $rest);
                    if ($name !== null) {
                        $sections[$current][] = $name;
                    }
                }
            }
        }

        if (!$sections) {
            throw new \Exception('No pipelines found in bitbucket-pipelines.yml.', 1);
        }

        return $sections;
    }

    /**
     * Reads the mapping key at the start of a line.
     *
     * @param  string      $text Line with its indentation removed.
     * @param  string|null $rest Whatever follows the colon, trimmed.
     * @return string|null Null when the line is not a key (e.g. a "- " item).
     *
     * @throws \Exception
     */
    private static function parseKey($text, &$rest = null)
    {
        $rest = '';

        if ($text[0] === '*') {
            throw new \Exception('Unsupported YAML in bitbucket-pipelines.yml: an alias is used where a pipeline name is expected.', 1);
        }

        if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"\s*:(?=\s|$)(.*)$/', $text, $m)) {
            $key = stripcslashes($m[1]);
        } elseif (preg_match("/^'((?:[^']|'')*)'\\s*:(?=\\s|\$)(.*)\$/", $text, $m)) {
            $key = str_replace("''", "'", $m[1]);
        } elseif (preg_match('/^([^\s\'"#&!|>%@`{}\[\],?-][^#]*?)\s*:(?=\s|$)(.*)$/', $text, $m)) {
            $key = $m[1];
        } else {
            return null;
        }

        if ($key === '<<') {
            throw new \Exception('Unsupported YAML in bitbucket-pipelines.yml: a merge key (<<) is used among the pipeline names.', 1);
        }

        $rest = trim($m[2]);

        return $key;
    }

    /**
     * Section keys must introduce an indented block, not an inline value.
     *
     * @throws \Exception
     */
    private static function assertBlockValue($rest, $key)
    {
        if ($rest === '') {
            return;
        }

        if ($rest[0] === '*') {
            throw new \Exception("Unsupported YAML in bitbucket-pipelines.yml: '{$key}' is an alias.", 1);
        }

        throw new \Exception("Unsupported YAML in bitbucket-pipelines.yml: '{$key}' uses flow style or an inline value.", 1);
    }

    /**
     * Removes a trailing comment, leaving "#" inside quotes alone.
     */
    private static function stripComment($line)
    {
        $quote = null;
        $length = strlen($line);

        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];

            if ($quote !== null) {
                if ($char === '\\' && $quote === '"') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '#' && ($i === 0 || ctype_space($line[$i - 1]))) {
                return substr($line, 0, $i);
            }
        }

        return $line;
    }
}
