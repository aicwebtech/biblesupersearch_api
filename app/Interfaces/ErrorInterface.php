<?php

namespace App\Interfaces;

interface ErrorInterface
{
    
    public function getErrors();
    public function getErrorLevel();
    public function getHttpStatus();
}
