<?php

namespace App\Interfaces;

interface AccessLogInterface
{
    
    public function incrementDailyHits();
    public function getAccessLimit();
    public function isAccessRevoked();
    public function hasUnlimitedAccess();

    //public function canAccessBacon();
}
