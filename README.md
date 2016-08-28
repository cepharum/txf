txf - PHP web application framework
===================================

(c) 2011-2014 cepharum GmbH, Berlin


## License

> The MIT License (MIT)
>
> Copyright (c) 2014 cepharum GmbH, Berlin, http://cepharum.de
>
> Permission is hereby granted, free of charge, to any person obtaining a copy
> of this software and associated documentation files (the "Software"), to deal
> in the Software without restriction, including without limitation the rights
> to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
> copies of the Software, and to permit persons to whom the Software is
> furnished to do so, subject to the following conditions:
>
> The above copyright notice and this permission notice shall be included in
> all copies or substantial portions of the Software.
>
> THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
> IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
> FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
> AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
> LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
> OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
> THE SOFTWARE.


# Motivation

This framework has evolved over several projects now resulting in a simple but
useful set of classes suitable for developing web applications view by view.

Classes perfectly aid in writing stateful scripts accepting filtered input for
accessing SQL databases using parameter binding for safely querying databases.
Furthermore scripts may use semantic description of output to be converted into
HTML web pages using integrated template engine. This includes description of
forms including support for preventing XSRF attacks.

Due to rewriting all requests URLs are always pretty-printed and suitable for
implementing REST-ful APIs on your own. Creating links to current or any other
script of application is supported without knowing the actual context. On linking
to current script embedding of current input parameters is optionally managed
for you.

Further classes simplify data modelling, data model relationships, encrypted
on-server session storage, SQL- and LDAP-based user management, i18n/l10n using
gettext, data browsing and detailed per-record cards and more. Querying SQL
databases is available using API for incrementally describing an SQL query for
highly simplifying scripts for processing custom search filtering depending on
input parameters.

A single txf framework installation is suitable for running several applications.

# Installation

## Primer

txf is a PHP framework for rapidly developing PHP-based web applications.
Previously it was designed to work out of the box by unzipping it into some
folder accessible from the web and append one ore more web applications relying
on this single installation. This design, however, wasn't suitable for managing
either application and framework in distinct projects to be versioned
separately. That's why the framework was refactored so all framework-specific
files can be put in a common subfolder of some project reyling on txf framework.
However this refactored framework doesn't work out of the box anymore.

## Find Root

This setup relies on at least two projects to be set up and used separately. One
of these is TXF. Any web application will be another project. All these projects
reside in a common folder which is considered to be your web root folder. You
might use some subfolder of your web server's document root, of course. But even
then TXF and any application are both contained as another subfolder in that
common container.

Lets consider this common container to be `/var/www/mycontainer`.

## Get TXF

Clone TXF framework into folder `/var/www/mycontainer/txf`:

    git clone https://github.com/cepharum/txf.git /var/www/mycontainer/txf

Folder `/var/www/mycontainer/txf` is now containing all files of framework.

## Create Your Application

Create folder for your application's individual files next to folder `txf`:

    mkdir /var/www/mycontainer/foobar

Next write this simple script into file `/var/www/mycontainer/foobar/home.php`:

    <?php namespace de\toxa\txf;
    view::main( "Hello World!" );

## Set up Web Server Software

Setting up server involves these tasks:

1. Ensure requests are forwarded to capturing collector script of txf in
   `/var/www/mycontainer/txf/run.php`.
2. Include rule for showing initial view of application unless user is
   explicitly requesting view.

### Apache 2.2+

#### Using Separate Virtual Host

    <VirtualHost *:80>
        ServerName foobar.example.com

        DocumentRoot /var/www/mycontainer/foobar

        SetEnv TXF_DOCUMENT_ROOT /var/www/mycontainer
        SetEnv TXF_APPLICATION foobar

        # include following two lines to support URL-encoded "/" in selectors
        #AllowEncodedSlashes On
        #SetEnv TXF_URLDECODE_SELECTORS foobar

        Alias /txf/run.php /var/www/mycontainer/txf/run.php

        <Directory /var/www/mycontainer/foobar>
            Options FollowSymLinks
            AllowOverride None
            Order deny,allow
            allow from all

            RewriteEngine On
            RewriteBase /

            # select some default application
            RewriteRule ^$ home [R,L]

            # all requests for unknown resources are processed using global script
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteRule .* /txf/run.php [L]
        </Directory>

	    <Location ~ ^/(classes|config|skins)/>
	        Order allow,deny
	        deny from all
	    </Location>

        <Directory /var/www/mycontainer/txf>
            Order allow,deny
            deny from all
        </Directory>

        <Location /txf/run.php>
            Order deny,allow
            allow from all
        </Directory>

    </VirtualHost>>

