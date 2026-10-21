<?php

namespace PhpMyAdmin\SqlParser\Tools;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Used for context generation.
 *
 * @category   Contexts
 *
 * @license    https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */
class ContextGenerator
{
    public static $LABELS_FLAGS = array(
        '(R)' => 2,
        '(D)' => 8,
        '(K)' => 16,
        '(F)' => 32
    );

    public static $LINKS = array(
        'MySql50000' => 'https://dev.mysql.com/doc/refman/5.0/en/keywords.html',
        'MySql50100' => 'https://dev.mysql.com/doc/refman/5.1/en/keywords.html',
        'MySql50500' => 'https://dev.mysql.com/doc/refman/5.5/en/keywords.html',
        'MySql50600' => 'https://dev.mysql.com/doc/refman/5.6/en/keywords.html',
        'MySql50700' => 'https://dev.mysql.com/doc/refman/5.7/en/keywords.html',
        'MySql80000' => 'https://dev.mysql.com/doc/refman/8.0/en/keywords.html',
        'MariaDb100000' => 'https://mariadb.com/kb/en/the-mariadb-library/reserved-words/',
        'MariaDb100100' => 'https://mariadb.com/kb/en/the-mariadb-library/reserved-words/',
        'MariaDb100200' => 'https://mariadb.com/kb/en/the-mariadb-library/reserved-words/',
        'MariaDb100300' => 'https://mariadb.com/kb/en/the-mariadb-library/reserved-words/'
    );

    const TEMPLATE =
        '<?php' . "\n" .
        '' . "\n" .
        '/**' . "\n" .
        ' * Context for %1$s.' . "\n" .
        ' *' . "\n" .
        ' * This file was auto-generated from tools/contexts/*.txt.' . "\n" .
        ' * Use tools/run_generators.sh for update.' . "\n" .
        ' *' . "\n" .
        ' * @see %3$s' . "\n" .
        ' */' . "\n" .
        '' . "\n" .
        'namespace PhpMyAdmin\\SqlParser\\Contexts;' . "\n" .
        '' . "\n" .
        'use PhpMyAdmin\\SqlParser\\Context;' . "\n" .
        'use PhpMyAdmin\\SqlParser\\Token;' . "\n" .
        '' . "\n" .
        '/**' . "\n" .
        ' * Context for %1$s.' . "\n" .
        ' *' . "\n" .
        ' * @category   Contexts' . "\n" .
        ' *' . "\n" .
        ' * @license    https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+' . "\n" .
        ' */' . "\n" .
        'class %2$s extends Context' . "\n" .
        '{' . "\n" .
        '    /**' . "\n" .
        '     * List of keywords.' . "\n" .
        '     *' . "\n" .
        '     * The value associated to each keyword represents its flags.' . "\n" .
        '     *' . "\n" .
        '     * @see Token::FLAG_KEYWORD_RESERVED Token::FLAG_KEYWORD_COMPOSED' . "\n" .
        '     *      Token::FLAG_KEYWORD_DATA_TYPE Token::FLAG_KEYWORD_KEY' . "\n" .
        '     *      Token::FLAG_KEYWORD_FUNCTION' . "\n" .
        '     *' . "\n" .
        '     * @var array' . "\n" .
        '     */' . "\n" .
        '    public static $KEYWORDS = array(' . "\n" .
        '%4$s' .
        '    );' . "\n" .
        '}' . "\n";

    public static function sortWords(array &$arr)
    {
        ksort($arr);
        foreach ($arr as &$wordsByLen) {
            ksort($wordsByLen);
            foreach ($wordsByLen as &$words) {
                sort($words, SORT_STRING);
            }
        }

        return $arr;
    }

