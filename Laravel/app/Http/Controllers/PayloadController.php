<?php
/**
 * User: Chester
 */

namespace App\Http\Controllers;


use Symfony\Component\Process\Process;

class PayloadController
{
    public function index()
    {
        $secret = request()->input('secret', '');
        if ($secret == 'publish_blog') {
            $command = 'cd /home/wwwroot/StayReader/Laravel/public/hexo-blog && git pull && npm install -g && ./node_modules/.bin/hexo g';
            $process = new Process($command);
            $process->run();
        }
        return response()->json(['message' => 'receive push event.']);
    }


}