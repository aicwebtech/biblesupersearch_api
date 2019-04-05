Bible SuperSearch Webservice API built using the Laravel framework.
This API, when combined with a UI, allows you to use Bible SuperSearch entirely on your website, not dependent on ours.

This includes the following:
* Core Bible search engine functionality
* API documentation
* Administrative backend
* Installer

This does NOT include:
* A User Interface for the Bible search engine


Server Requirements:
* PHP >= 7.1.3
* MySQL
* OpenSSL PHP Extension
* PDO PHP Extension
* PDO_MYSQL PHP Extension
* Mbstring PHP Extension
* Tokenizer PHP Extension
* XML PHP Extension
* Zip PHP Extension
* Ctype PHP Extension
* JSON PHP Extension
* BCMath PHP Extension
* Composer

Also, this API must have it's own subdomain.

Installation (Web Browser):
* Upload this entire directory to a directory (/path/to/biblesupersearch_api) on your web server.
* Rename .env.example to .env and make sure that it's WRITABLE for the web server
* Make sure the whole directory is readable by the web server
* Make these directories writable by the web server:
    * storage               (all subdirectories need to be writable)
    * bootstrap/cache
    * bibles/modules        (all files need to be writable)
    * bibles/unofficial     (all files need to be writable)

* Now, point a subdomain (https://biblesupersearch-api.your-domain.com) to path/to/biblesupersearch_api/public
* Point your Bible SuperSearch client software to the subdomain.
    * Standalone
        In config.js, set "apiUrl": "https://biblesupersearch-api.your-domain.com",
    * Word Press plugin
        On the Bible SuperSearch config page (https://your-domain.com/wp-admin/options-general.php?page=biblesupersearch)
        Set API URL to your subdomain (https://biblesupersearch-api.your-domain.com)

Documentation for using the API can be found at this API subdomain once installed.
