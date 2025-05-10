Bible SuperSearch API
Copyright (C) 2006-2023  Luke Mounsey

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License (GPL)
as published by the Free Software Foundation; either version 3
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License (GPL)
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

This software is licensed for NON-COMMERCIAL use only.

Any commercial use requires a commercial license.  Commercial use includes:

* Putting the SOFTWARE behind a paywall
* Charging others to access or use the SOFTWARE
* Selling the SOFTWARE for any amount, whether by itself or bundled with other software
* Incorporating into third party software which is NOT compatible with the GNU GPL (See https://www.gnu.org/licenses/license-list.html#GPLCompatibleLicenses)
* Any other use which would violate the GNU GPL

Please see full license at https://www.biblesupersearch.com/license/

OVERVIEW
Bible SuperSearch Webservice API built using the Laravel framework.
This API, when combined with a UI, allows you to use Bible SuperSearch entirely on your website, not dependent on ours.

This includes the following:
* Core Bible search engine functionality
* Webservice API for accessing this functionality
* API documentation
* Administrative backend
* Installer

This does NOT include:
* A User Interface for the Bible search engine

We reccommend our pre-built user interface, which is available both as a universal client and as a WordPress plugin.
These can be downloaded here: http://www.biblesupersearch.com/downloads
You also have the option to build your own.


Server Requirements:
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


Installation:
* Upload this entire directory to a directory (/path/to/biblesupersearch_api) on your web server.
* Rename .env.example to .env
    (NOTE: Please make sure your file browser is set to display hidden files, or you won't be able to find the .env.example file.)
    
* Make .env WRITABLE by the web server
* In .env, enter your database connection information, and save the file.

* Make sure the entire directory is readable by the web server

* Make these directories and files writable by the web server: (UNIX permissions should be 0775)
    * .env
    * storage               (all directories need to be writable)
    * bootstrap/cache
    * bibles
    * bibles/modules        (all files need to be writable)
    * bibles/unofficial     (all files need to be writable)
    * bibles/rendered       (all files need to be writable)
    * bibles/misc           (all files need to be writable)

* Now, point a subdomain (Something like https://biblesupersearch-api.your-domain.com) to path/to/biblesupersearch_api/public

* Navigate to this subdomain to complete the installation process.  
    You will set a username and password for the API Manager as part of this process.

* Once the API is fully installed, point your Bible SuperSearch client software to the subdomain.
    * Standalone
        In config.js, set "apiUrl": "https://biblesupersearch-api.your-domain.com",
    * WordPress plugin
        On the Bible SuperSearch config page (https://your-domain.com/wp-admin/options-general.php?page=biblesupersearch)
        Set API URL to your subdomain (https://biblesupersearch-api.your-domain.com)

Documentation for using the API can be found at this subdomain once installed.

API Manager

Navigate to https://biblesupersearch-api.your-domain.com/login



Troubleshooting:
    If you run into errors, set APP_DEBUG=true in your .env file
    Now, you will see debugging information when you attempt to load the page.
    If the debugging information doesn't help you solve the problem, please contact us.
