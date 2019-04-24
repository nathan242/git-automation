<?php

    function error_handler($errno, $errstr, $errfile, $errline) {
        $error_email = 'test@example.org';
        
        $data = "*** Git Control Error ***\n";
        $data .= 'ERRNO: '.$errno."\n";
        $data .= 'ERRSTR:  '.$errstr."\n";
        $data .= 'ERRFILE: '.$errfile."\n";
        $data .= 'ERRLINE: '.$errline."\n";
        
        //echo $data;
        mail($error_email, '*** Git Control Error ***', $data);
        exit();
    }

    set_error_handler('error_handler');

?>
