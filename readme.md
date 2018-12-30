# Bible SuperSearch API

[BibleSuperSearch.com](https://www.biblesupersearch.com)

Bible SuperSearch Webservice API built using the Laravel framework.
This API, when combined with a UI, allows you to use Bible SuperSearch entirely on your website, not dependent on ours.

This includes the following:
* Core Bible search engine functionality
* API documentation
* Administrative backend
* Installer

This does NOT include:
* A User Interface for the Bible search engine

## Notice
While we believe the API itself to be functional, this software has yet to be officially released.
We cannot provide any support for installing or configuring it.
Download and use at your own risk.

## Official Documentation

###Server Requirements:
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

###Installation (Web Browser):
[Download](http://www.biblesupersearch.com/downloads) the official release,
and follow the instructions in it's readme.txt

###Installation (CLI):
NOTE: THIS CLI INSTALLATION IS NOT OFFICIALLY SUPPORTED AT THIS TIME

* Clone this GIT repository into a directory (/path/to/biblesupersearch_api)
* Rename .env.example to .env
* Enter your database connection information in .env.  You may wish to set other configs as well at this time.


From the Bible SuperSearch API directory, run:

```
./install
```

This will set up most of the application.
However, it will not install any Bible modules.

See the `php artisan` commands for Bibles

Install and enable ALL available Bibles:

```
php artisan bible:install --all --enable
```

* Now, point a subdomain (https://biblesupersearch-api.your-domain.com) to path/to/biblesupersearch_api/public
* Point your Bible SuperSearch client software to the subdomain.
    * Standalone
        In config.js, set "apiUrl": "https://biblesupersearch-api.your-domain.com",
    * Word Press plugin
        On the Bible SuperSearch config page (https://your-domain.com/wp-admin/options-general.php?page=biblesupersearch)
        Set API URL to your subdomain (https://biblesupersearch-api.your-domain.com)

Documentation for using the API can be found at this API URL once installed.

## Contributing

Thank you for considering contributing to Bible SuperSearch.  If you find any bugs, please contact us.

## License

The Bible SuperSearch API is open-sourced software licensed under the [GNU GPL V3 or Greater](https://opensource.org/licenses/GPL-3.0)