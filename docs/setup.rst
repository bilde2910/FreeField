Installation guide
==================

Welcome to the FreeField installation guide! This guide will guide you through
installing and setting up FreeField for production use.

Prerequisites
-------------

FreeField requires a configured database backend with an account that allows
creating, altering and deleting tables, and inserting, updating and deleting
data, in the database that will hold FreeField data. All tables created by
FreeField will use a table prefix you decide, to prevent it from interfering
with tables created by other software using the same database. It is recommended
that you use MySQL with FreeField.

A web server with PHP 5.6 or higher is also required. FreeField comes
pre-configured for Apache. Setting up FreeField on another web server, such as
nginx, may require completing additional setup steps. More information about
configuring your webserver is explained in the `Setting up the web server`_
section below.

.. tip:: Setting up a web server with PHP is not in scope of this installation
         guide. However, if you are new to server hosting, there are many good
         guides you can follow, such as `this one by DigitalOcean
         <https://www.digitalocean.com/community/tutorials/how-to-install-linux-apache-mysql-php-lamp-stack-ubuntu-18-04>`_.
         You should also follow `this guide
         <https://www.digitalocean.com/community/tutorials/how-to-install-the-apache-web-server-on-ubuntu-18-04#step-4-%E2%80%94-managing-the-apache-process>`_
         from section 4 and on to learn how to control Apache and how to set up
         virtual hosts (which you will need for HTTPS, which FreeField strongly
         recommends). Finally, `this guide helps you set up HTTPS
         <https://www.digitalocean.com/community/tutorials/how-to-secure-apache-with-let-s-encrypt-on-ubuntu-18-04>`_
         for your installation, which is recommended to get the most out of your
         FreeField installation.

FreeField uses PDO to connect to the database backend. Please ensure that you
have enabled PDO with your preferred database backend, e.g. ``pdo_mysql`` for
MySQL, in your php.ini file.

Downloading FreeField
---------------------

Please download the latest release from `GitHub
<https://github.com/bilde2910/FreeField/releases>`_. Extract the files from the
downloaded archive to the directory on the server that you wish to install
FreeField to.

Rolling release
^^^^^^^^^^^^^^^

Some users may prefer installing FreeField as a rolling release directly from
git. This is possible by running ``git clone
https://github.com/bilde2910/FreeField.git``, although installation from point
releases (i.e. published packaged releases) are generally recommended for most
users. The following table outlines the differences between the two installation
methods.

+--------------------+---------------------------+-----------------------------+
| Feature            | Point releases (packages) | Rolling release (git clone) |
+====================+===========================+=============================+
| Availability of    | Within anything from a    | Immediately upon push to    |
| new features       | few hours (alpha) to      | FreeField repository        |
|                    | days/weeks (stable)       |                             |
+--------------------+---------------------------+-----------------------------+
| Stability          | Varies (alpha through     | Extremely unstable          |
|                    | stable channels)          | (bleeding edge)             |
+--------------------+---------------------------+-----------------------------+
| Installing new     | From web interface        | Requires shell access       |
| updates            |                           |                             |
+--------------------+---------------------------+-----------------------------+
| Specifying update  | Click to select desired   | Specify target hash using   |
| to install         | version                   | ``git checkout``            |
+--------------------+---------------------------+-----------------------------+
| Stability of       | Historically unstable,    | Extremely stable            |
| update process     | see e.g. commit d76af3a_  |                             |
+--------------------+---------------------------+-----------------------------+
| Can switch release | Yes, can switch to        | Yes, can switch to point    |
| plans?             | rolling release; see e.g. | releases by deleting the    |
|                    | `this post`_              | .git directory in FreeField |
+--------------------+---------------------------+-----------------------------+
| Handling breaking  | Breaking changes listed   | Breaking changes listed on  |
| changes            | in changelog visible      | Releases page on GitHub     |
|                    | before installing update  | only up to latest point     |
|                    |                           | release                     |
+--------------------+---------------------------+-----------------------------+
| Dependency and     | Checked automatically     | Not checked                 |
| prerequisite       | before updates are        |                             |
| checks             | applied; update halted if |                             |
|                    | necessary                 |                             |
+--------------------+---------------------------+-----------------------------+

.. _d76af3a: https://github.com/bilde2910/FreeField/commit/d76af3a22cee17ae661684303961df30ba2c7482
.. _this post: https://stackoverflow.com/q/37354989

Setting up the web server
-------------------------

Apache
^^^^^^

FreeField is pre-configured for Apache and does not require additional
configuration. Therefore, it is generally recommended that you install FreeField
under Apache.

