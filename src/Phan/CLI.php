<?php declare(strict_types=1);
namespace Phan;

use \Phan\Config;
use \Phan\Issue;
use \Phan\Log;

class CLI {

    /**
     * @var string[]
     * The set of file names to analyze
     */
    private $file_list = [];

    /**
     * Create and read command line arguments, configuring
     * \Phan\Config as a side effect.
     */
    public function __construct() {

        global $argv;

        // file_put_contents('/tmp/file', implode("\n", $argv));

        // Parse command line args
        // still available: g,j,k,n,t,u,v,w,z
        $opts = getopt(
            "f:m:o:c:aeqbrpid:s:3:y:l:xh::", [
                'fileset:',
                'output-mode:',
                'output:',
                'parent-constructor-required:',
                'expanded-dependency-list',
                'dump-ast',
                'quick',
                'backward-compatibility-checks',
                'reanalyze-file-list',
                'progress-bar',
                'ignore-undeclared',
                'project-root-directory:',
                'state-file:',
                'exclude-directory-list:',
                'minimum-severity:',
                'directory:',
                'dead-code-detection',
                'help',
            ]
        );

        // Determine the root directory of the project from which
        // we root all relative paths passed in as args
        Config::get()->setProjectRootDirectory(
            $opts['d'] ?? getcwd()
        );

        // Now that we have a root directory, attempt to read a
        // configuration file `.phan/config.php` if it exists
        $this->maybeReadConfigFile();

        foreach($opts ?? [] as $key => $value) {
            switch($key) {
            case 'h':
            case 'help':
                $this->usage();
                break;
            case 'f':
            case 'fileset':
                $file_list = is_array($value) ? $value : [$value];
                foreach ($file_list as $file_name) {
                    if(is_file($file_name) && is_readable($file_name)) {
                        $this->file_list = array_merge(
                            $this->file_list,
                            file($file_name, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)
                        );
                    } else {
                        error_log("Unable to read file $file_name");
                    }
                }
                break;
            case 'l':
            case 'directory':
                $directory_list = is_array($value) ? $value : [$value];
                foreach ($directory_list as $directory_name) {
                    $this->file_list = array_merge(
                        $this->file_list,
                        $this->directoryNameToFileList(
                            $directory_name
                        )
                    );
                }
                break;
            case 'm':
            case 'output-mode':
                if(!in_array($value, ['text', 'codeclimate'])) {
                    $this->usage("Unknown output mode: $value");
                }
                Log::setOutputMode($value);
                break;
            case 'c':
            case 'parent-constructor-required':
                Config::get()->parent_constructor_required =
                    explode(',', $value);
                break;
            case 'q':
            case 'quick':
                Config::get()->quick_mode = true;
                break;
            case 'b':
            case 'backward-compatibility-checks':
                Config::get()->backward_compatibility_checks = true;
                break;
            case 'p':
            case 'progress-bar':
                Config::get()->progress_bar = true;
                break;
            case 'a':
            case 'dump-ast':
                Config::get()->dump_ast = true;
                break;
            case 'e':
            case 'expanded-dependency-list':
                Config::get()->expanded_dependency_list = true;
                break;
            case 'o':
            case 'output':
                Log::setFilename($value);
                break;
            case 'i':
            case 'ignore-undeclared':
                Log::setOutputMask(Log::getOutputMask()^Issue::CATEGORY_UNDEFINED);
                break;
            case '3':
            case 'exclude-directory-list':
                Config::get()->exclude_analysis_directory_list =
                    explode(',', $value);
                break;
            case 's':
            case 'state-file':
                Config::get()->stored_state_file_path = $value;
                break;
            case 'r':
            case 'reanalyze-file-list':
                Config::get()->reanalyze_file_list = true;
                break;
            case 'y':
            case 'minimum-severity':
                Config::get()->minimum_severity = $value;
                break;
            case 'd':
            case 'project-root-directory':
                // We handle this flag before parsing options so
                // that we can get the project root directory to
                // base other config flags values on
                break;
            case 'x':
            case 'dead-code-detection':
                Config::get()->dead_code_detection = true;
                break;
            default:
                $this->usage("Unknown option '-$key'"); break;
            }
        }

        $pruneargv = array();
        foreach($opts ?? [] as $opt => $value) {
            foreach($argv as $key => $chunk) {
                $regex = '/^'. (isset($opt[1]) ? '--' : '-') . $opt . '/';

                if ($chunk == $value
                    && $argv[$key-1][0] == '-'
                    || preg_match($regex, $chunk)
                ) {
                    array_push($pruneargv, $key);
                }
            }
        }

        while($key = array_pop($pruneargv)) {
            unset($argv[$key]);
        }

        foreach($argv as $arg) if($arg[0]=='-') {
            $this->usage("Unknown option '{$arg}'");
        }

        // Merge in any remaining args on the CLI
        $this->file_list = array_merge(
            $this->file_list,
            array_slice($argv,1)
        );

        // Merge in any files given in the config
        $this->file_list = array_merge(
            $this->file_list, Config::get()->file_list
        );

        // Merge in any directories given in the config
        foreach (Config::get()->directory_list as $directory_name) {
            $this->file_list = array_merge(
                $this->file_list,
                $this->directoryNameToFileList($directory_name)
            );
        }
    }

