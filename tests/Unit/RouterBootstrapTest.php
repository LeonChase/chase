<?php
namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RouterBootstrapTest extends TestCase
{
    public function testRuntimeBootsWhenPhpIdentifiesTheRouterAsTheEntryScript(): void
    {
        [$exit, $output, $errors] = $this->request('/login');
        self::assertSame(0, $exit, $errors);
        self::assertStringContainsString('login-form', $output);
        self::assertStringNotContainsString('Fatal error', $output.$errors);
    }

    public function testPublicAssetsStillBypassSymfony(): void
    {
        [$exit, $output, $errors] = $this->request('/assets/app.css');
        self::assertSame(0, $exit, $errors);
        self::assertSame('STATIC_BYPASS', $output);
    }

    /** A fresh PHP process is necessary because Symfony Runtime terminates its runner. */
    private function request(string $uri): array
    {
        $script = <<<'PHP'
$_SERVER['REQUEST_URI']=$argv[2];
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['HTTP_HOST']='localhost';
$_SERVER['SERVER_PORT']='80';
$_SERVER['SERVER_PROTOCOL']='HTTP/1.1';
$_SERVER['SCRIPT_NAME']='/router.php';
$_SERVER['PHP_SELF']='/router.php';
$_SERVER['SCRIPT_FILENAME']=$argv[1].'/public/router.php';
$result=require $_SERVER['SCRIPT_FILENAME'];
if($result===false)echo 'STATIC_BYPASS';
PHP;
        $process = proc_open([PHP_BINARY, '-r', $script, dirname(__DIR__, 2), $uri],
            [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes, dirname(__DIR__, 2));
        self::assertIsResource($process);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output=''; $errors=''; $started=microtime(true); $exit=-1;
        do {
            $output.=stream_get_contents($pipes[1]);
            $errors.=stream_get_contents($pipes[2]);
            $status=proc_get_status($process);
            if(!$status['running']){$exit=$status['exitcode'];break;}
            if(microtime(true)-$started>10){proc_terminate($process);break;}
            usleep(10000);
        } while(true);
        $output.=stream_get_contents($pipes[1]);
        $errors.=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        return [$exit, $output, $errors];
    }
}
