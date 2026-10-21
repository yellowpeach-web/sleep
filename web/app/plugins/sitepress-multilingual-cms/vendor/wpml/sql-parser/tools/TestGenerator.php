<?php

namespace PhpMyAdmin\SqlParser\Tools;

require_once '../vendor/autoload.php';

use PhpMyAdmin\SqlParser\Context;
use PhpMyAdmin\SqlParser\Lexer;
use PhpMyAdmin\SqlParser\Parser;

/**
 * Used for test generation.
 *
 * @category   Tests
 *
 * @license    https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */
class TestGenerator
{
    public static function generate($query, $type = 'parser')
    {
        $lexer = new Lexer($query);

        $parser = ($type === 'parser') ? new Parser($lexer->list) : null;

        $lexerErrors = array();

        $parserErrors = array();


        if (! empty($lexer->errors)) {
            foreach ($lexer->errors as $err) {
                $lexerErrors[] = array(
                    $err->getMessage(),
                    $err->ch,
                    $err->pos,
                    $err->getCode()
                );
            }
            $lexer->errors = array();
        }

        if (! empty($parser->errors)) {
            foreach ($parser->errors as $err) {
                $parserErrors[] = array(
                    $err->getMessage(),
                    $err->token,
                    $err->getCode()
                );
            }
            $parser->errors = array();
        }

        return array(
            'query' => $query,
            'lexer' => $lexer,
            'parser' => $parser,
            'errors' => array(
                'lexer' => $lexerErrors,
                'parser' => $parserErrors,
            )
        );
    }

    public static function build($type, $input, $output, $debug = null, $ansi = false)
    {
        if (! in_array($type, array('lexer', 'parser'))) {
            throw new \Exception('Unknown test type (expected `lexer` or `parser`).');
        }

        $query = file_get_contents($input);

        if (empty($query)) {
            throw new \Exception('No input query specified.');
        }

        if ($ansi === true) {
            Context::setMode('ANSI_QUOTES');
        }

        $test = static::generate($query, $type);

        Context::setMode();

        file_put_contents($output, serialize($test));

        if (! empty($debug)) {
            file_put_contents($debug, print_r($test, true));
        }
    }

    public static function buildAll($input, $output, $debug = null)
    {
        $files = scandir($input);

        foreach ($files as $file) {
            if (($file === '.') || ($file === '..')) {
                continue;
            }

            $inputFile = $input . '/' . $file;
            $outputFile = $output . '/' . $file;
            $debugFile = ($debug !== null) ? $debug . '/' . $file : null;

            if (is_dir($inputFile)) {
                if (! is_dir($outputFile)) {
                    mkdir($outputFile);
                }
                if (($debug !== null) && (! is_dir($debugFile))) {
                    mkdir($debugFile);
                }

                static::buildAll($inputFile, $outputFile, $debugFile);
            } elseif (substr($inputFile, -3) === '.in') {
                $outputFile = substr($outputFile, 0, -3) . '.out';
                if ($debug !== null) {
                    $debugFile = substr($debugFile, 0, -3) . '.debug';
                }

                if (! file_exists($outputFile)) {
                    sprintf("Building test for %s...\n", $inputFile);
                    static::build(
                        strpos($inputFile, 'lex') !== false ? 'lexer' : 'parser',
                        $inputFile,
                        $outputFile,
                        $debugFile,
                        strpos($inputFile, 'ansi') !== false
                    );
                } else {
                    sprintf("Test for %s already built!\n", $inputFile);
                }
            }
        }
    }
}

if (count($argv) >= 3) {
    $input = rtrim($argv[1], '/');
    $output = rtrim($argv[2], '/');
    $debug = empty($argv[3]) ? null : rtrim($argv[3], '/');

    if (! is_dir($input)) {
        throw new \Exception('The input directory does not exist.');
    } elseif (! is_dir($output)) {
        throw new \Exception('The output directory does not exist.');
    } elseif (($debug !== null) && (! is_dir($debug))) {
        throw new \Exception('The debug directory does not exist.');
    }

    TestGenerator::buildAll($input, $output, $debug);
}
