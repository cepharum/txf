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

By using rewriting URLs of all scripts are pretty-printed and suitable for 
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

Let's create a typical Hello-World-application ...

## Prerequisites

### Choose Names

1. Choose a name for your application. In this example we call it `foobar`.
2. Choose a name for your initial view. Let's call it `home`.

## Download TXF to your Web Space

1. Log in to your web server.
2. Switch to your web space folder: `cd /var/www`
3. Clone git repository: `git clone https://github.com/cepharum/txf.git`

Now you have folder /var/www/txf/txf containing all files of framework.

## Create application folder

First, create folder for your application next to the inner txf folder: 

    mkdir /var/www/txf/foobar

Next create script for rendering initial view.

    cat >/var/www/txf/foobar/home.php <<EOT
    <?php
    namespace de\toxa\txf;
    view::main( "Hello World!" );
    EOT

This will write the script 

    <?php
    namespace de\toxa\txf;
    view::main( "Hello World!" );

to the file `/var/www/txf/foobar/home.php`.

## Set up Web Server Software

Setting up server involves these tasks:

1. Ensure requests are forwarded to capturing collector script of txf in `/var/www/txf/run.php`.
2. Include rule for showing initial view of application unless user is explicitly requesting view.

### Apache 2.2+

#### Using Separate Virtual Host

    <VirtualHost *:80>
        ServerName foobar.example.com
    
        DocumentRoot /var/www/txf/foobar

        SetEnv TXF_DOCUMENT_ROOT /var/www/txf
        SetEnv TXF_APPLICATION foobar
    
        Alias /txf/run.php /var/www/txf/run.php
    
        <Directory /var/www/txf/foobar>
            Options FollowSymLinks
            AllowOverride None
            Order allow,deny
            allow from all
    
            RewriteEngine On
            RewriteBase /
    
            # select some default application
            RewriteRule ^$ home [R,L]
    
            # all requests for unknown resources are processed using global script
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteRule .* /txf/run.php [L]
        </Directory>
    </VirtualHost>>

#### Contained in Existing Virtual Host

    Alias /foobar /var/www/txf/foobar
    Alias /txf/run.php /var/www/txf/run.php

    # provide pathname of folder containing txf and any installed application 
    # unless document root of web server is pointing there already
    SetEnv TXF_DOCUMENT_ROOT /var/www/txf
    
    <Directory /var/www/txf/foobar>
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

### nginx

**TODO!** _See comments in .htaccess.default!_

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
