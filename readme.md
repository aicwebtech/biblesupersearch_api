# Bible SuperSearch API

[BibleSuperSearch.com](https://www.biblesupersearch.com)

Bible SuperSearch Webservice API built using the Laravel framework.
This API, when combined with a User Interface (UI), allows you to use Bible SuperSearch entirely on your website, not dependent on ours.

#### This software includes the following:
* Full Bible search engine functionality
* Webservice API for accessing this functionality
* API documentation
* Administrative backend
* Installer

#### This does NOT include:
* A User Interface (UI) for the Bible search engine

We reccommend our pre-built user interface, which is available both as a universal client and as a WordPress plugin.
These can be downloaded here: [Download](http://www.biblesupersearch.com/downloads)
You also have the option to build  your own.

## Official Documentation

### Server Requirements:
* PHP >= 8.2.0
* MySQL
* mod_rewrite (Apache2) or equivalent
* BCMath PHP Extension
* Ctype PHP Extension
* Fileinfo PHP extension
* gd PHP extension
* JSON PHP Extension
* Mbstring PHP Extension
* OpenSSL PHP Extension
* PDO PHP Extension
* PDO_MYSQL PHP Extension
* Tokenizer PHP Extension
* XML PHP Extension
* Zip PHP Extension
* SQLite3 PHP Extension
* Composer


Also, it is highly recommended to have a dedicated domain or subdomain for this API.

### Installation (Web Browser):
(Recommended) [Download](http://www.biblesupersearch.com/downloads) the official release, and follow the instructions in it's readme.txt

### Installation (CLI):
NOTE: THIS CLI INSTALLATION IS NOT OFFICIALLY SUPPORTED AT THIS TIME

* Clone this GIT repository into a directory (/path/to/biblesupersearch_api)
* Rename .env.example-cli to .env
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

Thank you for considering contributing to Bible SuperSearch.  If you find any bugs, please [contact us](https://www.biblesupersearch.com/contact).

## License

The Bible SuperSearch API is open-sourced software licensed under the [GNU General Public License (GPL) V3 or Greater](https://opensource.org/licenses/GPL-3.0)

This SOFTWARE is made available FREE of charge, and is licensed for NON-COMMERCIAL use only.

    Matthew 10:8 freely ye have received, freely give. - Jesus

Any commercial use requires a commercial license.  Commercial use includes:

* Putting the SOFTWARE behind a paywall
* Charging others to access or use the SOFTWARE
* Selling the SOFTWARE for any amount, whether by itself or bundled with other software.  This includes charging for shipping, handling or installation.
* Using the SOFTWARE as a gift to solicit donations.
* Incorporating into third party software which is NOT compatible with the GNU GPL. See [GNU License Compatibility](https://www.gnu.org/licenses/license-list.html#GPLCompatibleLicenses)
* Any other use which would violate the GNU GPL

Please see full license at https://www.biblesupersearch.com/license/

