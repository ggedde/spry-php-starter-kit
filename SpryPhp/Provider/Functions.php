<?php declare(strict_types=1);
/**
 * Global Functions file
 */

namespace SpryPhp\Provider;

use Exception;
use SpryPhp\Provider\Alerts;
use SpryPhp\Provider\Request;
use SpryPhp\Provider\Route;

/**
 * Function Class
 * Provides general functions
 */
class Functions
{
    /**
     * Prettify Error Messages and Stack Traces.
     *
     * @param string|array $errors
     * @param string       $trace
     *
     * @uses APP_DEBUG
     * @uses APP_PATH
     *
     * @return void
     */
    public static function displayError(mixed $errors, string $trace = ''): void
    {
        echo '<div style="padding: .5em;"><div style="padding: .8em 1.6em; background-color: light-dark(#eee, #333); border: 1px solid #888; border-radius: .5em; line-height: 1.4; overflow: auto;"><pre style="white-space: pre-wrap;">';

        ob_start();
        var_dump($errors);
        $data = ob_get_contents();
        ob_end_clean();


        // $data = str_replace("]=>\n", '] => ', $data);
        $data = preg_replace('/]=>\n[\ ]*/', '] => ', $data);
        $data = preg_replace('/bool\((false|true)\)/', '{{bool:}} $1', $data);
        $data = preg_replace('/int\(([0-9]*)\)/', '{{int:}} $1', $data);
        $data = preg_replace('/float\(([0-9\.]*)\)/', '{{float:}} $1', $data);
        $data = preg_replace('/string\(([0-9]*)\).*\"(.*)\"/', '{{str($1):}} $2', $data);
        $data = preg_replace('/array\(([0-9]*)\).*\{/', '{{array($1):}} {', $data);
        $data = preg_replace('/object\((.*)\).*\#.*{/', '{{object:}} {{{$1}}} {', $data);
        $data = preg_replace('/=> NULL/', '=> {{NULL}}', $data);
        $data = preg_replace('/^NULL/', '{{NULL}}', $data);
        $data = preg_replace('/{\n[\ ]*}/', '{}', $data);

        $data = str_replace('{{{', '<span style="color: #197239">', $data);
        $data = str_replace('{{', '<span style="color: #80a0a7">', $data);
        $data = str_replace(['}}}', '}}'], '</span>', $data);
        $data = str_replace(' => ', '<span style="color: #888"> => </span>', $data);

        if (defined('APP_PATH')) {
            $data = str_replace(constant('APP_PATH'), '', $data);
        }

        echo $data;

        if (!$trace) {
            ob_start();
            debug_print_backtrace(0);
            $trace = ob_get_contents();
            ob_end_clean();
        }

        if ($trace && defined('APP_DEBUG')) {
            echo "\n";
            echo '<span style="color:#794111;">';
            if (defined('APP_PATH')) {
                echo str_replace(constant('APP_PATH'), '', $trace);
            } else {
                echo $trace;
            }
            echo '</span>';
        }
        echo '</pre></div></div>';
    }

    /**
     * Basic Dump function
     *
     * @param mixed $value     Single Value
     * @param mixed ...$values Additional Values
     *
     * @return void
     */
    public static function d(mixed $value, mixed ...$values): void
    {
        if (!empty($values)) {
            $value = [
                $value,
                ...$values,
            ];
        }

        $traceArray = debug_backtrace(0, 1);

        ob_start();
        debug_print_backtrace(0);
        $trace = ob_get_contents();
        ob_end_clean();

        self::displayError($value, '<span style="color: #006499">in '.$traceArray[0]['file'].':'.$traceArray[0]['line']."</span>\n\n".$trace);
    }

    /**
     * Basic Die and Dump function
     *
     * @param mixed $value     Single Value
     * @param mixed ...$values Additional Values
     *
     * @return void
     */
    public static function dd(mixed $value, mixed ...$values): void
    {
        if (!empty($values)) {
            $value = [
                $value,
                ...$values,
            ];
        }

        $traceArray = debug_backtrace(0, 1);

        ob_start();
        debug_print_backtrace(0);
        $trace = ob_get_contents();
        ob_end_clean();

        self::displayError($value, '<span style="color: #006499">in '.$traceArray[0]['file'].':'.$traceArray[0]['line']."</span>\n\n".$trace);
        exit;
    }

    /**
     * Prettify Exceptions Messages and Stack Traces.
     *
     * @return void
     */
    public static function formatExceptions(): void
    {
        set_exception_handler(function (\Throwable $exception) {
            self::displayError('<b>Uncaught Exception</b>: '.$exception->getMessage(), '<span style="color: #006499">in '.$exception->getFile().':'.$exception->getLine()."</span>\n\n".$exception->getTraceAsString());
        });
    }