> This example is configuring web server to show your application when using
> separate domain name `foobar.example.com` without any URL prefix.

#### Contained in Existing Virtual Host

    Alias /foobar /var/www/mycontainer/foobar
    Alias /txf/run.php /var/www/mycontainer/txf/run.php

    # provide pathname of folder containing txf and any installed application
    # unless document root of web server is pointing there already
    SetEnv TXF_DOCUMENT_ROOT /var/www/mycontainer

    # include following two lines to support URL-encoded "/" in selectors
    #AllowEncodedSlashes On
    #SetEnv TXF_URLDECODE_SELECTORS foobar

    <Directory /var/www/mycontainer/foobar>
        Options FollowSymLinks
        AllowOverride None
        Order allow,deny
        allow from all

        RewriteEngine On

        RewriteBase /foobar

        # select some default application
        RewriteRule ^$ home [R,L]

        # all requests for unknown resources are processed using global script
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule .* /txf/run.php [L]
    </Directory>

    <Location ~ ^/foobar/(classes|config|skins)/>
        Order allow,deny
        deny from all
    </Location>

    <Directory /var/www/mycontainer/txf>
        Order allow,deny
        deny from all
    </Directory>

    <Location /txf/run.php>
        Order deny,allow
        allow from all
    </Directory>

> This example is configuring web server to show your application when using URL
> prefix `/foobar`.

### nginx

#### Using Separate Virtual Host

    server {
            listen 80;

            server_name foobar.example.com;

            root /var/www/mycontainer/foobar;

            location = /run.php {
                    alias /var/www/mycontainer/txf;

                    fastcgi_split_path_info ^(.+\.php)(/.+)$;
                    # NOTE: You should have "cgi.fix_pathinfo = 0;" in php.ini

                    fastcgi_pass 127.0.0.1:9000;
                    fastcgi_index index.php;

                    include fastcgi_params;

                    fastcgi_param TXF_DOCUMENT_ROOT /var/www/mycontainer;
                    fastcgi_param TXF_APPLICATION foobar;

			        # include following two lines to support URL-encoded "/" in selectors
                    fastcgi_param TXF_URLDECODE_SELECTORS 1;

                    # This might be contained in included fastcgi_params as well:
                    fastcgi_param SCRIPT_FILENAME $document_root/$fastcgi_script_name;
                    fastcgi_param QUERY_STRING    $query_string;
                    # Set if request is over https.
                    # fastcgi_param HTTPS         $https if_not_empty;
            }

            location = / {
                    rewrite / /home permanent;
            }

            location ~ ^/txf|/config/|/classes/|/skins/ {
                    deny all;
            }

            location ~ /assets/ {
                    try_files $uri =404;
            }

            location / {
                    try_files $uri /run.php?$args;
            }

            # Deny access to .htaccess files, if an Apache's document root
            # concurs with nginx's one.
            location ~ /\.ht {
                    deny all;
            }

            # Prevent unprocessed access on PHP scripts.
            location ~ \.php(/.+)?$ {
                    deny all;
            }
    }

> This example is configuring web server to show your application when using
> separate domain name `foobar.example.com` without any URL prefix.

## Try it!

Use your favourite browser for opening URL of your installation at
`http://foobar.example.com` or `http://example.com/foobar` depending on whether
having set up application in a separate virtual host or contained in an existing
one. Of course you need to choose different domain than `example.com` in either
case.

This will open a nearly blank page showing well-formed XHTML document reading
"Hello World!". By inspecting the document you will encounter a properly set of
DOM elements ready for rendering multi-column views. The page is simply missing
some styling, yet.
