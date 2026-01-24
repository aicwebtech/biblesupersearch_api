<?php

namespace App\TextToSpeech;

class Mp3
{
  	var $str;
  	var $time;
  	var $frames;
  
  	// Create a new mp3
  	public function __construct($path="")
  	{
  	    if ($path!="") {
  		    $this->str = file_get_contents($path);
  		}
  	}

    public function getStr()
    {
        return $this->str;
    }
  
    // Put an mp3 behind the first mp3
    function mergeBehind($mp3)
    {
        $this->str .= $mp3->str;
    }
    
    // Calculate where's the end of the sound file
    function getIdvEnd()
    {
        $strlen = strlen($this->str);
        $str = substr($this->str,($strlen-128));
        $str1 = substr($str,0,3);
        
        if (strtolower($str1) == strtolower('TAG'))
        {
            return $str;
        } else {
            return false;
        }
    }
    
    // Calculate where's the beginning of the sound file
    function getStart()
    {
        $strlen = strlen($this->str);

        for ($i=0; $i<$strlen; $i++)
        {
            $v = substr($this->str,$i,1);
            $value = ord($v);
        
            if ($value == 255) {
                return $i;
            }
        }
    }
    
    // Remove the ID3 tags
    function stripTags()
    {
        //Remove start stuff...
        $newStr = '';
        $s = $start = $this->getStart();

        if($s===false){
            return false;
        } else {
            $this->str = substr($this->str,$start);
        }
        
        //Remove end tag stuff
        $end = $this->getIdvEnd();
        
        if($end !== false) {
            $this->str = substr($this->str,0,(strlen($this->str)-129));
        }
    }
    
    // Display an error
    function error($msg)
    {
        //Fatal error
        die('<strong>audio file error: </strong>'.$msg);
    }

    // Send the new mp3 to the browser
    function output($path)
    {
        //Output mp3
        //Send to standard output
        if(ob_get_contents())
            $this->error('Some data has already been output, can\'t send mp3 file');
        
        if (php_sapi_name()!='cli') {
            //We send to a browser
            header('Content-Type: audio/mpeg3');
            if(headers_sent())
            $this->error('Some data has already been output to browser, can\'t send mp3 file');
            header('Content-Length: '.strlen($this->str));
            header('Content-Disposition: attachment; filename="'.$path.'"');
        }
        echo $this->str;
        return '';
    }
}