    /**
     * Initiate the Error Reporting based on constant "APP_DEBUG"
     *
     * @uses APP_DEBUG
     *
     * @throws Exception
     *
     * @return void
     */
    public static function setDebug(): void
    {
        if (!defined('APP_DEBUG')) {
            throw new Exception("SpryPHP: APP_DEBUG is not defined.");
        }

        if (!empty(constant('APP_DEBUG'))) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        }
    }

    /**
     * Verify that the Host is correct and if not, then redirect to correct Host.
     *
     * @uses APP_HOST
     * @uses APP_HTTPS
     *
     * @return void
     */
    public static function forceHost(): void
    {
       // Check Host and Protocol
        $isWrongHost = defined('APP_HOST') && !empty(constant('APP_HOST')) && !empty($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== constant('APP_HOST');
        $isWrongProtocol = defined('APP_HTTPS') && !empty(constant('APP_HTTPS')) && !((!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') || intval($_SERVER['SERVER_PORT']) === 443 || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'));
        if ($isWrongHost || $isWrongProtocol) {
            header('Location: http'.(defined('APP_HTTPS') && !empty(constant('APP_HTTPS')) ? 's' : '').'://'.(defined('APP_HOST') && !empty(constant('APP_HOST')) ? constant('APP_HOST') : $_SERVER['HTTP_HOST']), true, 302);
            exit;
        }
    }

    /**
     * Load Env File
     *
     * @param string $envFile - Absolute path to file.
     *
     * @throws Exception
     *
     * @return void
     */
    public static function loadEnvFile(string $envFile): void
    {
        // Check if file exists and if not, then log an error.
        if (!file_exists($envFile)) {
            throw new Exception("SpryPHP: Missing ENV File ('.$envFile.')");
        }

        // Load Env File into env vars.
        foreach (parse_ini_file($envFile) as $envVarKey => $envVarValue) {
            putenv($envVarKey.'='.$envVarValue);
        }
    }

    /**
     * Logout of Session and abort current action with a Message.
     *
     * @param string $error
     *
     * @uses APP_URI_LOGIN
     * @uses APP_URI_LOGOUT
     *
     * @throws Exception
     *
     * @return void
     */
    public static function abort(string $error): void
    {
        if ($error) {
            Alerts::set('error', $error);
            if (!defined('APP_URI_LOGIN')) {
                throw new Exception("SpryPHP: APP_URI_LOGIN is not defined");
            }

            if (!defined('APP_URI_LOGOUT')) {
                throw new Exception("SpryPHP: APP_URI_LOGOUT is not defined");
            }

            if (!in_array(Request::$path, [constant('APP_URI_LOGIN'), constant('APP_URI_LOGOUT')], true)) {
                Route::goTo(constant('APP_URI_LOGIN'));
            }
        }
    }

    /**
     * Safe Value
     *
     * @param string $value
     *
     * @return string
     */
    public static function esc(string $value): string
    {
        return addslashes(str_replace(['"', '&#039;'], ['&#34;', "'"], htmlspecialchars(stripslashes(strip_tags($value)), ENT_NOQUOTES, "UTF-8", false)));
    }

    /**
     * Convert Value for HTML Attribute
     *
     * @param mixed $value
     *
     * @return string
     */
    public static function attr(mixed $value): string
    {
        return str_replace("'", "&#39;", stripslashes(strval($value)));
    }

    /**
     * Sanitize String
     *
     * @param string $string
     * @param string $space  Character for spaces.
     *
     * @return string
     */
    public static function sanitizeString(string $string, string $space = '_'): string
    {
        $string = preg_replace('/[^a-z0-9\_]/', '', trim(str_replace([' ', '-'], '_', trim(strtolower($string))), '_'));
        $string = preg_replace('/[_]{2,}/', '_', $string);

        return str_replace('_', $space, $string);
    }

    /**
     * Format Title
     *
     * @param string $title
     *
     * @return string
     */
    public static function formatTitle(string $title): string
    {
        return ucwords(self::sanitizeString(self::convertToSnakeCase($title), ' '));
    }

    /**
     * Convert to CamelCase
     *
     * @param string $data
     *
     * @return string
     */
    public static function convertToCamelCase(string $data): string
    {
        if (!is_string($data)) {
            return $data;
        }

        $str = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $data)));
        $str[0] = strtolower($str[0]);

        return $str;
    }

    /**
     * Converts Keys to SnakeCase
     *
     * @param string $data
     *
     * @access public
     *
     * @return string
     */
    public static function convertToSnakeCase(string $data): string
    {
        if (!is_string($data)) {
            return $data;
        }

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $data));
    }

    /**
     * Create a New Ordered UUID that is UUIDv7 compatible
     *
     * @return string
     */
    public static function newUuid(): string
    {
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(dechex(intval(number_format(floatval(time().explode(' ', microtime())[0]), 6, '', ''))).bin2hex(random_bytes(9)), 4));
    }

    /**
     * Checks the App for issues
     *
     * @throws Exception
     *
     * @return void
     */
    public static function checkAppIntegrity(): void
    {
        if (defined('APP_AUTH_KEY') && constant('APP_AUTH_KEY') === '__AUTH_KEY__') {
            throw new Exception("SpryPHP: Please update APP_AUTH_KEY to a secure and unique value.");
        }

        if (defined('APP_AUTH_PASSWORD') && constant('APP_AUTH_PASSWORD') === '__AUTH_PASSWORD__') {
            throw new Exception("SpryPHP: Please update APP_AUTH_PASSWORD to a secure and unique value.");
        }
    }

    /**
     * List of States and abbreviations.
     *
     * @return array
     */
    public static function getStates(): array
    {
        return array(
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AS' => 'American Samoa',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DC' => 'District of Columbia',
            'DE' => 'Delaware',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'GU' => 'Guam',
            'HI' => 'Hawaii',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'IA' => 'Iowa',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'ME' => 'Maine',
            'MD' => 'Maryland',
            'MA' => 'Massachusetts',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MS' => 'Mississippi',
            'MO' => 'Missouri',
            'MT' => 'Montana',
            'NE' => 'Nebraska',
            'NV' => 'Nevada',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NY' => 'New York',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'PR' => 'Puerto Rico',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VI' => 'Virgin Islands',
            'VT' => 'Vermont',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming',
        );
    }
}