nginx
^^^^^

If you are setting up FreeField for nginx, it is **critical** that you restrict
access to some directories that should not be publicly visible.

includes
   You **must** deny access for everyone to this directory. It contains files
   that are included from elsewhere, and accessing these scripts directly can be
   dangerous.

docs
   You are *recommended* to restrict access to this directory, as it only
   contains documentation in reStructuredText format. This is already readily
   available from https://freefield.readthedocs.io/ in a more user-friendly
   format.

Please check that you are not able to access these directories from the browser
before you continue setting up FreeField.

.. danger:: If you do not deny all direct access to /includes (via e.g.
            ``deny all``), any user would be able to break your entire FreeField
            installation beyond repair, and might even compromise the security
            of your server. Files in this directory contains code that is only
            supposed to be executed in controlled circumstances. The behavior of
            this code in circumstances of direct execution has not been tested.

Other web servers
^^^^^^^^^^^^^^^^^

No documentation currently exists for other web servers. If you have set up
FreeField on a web server that is not listed above, please share your experience
on GitHub so we can add it to this documentation.

Installation wizard
-------------------

Once you have downloaded and extracted the FreeField archive, you may navigate
to the installation path using your web browser of choice. You will
automatically be redirected to the installation wizard.

Stage 1: Environment checks
^^^^^^^^^^^^^^^^^^^^^^^^^^^

This stage of the installation checks that your installation environment is
suitable for FreeField. Below are a list of performed checks, and steps you may
perform to correct any issues with the environment.

Encrypted connection (HTTPS)
   Web browsers will restrict access to geolocation and service workers, among
   other things, if HTTPS is not enabled on the installation site. A lack of
   HTTPS on your site will result in users not being able to tap on the "my
   location" button on the map to locate and track their own location on the
   map. It will also not be possible to enable Progressive Web App functionality
   if HTTPS is disabled, as this depends on service workers, which do not work
   without HTTPS for security reasons.

   .. tip:: If your hosting provider already offers HTTPS by default, you can
            try to simply load your site over HTTPS instead by changing your
            browser URL. Otherwise, you may have to enable HTTPS yourself. If
            you are running your own server, and you do not have HTTPS set up,
            you need to enable and configure HTTPS in your HTTP daemon's
            configuration file, and allow connections to TCP port 443 (or
            whatever port you are running HTTPS over) through your firewall.

   .. tip:: If you need a TLS certificate, you could use a service such as Let's
            Encrypt to get one for free. For information on how to set up Let's
            Encrypt, please see Let's Encrypt's `Getting Started guide
            <https://letsencrypt.org/getting-started/>`_.

Installation directory writable
   In order for FreeField to perform updates, it is highly recommended that you
   allow writing to FreeField's installation directory. FreeField will still
   function without this permission, but you will not be able to install
   updates.

   .. hint:: To allow writing, either change the owner of the installation
             directory to the user used by the HTTP daemon using e.g. ``chown
             -R http:http .``, or change the file permission to allow global
             writes, i.e. ``chmod -R a+w .``, in the installation directory.

Userdata directory writable
   FreeField stores its configuration files and some user-submitted data in the
   /includes/userdata folder. This folder must be writable by the HTTP daemon.
   If it is not writable, make it writable, either by changing the owner of the
   file, or by allowing global writes, as detailed in the above section.

cURL extension loaded
   cURL is used to download updates to FreeField, as well as performing user
   authentication. FreeField will not work without cURL. If this check fails,
   ensure that the PHP cURL extension is available on your system, and that it
   is enabled in php.ini.

fopen() allows URLs
   ``fopen()`` is used to make requests to webhooks and in some cases, to
   facilitate user authentication. Some installations of PHP have ``fopen()``
   set to deny reading from URLs. This can cause FreeField to fail if webhooks
   are called, and in some cases, when users authenticate with certain
   authentication providers. To enable this setting, ensure that
   ``allow_url_fopen`` is set to ``1`` in php.ini.

gd extension loaded
   If FreeField is configured to require approval of newly registered users, the
   user approval requirements notice page displayed to the newly registered
   users can be configured to display QR codes that, if scanned by an
   administrator, allows quickly approving the user. An approval link will be
   required in any case that the user can forward to an admin through some
   messaging service/private message somewhere.

   .. hint:: QR codes and manual approval is explained in greater detail in
             :ref:`manual-approval`.

openssl extension loaded
   Cryptographic functions are used for various purposes in FreeField, and these
   functions are provided by OpenSSL. FreeField uses encryption for session
   cookies and sensitive data in the configuration files, as well as
   ``openssl_random_pseudo_bytes()`` for generating CSRF state tokens, session
   tokens and cryptographic keys. FreeField will not function without this
   extension. Ensure that it is installed and enabled in php.ini.