    /**
     * @return string[]
     * Get the set of files to analyze
     */
    public function getFileList() : array {
        return $this->file_list;
    }

    private function usage(string $msg='') {
        global $argv;

        if(!empty($msg)) {
            echo "$msg\n";
        }

        echo <<<EOB
Usage: {$argv[0]} [options] [files...]
 -f, --fileset <filename>
  A file containing a list of PHP files to be analyzed

 -l, --directory <directory>
  A directory to recursively read PHP files from to analyze

 -3, --exclude-directory-list <dir_list>
  A comma-separated list of directories for which any files
  therein should be parsed but not analyzed.

 -s, --state-file <filename>
  Save state to the given file and read from it to speed up
  future executions

 -r, --reanalyze-file-list
  Force a re-analysis of any files passed in even if they haven't
  changed since the last analysis

 -d, --project-root-directory
  Hunt for a directory named .phan in the current or parent
  directory and read configuration file config.php from that
  path.

 -m <mode>, --output-mode
  Output mode: text, codeclimate

 -o, --output <filename>
  Output filename

 -p, --progress-bar
  Show progress bar

 -a, --dump-ast
  Emit an AST for each file rather than analyze

 -e, --expanded-dependency-list
  Emit an expanded list of files for the files to be analyzed
  that includes all files that should be re-analyzed if the
  passed files have changed. Doing so inhibits analysis or
  parsing.

 -q, --quick
  Quick mode - doesn't recurse into all function calls

 -b, --backward-compatibility-checks
  Check for potential PHP 5 -> PHP 7 BC issues

 -i, --ignore-undeclared
 Ignore undeclared functions and classes

 -y, --minimum-severity <level in {0,5,10}>
  Minimum severity level (low=0, normal=5, critical=10) to report.
  Defaults to 0.

 -c, --parent-constructor-required
  Comma-separated list of classes that require
  parent::__construct() to be called

 -x, --dead-code-detection
  Emit issues for classes, methods, functions, constants and
  properties that are probably never referenced and can
  possibly be removed.

 -h,--help
  This help information

EOB;
        exit;
    }

    /**
     * @param string $directory_name
     * The name of a directory to scan for files ending in `.php`.
     *
     * @return string[]
     * A list of PHP files in the given directory
     */
    private function directoryNameToFileList(
        string $directory_name
    ) : array {
        $file_list = [];

        try {
            $iterator = new \RegexIterator(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($directory_name)
                ),
                '/^.+\.php$/i',
                \RecursiveRegexIterator::GET_MATCH
            );

            foreach (array_keys(iterator_to_array($iterator)) as $file_name) {
                if(is_file($file_name) && is_readable($file_name)) {
                    $file_list[] = $file_name;
                } else {
                    error_log("Unable to read file $file_name");
                }
            }
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }

        return $file_list;
    }

    /**
     * Update a progress bar on the screen
     *
     * @param string $msg
     * A short message to display with the progress
     * meter
     *
     * @param float $p
     * The percentage to display
     *
     * @param float $sample_rate
     * How frequently we should update the progress
     * bar, randomly sampled
     *
     * @return null
     */
    public static function progress(
        string $msg,
        float $p
    ) {
        // Bound the percentage to [0, 1]
        $p = min(max($p, 0.0), 1.0);

        if (!Config::get()->progress_bar || Config::get()->dump_ast) {
            return;
        }

        // Don't update every time when we're moving
        // super fast
        if ($p < 1.0
            && rand(0, 1000) > (1000 * Config::get()->progress_bar_sample_rate
        )) {
            return;
        }

        $memory = memory_get_usage()/1024/1024;
        $peak = memory_get_peak_usage()/1024/1024;

        $padded_message = str_pad ($msg, 10, ' ', STR_PAD_LEFT);

        fwrite(STDERR, "$padded_message ");
        $current = (int)($p * 60);
        $rest = max(60 - $current, 0);
        fwrite(STDERR, str_repeat("\u{2588}", $current));
        fwrite(STDERR, str_repeat("\u{2591}", $rest));
        fwrite(STDERR, " " . sprintf("% 3d", (int)(100*$p)) . "%");
        fwrite(STDERR, sprintf(' %0.2dMB/%0.2dMB', $memory, $peak) . "\r");
    }

    /**
     * Look for a .phan/config file up to a few directories
     * up the hierarchy and apply anything in there to
     * the configuration.
     */
    private function maybeReadConfigFile() {

        // If the file doesn't exist here, try a directory up
        $config_file_name =
            implode(DIRECTORY_SEPARATOR, [
                Config::get()->getProjectRootDirectory(),
                '.phan',
                'config.php'
            ]);

        // Totally cool if the file isn't there
        if (!file_exists($config_file_name)) {
            return;
        }

        // Read the configuration file
        $config = require($config_file_name);

        // Write each value to the config
        foreach ($config as $key => $value) {
            Config::get()->__set($key, $value);
        }
    }

}
