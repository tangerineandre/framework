<?php
namespace Phidias\Core;

class Debug
{
    private static $enabled             = FALSE;
    private static $initialTimestamp    = FALSE;

    private static $messages            = array();
    private static $blockStack          = array();
    private static $stackDepth          = 0;

    public static function enable()
    {
        self::$initialTimestamp = microtime(true);
        self::$enabled          = TRUE;

        self::add('debugger enabled');
    }

    public static function disable()
    {
        self::$enabled = FALSE;
    }

    public static function isEnabled()
    {
        return self::$enabled;
    }

    public static function getInitialTimestamp()
    {
        return self::$initialTimestamp;
    }

    public static function startBlock($text, $type = NULL, $callbacks = NULL)
    {
        if ( !self::$enabled ) {
            return;
        }

        $message = new Debug_Message($text, $type, $callbacks);
        self::$blockStack[self::$stackDepth] = $message;

        if (self::$stackDepth) {
            self::$blockStack[self::$stackDepth-1]->messages[] = $message;
        } else {
            self::$messages[] = $message;
        }

        self::$stackDepth++;
    }

    public static function endBlock()
    {
        if (self::$stackDepth == 0) {
            return;
        }

        self::$blockStack[self::$stackDepth-1]->duration = microtime(true)-self::$blockStack[self::$stackDepth-1]->timestamp;
        self::$stackDepth--;
    }

    public static function collapseAll()
    {
        while (self::$stackDepth > 0) {
            self::endBlock();
        }
    }

    public static function add($text, $type = NULL, $callbacks = NULL)
    {
        if ( !self::$enabled ) {
            return;
        }

        $message = new Debug_Message($text, $type, $callbacks);

        if (self::$stackDepth) {
            self::$blockStack[self::$stackDepth-1]->messages[] = $message;
        } else {
            self::$messages[] = $message;
        }
    }

    public static function flush()
    {
        if (!self::$enabled) {
            return;
        }

        /* Close all unopened blocks */
        while (self::$stackDepth > 0) {
            self::endBlock();
        }

        $templateFileSource = NULL;
        $templateFile       = Route::template("phidias/debugger", $templateFileSource);

        if ($templateFile) {
            $view = new View( Environment::getPublicURL($templateFileSource) );
            $view->assign('messages',   self::$messages);
            $view->assign('peakMemory', memory_get_peak_usage());
            $view->assign('totalTime',  microtime(true) - self::$initialTimestamp);
            echo $view->fetch(Route::template("phidias/debugger"));
        } else {
            dump(self::$messages);
        }
    }

    public static function dump($var, $returnOutput = FALSE)
    {
        $varData = print_r($var, TRUE);

        if ($returnOutput) {
            return $varData;
        }

        echo '<pre>'.htmlentities($varData).'</pre>';
    }

    public static function dumpx($var)
    {
        self::dump($var);
        exit;
    }

}

class Debug_Message
{
    public $timestamp;
    public $text;
    public $type;
    public $duration;
    public $memory;
    public $file;
    public $line;

    public $messages;   //sub-messages

    public function __construct($text, $type = NULL, $callbacks = NULL)
    {
        $this->timestamp    = microtime(true);
        $this->text         = $text;
        $this->type         = $type;
        $this->duration     = NULL;
        $this->memory       = memory_get_usage();

        $this->messages = array();


        $trace = debug_backtrace();

        if ($callbacks === NULL) {
            if (isset($trace[1]) && ($trace[1]['function'] == 'add' || $trace[1]['function'] == 'startBlock') ) {
                $this->file = $trace[1]['file'];
                $this->line = $trace[1]['line'];
            }
        } else {
            foreach($callbacks as $callback) {
                $targetClass    = $callback[0];
                $targetMethod   = $callback[1];

                foreach($trace as $invocation) {
                    if (isset($invocation['class']) && $invocation['class'] == $targetClass && $invocation['function'] == $targetMethod) {
                        $this->file = $invocation['file'];
                        $this->line = $invocation['line'];
                    }
                }
            }
        }

    }

}