PharData available
   PharData is used to extract updates after they have been downloaded.
   FreeField will still function even if PharData for some reason isn't present,
   but updates will not be possible to install.

You should ensure that as many as possible of the above checks pass, as failing
checks may limit the functionality of FreeField or completely prevent it from
working - in the latter case, the installation wizard will not allow you to
proceed with the installation. You should make the desired changes now, as some
configuration defaults vary depending on the state of the checks. Apply the
changes, restart the HTTP daemon for the changes to take effect, and then reload
the installation wizard to ensure that the changes have been applied and that
the checks are now passing.

Stage 2: Write the configuration file
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

This stage simply writes a configuration file with default values applied to the
userdata directory. It also generates cryptographic keys for session data and
sensitive configuration file entries. This step is automatic. The output from
this step should be the following three checks:

- Copied file options from template files
- Secure storage encryption keys generated
- Configuration file written

If any of those entries are missing, along with the :guilabel:`Continue setup`
button, then something has gone very wrong, and you should check your web server
error logs.

Stage 3: Database setup
^^^^^^^^^^^^^^^^^^^^^^^

In this stage, you need to set up a connection from FreeField to your database
backend. Choose your database provider from the list of available providers and
enter the required connection details.

Hostname
   The hostname of the database server.

   .. hint:: This is typically "localhost", "127.0.0.1" or "::1" if MySQL is
             running on the same host as the web server. If you are using shared
             web hosting, please check your hosting provider's settings panel
             for the hostname, as shared hosting providers often have dedicated
             SQL servers.

Port
   The port that your database runs on. In most cases, you can leave this to the
   default ``-1`` to let PDO use the default port for your given database type.

Username
   The username used to access the database server.

Password
   The password used to access the database server.

Database
   The database that you wish to store FreeField data in.

Table prefix
   All FreeField tables are prefixed with this string to separate it from other
   tables in the database. You have to specify a string here. The default prefix
   ``ffield_`` works in most cases, though if you are running multiple instances
   of FreeField in the same database, you must select a different table prefix
   for each instance, so the instances do not interfere with each other.

.. caution:: Only MySQL has been tested and is known to be stable with
             FreeField. Providers marked "experimental" have not been tested and
             may be unstable, not work at all, or spontaneously break in the
             future. Use these at your own risk.

.. note:: If you cannot find your database provider in the list, then you have
          most likely not enabled the PDO extension for your database backend in
          php.ini. For example, if you want to use MySQL, you must ensure that
          ``extension=pdo_mysql`` is defined and not commented out in php.ini.
          If you have enabled the extension, and the option still does not show
          up in the selection box, then FreeField may not support your database
          backend. If you wish for your database backend to be supported, you
          may create an issue for it on GitHub, but remember to search for
          existing related issues first, as others may have requested it before
          you.

.. note:: If you use SQLite, please fill in the path to the SQLite database in
          the "Database" field, and fill in dummy values in all other fields.

When you are ready, FreeField will connect to the tables and set up the
necessary database table structure. If everything went according to plan, the
following five entries should all be checked with green check marks:

Database details are valid
   If this fails, one or more provided settings may be empty or contain invalid
   characters. FreeField will not attempt to connect to the database if the
   database settings are invalid.

Configuration file updated
   If this fails, then FreeField was not able to write the configuration file in
   the userdata directory. The userdata directory must be permanently writable
   in order for FreeField to function.

Connected to database
   If this fails, FreeField was not able to establish a connection to the
   database. Please read the accompanying error message for more details, or
   consult the troubleshooting section below for help resolving common mistakes.

Created database structure
   If this fails, FreeField was able to establish a connection to the database,
   but could not run the SQL queries necessary to set up the FreeField tables.
   Please read the accompanying error message for more details, or consult the
   troubleshooting section below for help resolving some common mistakes.

Stage 3 registered complete
   This step saves the progress of the installation wizard to the configuration
   file. If this step fails, something is seriously wrong with your server, as
   it means the configuration file became unwritable somewhere during the
   database connection process. This should never happen under any
   circumstances.

