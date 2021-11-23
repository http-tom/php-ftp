<?php

declare(strict_types=1);

require dirname(dirname(dirname(dirname(__FILE__)))).'/vendor/autoload.php';

use PHPUnit\Framework\TestCase;

use HttpTom\Ftp\FTP as FTP;


final class FTPTest extends TestCase {

    public function testConnect()
    {
        $cid = FTP::connect();
        $this->$this->assertIsResource($cid);
    }
}
