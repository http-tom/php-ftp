<?php
namespace HttpTom\Ftp;

use Exception;
use HttpTom\Log\Log as Log;

class FTP {
    
    private static $instance = null;

    private $log = null;

    public static function getInstance()
    {
        if(!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct()
    {
        $this->log = new Log();
        $this->log->setOutputMode(true, 'echo');
    }

    public static function loggingOff()
    {
        self::getInstance()->log->setOutputMode(false);
    }

    /**
     * Connect to FTP server
     * @param string $hostname
     * @param string $user
     * @param string $password
     */
    public static function connect($hostname, $user, $password)
    {
        $cid = ftp_connect($hostname);
        $login_res = ftp_login($cid, $user, $password);

        if(!$cid || !$login_res)
        {
            throw new \Exception('FTP connection failed!');
        }

        ftp_pasv($cid, true);

        self::getInstance()->log->add('FTP Connected'.PHP_EOL);

        return $cid;
    }

    /**
     * Download a file from FTP server
     * @param resource $cid Connection Id
     * @param string $remote_filename
     * @param string $local_filename
     */
    public static function download($cid, $remote_filename, $local_filename)
    {
        return ftp_get($cid, $local_filename, $remote_filename, self::get_ftp_mode($remote_filename));
    }

    /**
     * Closes connection to FTP server
     * @param reource $cid
     */
    public static function close($cid)
    {
        if(is_resource($cid))
        {
            ftp_close($cid);
            self::getInstance()->log->add('FTP closed'.PHP_EOL);
            return true;
        }
        self::getInstance()->log->add('Failed to close connection'.PHP_EOL);
        return false;
    }

    private static function get_ftp_mode($file)
    {   
        $path_parts = pathinfo($file);
       
        if (!isset($path_parts['extension'])) return FTP_BINARY;
        switch (strtolower($path_parts['extension'])) {
            case 'am':case 'asp':case 'bat':case 'c':case 'cfm':case 'cgi':case 'conf':
            case 'cpp':case 'css':case 'csv':case 'dhtml':case 'diz':case 'h':case 'hpp':case 'htm':
            case 'html':case 'in':case 'inc':case 'js':case 'm4':case 'mak':case 'nfs':
            case 'nsi':case 'pas':case 'patch':case 'php':case 'php3':case 'php4':case 'php5':
            case 'phtml':case 'pl':case 'po':case 'py':case 'qmail':case 'sh':case 'shtml':
            case 'sql':case 'tcl':case 'tpl':case 'txt':case 'vbs':case 'xml':case 'xrc':
                return FTP_ASCII;
        }
        return FTP_BINARY;
    }


    /**
     * Test if a directory exist / is a directory
     *
     * @param string $dir
     * @return bool $is_dir 
     */
    private static function is_dir($dir, $cid) 
    { 
        $is_dir = false;
        
        // Get the current working directory 
        $origin = ftp_pwd($cid); 

        // Attempt to change directory, suppress errors 
        if (@ftp_chdir($cid, $dir)) 
        { 
            // If the directory exists, set back to origin 
            ftp_chdir($cid, $origin);    
            $is_dir = true; 
        } 

        return $is_dir;
    }
    
    /**
     * Download a directory from remote FTP server
     *
     * If remote_dir ends with a slash download folder content
     * otherwise download folder itself
     *
     * @param resource $connection_id
     * @param string $remote_dir
     * @param string $local_dir
     *
     * @return bool $downloaded
     *
     */
    public static function download_dir($cid, $remote_dir, $local_dir)
    {
        $downloaded = false;

        try
        {
            if(is_dir($local_dir) && is_writable($local_dir))
            {
                // If remote_dir does not ends with /
                if(!substr($remote_dir, -1) === '/')
                {
                    // Create first level directory on local filesystem
                    $local_dir = rtrim($local_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($remote_dir);
                    mkdir($local_dir);
                }
                
                // Remove trailing slash
                $local_dir = rtrim($local_dir, DIRECTORY_SEPARATOR);

                $downloaded = self::download_all($cid, $remote_dir, $local_dir); 
            }
            else
            {
                throw new \Exception("Local directory does not exist or is not writable", 1);
            }
        }
        catch(\Exception $e)
        {
            error_log("FTP::download_dir : " . $e->getMessage());
        }

        return $downloaded;
    }

    /**
     * Recursive function to download remote files
     *
     * @param resource $cid
     * @param string $remote_dir
     * @param string $local_dir
     *
     * @return bool $download_all
     *
     */
    private static function download_all($cid, $remote_dir, $local_dir)
    {
        $download_all = false;

        try
        {
            if(self::is_dir($remote_dir, $cid))
            {
                // $files = ftp_nlist($cid, $remote_dir);
                if(substr($remote_dir, -1) != DIRECTORY_SEPARATOR)
                {
                    $remote_dir .= DIRECTORY_SEPARATOR;
                }
                $files = ftp_mlsd($cid, $remote_dir);
                if($files !== false)
                {
                    $to_download = 0;
                    $downloaded = 0;

                    // do this for each file in the remote directory 
                    foreach ($files as $file)
                    {
                        // To prevent an infinite loop 
                        if ($file['type'] != "cdir" && $file['type'] != "pdir")
                        {
                            $to_download++;
                            // do the following if it is a directory 
                            if ($file['type'] == 'dir')
                            {                                
                                // Create directory on local filesystem
                                self::getInstance()->log->add("Creating " . $local_dir . DIRECTORY_SEPARATOR . basename($file['name']). ' ## ' . $file['name'].PHP_EOL);
                                if (!file_exists($local_dir . DIRECTORY_SEPARATOR . basename($file['name'])) || !is_dir($local_dir . DIRECTORY_SEPARATOR . basename($file['name']))) {
                                    mkdir($local_dir . DIRECTORY_SEPARATOR . basename($file['name']));
                                }
                                
                                // Recursive part 
                                self::getInstance()->log->add('Entering '.$remote_dir.$file['name'].PHP_EOL);
                                if(self::download_all($cid, $remote_dir.$file['name'].DIRECTORY_SEPARATOR, $local_dir . DIRECTORY_SEPARATOR . basename($file['name'])))
                                {
                                    $downloaded++;
                                }
                            }
                            else
                            { 
                                // Download files 
                                self::getInstance()->log->add("Downloading file ". $remote_dir.$file['name'] . ' ');
                                $downloadFile = true;
                                $modifiedTime = strtotime($file['modify']);
                                if(file_exists($local_dir . DIRECTORY_SEPARATOR . basename($file['name']))) {
                                    $modifiedLocalTime = filemtime($local_dir . DIRECTORY_SEPARATOR . basename($file['name']));
                                    if(false === $modifiedTime > $modifiedLocalTime)
                                    {
                                        self::getInstance()->log->appendPrev(' [skipping file]');
                                        $downloadFile = false;

                                        // ensure the file update time is adjusted to be same as the file on server
                                        touch($local_dir . DIRECTORY_SEPARATOR . basename($file['name']), $modifiedTime);
                                    }
                                }
                                if ($downloadFile) {
                                    if (ftp_get($cid, $local_dir . DIRECTORY_SEPARATOR . basename($file['name']), $remote_dir.$file['name'], FTP_BINARY)) {
                                        self::getInstance()->log->appendPrev('success');
                                        $downloaded++;
                                        touch($local_dir . DIRECTORY_SEPARATOR . basename($file['name']), $modifiedTime);
                                    } else {
                                        self::getInstance()->log->appendPrev('failed');
                                    }
                                }
                                self::getInstance()->log->appendPrev(PHP_EOL);
                            }
                        }
                    }

                    // Check all files and folders have been downloaded
                    if($to_download===$downloaded)
                    {
                        $download_all = true;
                    }
                }
            }
        }
        catch(\Exception $e)
        {
            error_log("FTP::download_all : " . $e->getMessage());
        }

        return $download_all;
    }
}