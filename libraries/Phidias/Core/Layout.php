<?php
namespace Phidias\Core;

class Layout
{
    private static $variables       = array();
    private static $blocks          = array();
    private static $current_block   = FALSE;

    private $URL;

    public static function set($variable, $value)
    {
        self::$variables[$variable] = $value;
    }

    public static function block($block)
    {
        self::$current_block = strtolower( $block );
        ob_start();
    }

    public static function endBlock()
    {
        if ( isset(self::$blocks[self::$current_block]) ) {
            self::$blocks[self::$current_block] .= ob_get_contents();
        } else {
            self::$blocks[self::$current_block] = ob_get_contents();
        }
        ob_end_clean();
    }


    /* Functions to be used within the layout */
    private function URL()
    {
        return $this->URL;
    }

    private function get($variable, $default_value = NULL)
    {
        return isset(self::$variables[$variable]) ? self::$variables[$variable] : $default_value;
    }

    private function output($block = NULL)
    {
        $block = ($block === NULL) ? 'OUTPUT' : strtolower( $block );

        if ( isset(self::$blocks[$block]) ) {
            return self::$blocks[$block];
        }
    }


    /* Execution */
    public function __construct($output = NULL, $URL = NULL)
    {
        self::$blocks['OUTPUT'] = $output;
        $this->URL = $URL;
    }

    public function render($template)
    {
        ob_start();
        Debug::startBlock("including layout file '$template'", 'include');
        include $template;
        Debug::endBlock();
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }
}