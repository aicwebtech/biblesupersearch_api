# Bible SuperSearch API

[BibleSuperSearch.com](http://www.biblesupersearch.com)

Webservice API for Bible SuperSearch built using the Laravel framework.
Currently version-locked to Laravel 5.4, to allow users to easily migrate from Bible SuperSearch legacy.
(The legacy software will not work in PHP 7, and PHP 7 is required by Laravel 5.5)

## Notice
While we believe the API itself to be functional, this software has yet to be officially released.
We cannot provide any support for installing or configuring it.
Download and use at your own risk.

## Official Documentation

###Server Requirements:
* PHP >= 5.6.4 (PHP 7.0.0 >= highly recommended)
* OpenSSL PHP Extension
* PDO PHP Extension
* Mbstring PHP Extension
* Tokenizer PHP Extension
* XML PHP Extension
* Composer

###Installation (CLI):
* Clone this GIT repository
* Rename .env.example to .env
* Enter your database connection information in .env.  You may wish to set other configs as well at this time.


From the Bible SuperSearch directory, run:

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

Documentation for using the API can be found at your API URL once installed.

## Contributing

Thank you for considering contributing to Bible SuperSearch.  If you find any bugs, please contact us.

## License

The Bible SuperSearch API is open-sourced software licensed under the [GNU GPL V2 or Greater](https://opensource.org/licenses/GPL-2.0)