<?php
    namespace Core;

    class Log
    {
        /**
         * Config to determine if log type should be writen to the file.
         *
         * @var array
         */
        private $canWrite = array();

        /**
         * Config to determine if log type should be display to the user browser or not.
         *
         * @var array
         */
        private $canDisplay = array();

        /**
         * Config to determine if log type should generate backtrace or not
         *
         * @var array
         */
        private $canTrace = array();

        /**
         * Resource for Log File handler
         *
         * @var Resource
         */
        private $fileHandler = null;

        /**
         * Load configuration and open log file
         */
        public function __construct()
        {
            $this->loadConfig();
            $this->open();
        }

        /**
         * Load log configuration
         */
        private function loadConfig()
        {
            $config =& loadClass('Config', 'Core');
            $cfg = $config->setDefault("Log", [
                'info' => true,
                'warning' => true,
                'error'=>true,
                'infoDisplay' => false,
                'warningDisplay' => true,
                'errorDisplay'=>true,
                'infoTrace'=>true,
                'warningTrace'=>true,
                'errorTrace'=>true
            ]);

            $this->canWrite['info']    = $cfg['info'];
            $this->canWrite['warning'] = $cfg['warning'];
            $this->canWrite['error']   = $cfg['error'];

            $this->canDisplay['info']    = $cfg['infoDisplay'];
            $this->canDisplay['warning'] = $cfg['warningDisplay'];
            $this->canDisplay['error']   = $cfg['errorDisplay'];

            $this->canTrace['info']    = $cfg['infoTrace'];
            $this->canTrace['warning'] = $cfg['warningTrace'];
            $this->canTrace['error']   = $cfg['errorTrace'];
        }

        /**
         * Open log file. Log name is based on y-m-d date format with extension of log.
         * Will create if not exists.
         */
        private function open()
        {
            $this->fileHandler = fopen(APP_PATH.'log/'.date('y-m-d').'.log', "a+");
            $this->write('------------------------- [New User Request] -------------------------');
        }

        /**
         * Write message to the log file.
         *
         * @param  string $message Message to be writen
         */
        private function write($message)
        {
            fwrite($this->fileHandler, '['.date('H:i:s').'] '.$message.PHP_EOL);
        }

        /**
         * Write message to be displayed on the user browser
         *
         * @param  string $message Message to be displayed
         * @param  string $prefix  Message prefix (will be bold)
         * @param  string $suffix  Message prefix (will be bold)
         */
        public function display($message, $prefix='', $suffix='')
        {
            echo ($prefix!==''?"<b>$prefix: </b>":'') . $message . ($suffix!==''?"<b>$suffix: </b>":'') . '<br />';
        }

        /**
         * Log message info
         *
         * @param  string $message Info message
         * @param  int    $depth   Max backtrace depth to be written to log file
         * @param  int    $start   Skip the start of the backtrace (don't need to trace log call)
         */
        public function info($message, $depth=1, $start=1)
        {
            $trace = "";
            if ($this->canTrace['info'])
            {
                $trace = '['.$this->getBacktrace($start, $depth).']';
            }
            if ($this->canWrite['info'])
            {
                $this->write("$message $trace");
            }
            if ($this->canDisplay['info'])
            {
                $this->display($message, 'Info', $trace);
            }
        }

        /**
         * Log message warning
         *
         * @param  string $message Warning message
         * @param  int    $depth   Max backtrace depth to be written to log file
         * @param  int    $start   Skip the start of the backtrace (don't need to trace log call)
         */
        public function warning($message, $depth=1, $start=1)
        {
            $trace = "";
            if ($this->canTrace['info'])
            {
                $trace = '['.$this->getBacktrace($start, $depth).']';
            }
            if ($this->canWrite['warning'])
            {
                $this->write("$message $trace");
            }
            if ($this->canDisplay['warning'])
            {
                $this->display($message, 'Warning', $trace);
            }
        }

        /**
         * Log message error
         *
         * @param  string $message Error message
         * @param  int    $depth   Max backtrace depth to be written to log file
         * @param  int    $start   Skip the start of the backtrace (don't need to trace log call)
         */
        public function error($message, $depth=1, $start=1)
        {
            $trace = "";
            if ($this->canTrace['info'])
            {
                $trace = '['.$this->getBacktrace($start, $depth).']';
            }
            if ($this->canWrite['error'])
            {
                $this->write("$message $trace");
            }
            if ($this->canDisplay['error'])
            {
                $this->display($message, 'Error', $trace);
            }
        }

        private function getBacktrace($start, $depth)
        {
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, $depth+$start);

            $traceMessage = "";

            for ($i=$start; $i<$depth+$start;$i++)
            {
                if ($traceMessage !== "")
                {
                    $traceMessage .= ", ";
                }
                $traceMessage .= "in ".$trace[$i]['file']." at line ".$trace[$i]['line'];
            }

            return $traceMessage;
        }
    }
?>