    public static function readWords(array $files)
    {
        $words = array();
        foreach ($files as $file) {
            $words = array_merge($words, file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        }

        $types = array();

        for ($i = 0, $count = count($words); $i !== $count; ++$i) {
            $type = 1;
            $value = trim($words[$i]);

            foreach (static::$LABELS_FLAGS as $label => $flags) {
                if (strstr($value, $label) !== false) {
                    $type |= $flags;
                    $value = trim(str_replace($label, '', $value));
                }
            }

            if (strstr($value, ' ') !== false) {
                $type |= 2;
                $type |= 4;
            }

            $len = strlen($words[$i]);
            if ($len === 0) {
                continue;
            }

            $value = strtoupper($value);
            if (! isset($types[$value])) {
                $types[$value] = $type;
            } else {
                $types[$value] |= $type;
            }
        }

        $ret = array();
        foreach ($types as $word => $type) {
            $len = strlen($word);
            if (! isset($ret[$type])) {
                $ret[$type] = array();
            }
            if (! isset($ret[$type][$len])) {
                $ret[$type][$len] = array();
            }
            $ret[$type][$len][] = $word;
        }

        return static::sortWords($ret);
    }

    public static function printWords($words, $spaces = 8, $line = 140)
    {
        $typesCount = count($words);
        $ret = '';
        $j = 0;

        foreach ($words as $type => $wordsByType) {
            foreach ($wordsByType as $len => $wordsByLen) {
                $count = round(($line - $spaces) / ($len + 9));
                $i = 0;

                foreach ($wordsByLen as $word) {
                    if ($i === 0) {
                        $ret .= str_repeat(' ', $spaces);
                    }
                    $ret .= sprintf('\'%s\' => %s, ', $word, $type);
                    if (++$i === $count || ++$i > $count) {
                        $ret .= "\n";
                        $i = 0;
                    }
                }

                if ($i !== 0) {
                    $ret .= "\n";
                }
            }

            if (++$j < $typesCount) {
                $ret .= "\n";
            }
        }

        return str_replace(" \n", "\n", $ret);
    }

    public static function generate($options)
    {
        if (isset($options['keywords'])) {
            $options['keywords'] = static::printWords($options['keywords']);
        }

        return sprintf(
            static::TEMPLATE,
            $options['name'],
            $options['class'],
            $options['link'],
            $options['keywords']
        );
    }

    public static function formatName($name)
    {
        $parts = array();
        if (preg_match('/([^[0-9]*)([0-9]*)/', $name, $parts) === false) {
            return $name;
        }

        $base = $parts[1];
        switch ($base) {
            case 'MySql':
                $base = 'MySQL';
                break;
            case 'MariaDb':
                $base = 'MariaDB';
                break;
        }

        $ver_str = $parts[2];
        if (strlen($ver_str) % 2 === 1) {
            $ver_str = '0' . $ver_str;
        }
        $version = array_map('intval', str_split($ver_str, 2));
        if ($version[count($version) - 1] === 0) {
            $version = array_slice($version, 0, count($version) - 1);
        }
        return $base . ' ' . implode('.', $version);
    }

    public static function build($input, $output)
    {
        $directory = dirname($input) . '/';

        $file = basename($input);

        $name = substr($file, 0, -4);

        $class = 'Context' . $name;

        $formattedName = static::formatName($name);

        file_put_contents(
            $output . '/' . $class . '.php',
            static::generate(
                array(
                    'name' => $formattedName,
                    'class' => $class,
                    'link' => static::$LINKS[$name],
                    'keywords' => static::readWords(
                        array(
                            $directory . '_common.txt',
                            $directory . '_functions' . $file,
                            $directory . $file
                        )
                    )
                )
            )
        );
    }

    public static function buildAll($input, $output)
    {
        $files = scandir($input);

        foreach ($files as $file) {
            if (($file[0] === '.') || ($file[0] === '_')) {
                continue;
            }

            sprintf("Building context for %s...\n", $file);
            static::build($input . '/' . $file, $output);
        }
    }
}

if (count($argv) >= 3) {
    $input = rtrim($argv[1], '/');
    $output = rtrim($argv[2], '/');

    if (! is_dir($input)) {
        throw new \Exception('The input directory does not exist.');
    } elseif (! is_dir($output)) {
        throw new \Exception('The output directory does not exist.');
    }

    ContextGenerator::buildAll($input, $output);
}