Troubleshooting
"""""""""""""""

``SQLSTATE[HY000] [1044]``
   The authentication credentials were correct, but the database could not be
   connected to. Check that you did not mistype the name of the database, that
   the database actually exists, and that the given user has permission to
   access and modify it.

``SQLSTATE[HY000] [1045]``
   The provided database credentials were incorrect. Double-check the username
   and password you defined.

``SQLSTATE[42S01]``
   You have already set up FreeField before with these details. You can install
   this FreeField instance side-by-side with the other instance in the same
   database by changing the table prefix to some other value than the default.

Stage 4: Authentication setup
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

In this stage, you will be setting up authentication on FreeField. You have to
set up at least one authentication provider and demonstrate that you are able to
sign in to it in order to proceed to the next step. Please consult the
:doc:`/auth/index` docs for help setting up authentication with your preferred
authentication provider. Once you are done setting up authentication, you will
be prompted to sign in using one of the providers you set up.

All of the following checks must pass in order to continue to the next step:

Provided authentication details are valid
   If this fails, then there is an invalid value in your authentication setup.
   Please ensure that you have correctly inserted the required values for your
   authentication provider according to the :doc:`/auth/index` docs.

Configuration file written
   If this fails, then FreeField failed to update the configuration file with
   the authentication provider settings you provided. Ensure that the userdata
   folder remains permanently writable.

At least one authentication provider is enabled
   If this fails then you have either not enabled any of the authentication
   providers on the previous page using the "Enable" checkboxes, or you have
   enabled one or more, but there is missing information for all of them (e.g.
   you have enabled an authentication provider, but not provided required
   details, such as a client ID and/or secret). Ensure that all fields are
   filled in, and the "Enable" checkbox ticked off, for at least one
   authentication provider, then try again.

Prepared authentication challenge
   If this fails, then something is seriously wrong with your server. It would
   indicate that within milliseconds of the configuration file being written
   above, someone or something prevented the configuration file from being
   written to again. This should never fail under any reasonable circumstances.

When you have configured an authentication provider, and all checks pass, you
can proceed to sign in using the authentication provider you set up.

Stage 5: Verify authentication setup
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

You are automatically redirected to this stage when you click
:guilabel:`Continue setup` in stage 4, and the authentication challenge is part
of this step. Sign in using any available authentication provider.

.. hint:: If you for some reason cannot sign in using a provider, you can at any
          time click on :guilabel:`Reconfigure` to return to stage 4 and attempt
          to set up the authentication providers again. You may want to consult
          the :doc:`/auth/index` docs to ensure that authentication is set up
          properly.

When you have signed in, you should return to the installation wizard, and all
of the following checks must pass:

Authentication successful
   If you can see this check, then you have already successfully authenticated.
   This check cannot fail.

Registered account as site administrator in database
   This is handled by the FreeField authentication module, not the setup wizard.
   If you can see this check, then you have already been added to the database.
   This check cannot fail.

Configuration file updated
   If this step fails, the userdata folder (or the configuration file within) is
   no longer writable. The userdata folder and all contents must remain
   permanently writable for FreeField to function.

Stage 6: Map setup
^^^^^^^^^^^^^^^^^^

In this step, you have to set up map settings to use with FreeField. You have to
choose a map provider and set it up, along with map defaults. Please consult the
:doc:`/map/index` docs for more information on how to configure map providers.

In addition to selecting a map provider, you have to specify the default
starting coordinates for FreeField. The coordinates you choose are the ones that
the map will be centered on when you first launch FreeField.

.. tip:: It is a very good idea to pick the coordinates of a centrally located
         and/or easily recognizable location in the town/city you are setting up
         FreeField for. The default 0, 0 location is **not a good location** to
         center the map.

When you are done with stage 6, FreeField will write the map provider settings
to the configuration file. The following checks should pass:

Provided map settings are valid
   If this fails, there is an error in the settings you entered. Ensure that the
   map provider details are set up as described in the :doc:`/map/index` docs,
   and that the defaults map location you have selected are valid coordinates.

Configuration file updated
   If this fails, then FreeField was unable to save the settings you just
   entered. The most likely cause for this is that the configuration file is not
   writable. The userdata directory and its contents must remain permanently
   writable in order for FreeField to function properly.

Post-installation steps
-----------------------

If all these checks passed, you have successfully completed the installation
wizard and set up FreeField for use. Before you grant others access to the map,
you should set up additional settings such as :doc:`/geofencing`,
:doc:`/permissions`, :doc:`/appearance`, and then add the :doc:`manage-poi` in
your area to the map.

.. warning:: By default, FreeField allows submission of Pokéstops to the map
             anywhere in the world. To prevent your map from being leeched by
             users elsewhere, it is strongly recommended that you set up a
             geofence after installation that restricts the area in which
             Pokéstops and field research can be submitted. For more information
             on how to do this, refer to :doc:`/geofencing`.
