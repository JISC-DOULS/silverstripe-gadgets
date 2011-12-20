<?php
/**
 * Lib of functions to help output dynamic gadget xml
 */

class gadget{

    /**
     * Replace known tokens in gadget xml
     * %%SERVER%% - base url to this module directory
     * %%DIR%% - url to module folder
     * %%JS%% - Gadget JS lib for connecting to SS webservices
     * @param string $string
     * @return string
     */
    public static function replace_tokens($string) {
        $string = str_ireplace('%%JS%%', self::replace_js(), $string);
        $string = str_ireplace('%%SERVER%%', self::replace_server(), $string);
        $string = str_ireplace('%%DIR%%', self::replace_dir(), $string);
        return $string;
    }

    public static function output($string) {
        header('Content-Type: text/xml; charset=utf-8');
        echo $string;
        exit;
    }

    /**
     * Return server address so dynamic absolute urls can be used
     */
    private static function replace_server() {
        $urlbase = substr($_SERVER['PHP_SELF'], 0, -strlen('gadgets/gadget.php'));
		$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? "https" : "http";
		return "$protocol://". $_SERVER['HTTP_HOST'] . $urlbase;
    }

    private static function replace_dir() {
        $urlbase = substr($_SERVER['PHP_SELF'], 0, -strlen('gadget.php'));
		$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? "https" : "http";
		return "$protocol://". $_SERVER['HTTP_HOST'] . $urlbase;
    }

    private static function replace_js() {
        return '<script type="text/javascript" src="%%SERVER%%/snapp/code/javascript/lib.js"></script>';
    }
}
