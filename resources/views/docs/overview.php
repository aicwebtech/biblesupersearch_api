<?php
    ?>
This API allows the Bible SuperSearch Bible search engine to be used seemlesly on any website.
There is no cost to use the API, however, a website will be limited to <?php echo config('bss.daily_access_limit') ?> hits per day.

All of our API actions return a JSON encoded string, and are cross-origin compliant, with the header: Access-Control-Allow-Origin: *.
We also support JSONP with the 'callback' parameter avaliable for every API action.
