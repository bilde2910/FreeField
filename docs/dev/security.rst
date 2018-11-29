Security in FreeField
=====================

This document aims to explain various considerations that have been made with
regards to security in FreeField, and steps that need to be taken to ensure this
security standard is upheld.

Cross-site scripting (XSS)
--------------------------

When outputting user-provided data, the data must be escaped to prevent XSS
attacks. XSS attacks let users input data in such a way that when the data is
later output, their result forms some kind of HTML tag or JavaScript statement
that allows arbitrary code execution. For example, a user could use
``<script>alert("Hello world!");</script>`` as their username and, if not
properly handled, this would result in the execution of the ``alert()``
statement when a browser visits a page with unescaped output.

Sanitation of data is required for all data that is:

-  Obtained directly from end users (e.g. usernames, Pokéstop names, etc.)
-  Obtained via third party APIs (e.g. user provider identities, Telegram group
   names, etc.)
-  Stored in configuration files or databases
-  Strings from localization files

If the data is converted to a format that does not allow script execution, such
as a string converted to an integer via ``intval()``, the converted output does
not require XSS sanitation, but bounds checking may still be required to ensure
values are within an acceptable range.

General handling
^^^^^^^^^^^^^^^^

If no specific function exists for automatically escaping values as listed
below, you should use the following functions to escape data:

.. code-block:: php

   // Output to HTML (returns string with escaped characters):
   echo htmlspecialchars($unsafeStr, ENT_QUOTES);

   // Output to JavaScript (returns JSON encoded data):
   echo json_encode($unsafeStr);

   // Output as part of URL (e.g. for Location HTTP header):
   echo "./foobar.php?value=".urlencode($unsafeStr);

Configuration values
^^^^^^^^^^^^^^^^^^^^

When displaying values from the FreeField configuration directly on the page,
use the following functions:

.. code-block:: php

   // Output to HTML:
   echo Config::get("setting/path")->valueHTML();

   // Output to JavaScript:
   echo Config::get("setting/path")->valueJS();

   // Output as part of URL:
   echo Config::get("setting/path")->valueURL();

   // NEVER do this:
   echo Config::get("setting/path")->value();

Localized strings
^^^^^^^^^^^^^^^^^

When displaying localized strings on HTML pages, use the following functions:

.. code-block:: php

   // Without arguments to HTML:
   echo I18N::resolveHTML("token.path");

   // Without arguments to JSON/escaped and quoted string:
   echo I18N::resolveJS("token.path");

   // With arguments to HTML:
   // - If `$args` contains unescaped user data, then use:
   echo I18N::resolveArgsHTML("token.path", true, $args);
   // - Otherwise, if you are 100% certain all data in `$args` cannot contain
   //   user provided data, or the data in `$args` is already escaped, use:
   echo I18N::resolveArgsHTML("token.path", false, $args);

   // With arguments to JSON/escaped and quoted string:
   // - If `$args` contains unescaped user data, then use:
   echo I18N::resolveArgsJS("token.path", true, $args);
   // - Otherwise, if you are 100% certain all data in `$args` cannot contain
   //   user provided data, or the data in `$args` is already escaped, use:
   echo I18N::resolveArgsJS("token.path", false, $args);

The second argument to the ``resolveArgs`` functions determines whether the
complete and fully localized string should be escaped (true), or whether only
the base string should be escaped, and the ``$args`` data should be substituted
in without escaping (false). If ``$args`` intentionally contains HTML tags, but
also contains user data, escape the user data in the array using
``htmlspecialchars($string, ENT_QUOTES)``.

Client-side, all strings are resolved using ``resolve()`` and ``resolveArgs()``.
When outputting them to the page:

.. code-block:: javascript

   // Do this:
   $("#element").text(resolve("token.path"));

   // NEVER do this:
   $("#element").html(resolve("token.path"));

Cross-site request forgery (CSRF)
---------------------------------

A CSRF attack involves a user voluntarily or involuntarily making a request to
FreeField from a site hosted elsewhere, such as by submitting a form on a third
party site that points to a script on FreeField. This can cause users to perform
unwanted actions, such as a form tricking them to e.g. send an email that
instead is submitted to FreeField with hidden fields that cause a malicious
user's privileges to be elevated.

All forms must use CSRF protection. This also applies to anchor tags that
perform some kind of server-side action, such as anchors to auth/logout.php.
This can be implemented as such:

1. Add this to the top of the PHP script that contains the input form or anchor,
   before any other output is written to the browser:

   .. code-block:: php

      __require("security");
      Security::requireCSRFToken();

2. Do either of the following:

   -  For HTML forms, output a CSRF field:

      .. code-block:: html

         <form method="post" action="foo.php">
             <?php echo Security::getCSRFInputField(); ?>
             <!-- More form fields here -->
         </form>

   -  For anchors, add the CSRF parameter to the URL:

      .. code-block:: html

         <a href="./foo.php?<?php echo Security::getCSRFUrlParameter(); ?>">
             <!-- Anchor content -->
         </a>

3. In the target script that processes the form contents or anchor, do this
   before any processing takes place to check if there is a CSRF failure, and
   cancel processing if that is the case:

   .. code-block:: php

      __require("security");
      if (!Security::validateCSRF()) {
          // Validation failed, redirect user to where they came from
          header("HTTP/1.1 303 See Other");
          header("Location: /return/path.php");
          exit;
      }

Securing authentication
-----------------------

Always make use of any CSRF protection mechanisms provided by the authentication
provider's API, typically via OAuth2 with the ``state`` parameter:

.. code-block:: php

   // auth/oa2/*.php

   __require("vendor/oauth2");
   $opts = array(
       /* ... other OAuth2 options go here ... */
       "params" => array(
           "state" => $state = bin2hex(openssl_random_pseudo_bytes(16))
       )
   );

   include(__DIR__."/../../includes/auth/oauth2-proc.php");

The ``oauth2-proc.php`` script automatically handles CSRF protection for OAuth2
providers. If the script is still executing after the ``include`` line for that
file, then all checks have passed and authentication is genuine and successful.

Considerations for Telegram bot tokens
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

The guide for setting up :doc:`/auth/telegram` makes a clear warning statement
not to use the bot token that is used for authentication for any other purpose
and to never share it with any third parties, citing possible attack vectors
associated with other users being able to impersonate the Telegram service and
other users when authenticating to FreeField with Telegram.

The technical reason behind this warning is the way in which Telegram handles
validation and signing of authentication requests. When a user authenticates to
Telegram, their web browsers are given a set of fields that they in turn
automatically pass on to FreeField, including their username and identifying
information, along with a hash field that is used to check the authenticity of
the received data. Citing the `Telegram documentation on login widgets
<https://core.telegram.org/widgets/login#checking-authorization>`_:

   You can verify the authentication and the integrity of the data received by
   comparing the received hash parameter with the hexadecimal representation of
   the HMAC-SHA-256 signature of the **data-check-string** with the SHA256 hash
   of the bot's token used as a secret key.

   **Data-check-string** is a concatenation of all received fields, sorted in
   alphabetical order, in the format ``key=<value>`` with a line feed character
   ('\\n', 0xA0) used as separator – e.g.,
   ``'auth_date=<auth_date>\nfirst_name=<first_name>\nid=<id>\nusername=<username>'``.

Since the bot token is used as the secret key, anyone with the bot token will be
able to construct a data-check-string with the name, ID and username of any user
and then sign it using the bot token to get a valid hash value.

In general, this method of authentication is secure, but it requires that the
bot token is kept secret and closely guarded - which it *would* be in most
applications that implement the Telegram API, since most likely, the developers
of those applications are the only ones who'd generate tokens. However, for
FreeField, the risk is greater that end user administrators don't realize the
full potential usage area for the tokens, given that most end users would have
little to no developer experience and that Telegram, upon issuance of the token,
does not state that it has to be stored securely.

This is also the reason that bot tokens are masked on webhooks that trigger
Telegram messages. Despite our insistence in the :doc:`/webhooks/telegram`
documentation to not re-use the authentication bot token for webhooks, some
users will inevitably do it anyway, and in an effort to prevent the
authentication bot token from being leaked through the webhook list, we've
chosen to always treat the token as if it was used for authentication, i.e.
always masking it when displayed to users, never sending it to the web browser,
and storing it in encrypted form in the configuration files.

Permissions
-----------

Many functions in FreeField are not supposed to be accessible by regular users,
and a permissions system is implemented to granularize and enforce access
restrictions for those resources.

Permissions are stored in the configuration files as a settings path of the
``PermissionsOption`` type:

.. code-block:: php

   // includes/config/defs.php

   "permissions/level/admin/updates/general" => array(
       "domain" => "perms",
       "section" => "admin",
       "default" => PermissionOption::LEVEL_HOST,
       "option" => new PermissionOption()
   ),

All permissions are stored as sub-keys under ``permissions/level`` and are
assigned a default permission level that corresponds to one of the default
permission levels in the ``PermissionOption`` class.

.. code-block:: php

   // includes/config/types.php

   class PermissionOption extends DefaultOption {
       /*
           Constants representing the default permission levels.
       */
       const LEVEL_HOST = 250;
       const LEVEL_ADMIN = 200;
       const LEVEL_MODERATOR = 160;
       const LEVEL_SUBMITTER = 120;
       const LEVEL_REGISTERED = 80;
       const LEVEL_READ_ONLY = 40;
       const LEVEL_ANONYMOUS = 0;

       /* ... more functions ... */
   }

Permissions must be checked for the current user before performing potentially
dangerous operations. For example, to check for the above permission under the
``permissions/level/admin/updates/general`` setting, use:

.. code-block:: php

   if (!Auth::getCurrentUser()->hasPermission("admin/updates/general")) {
       header("HTTP/1.1 303 See Other");
       header("Location: /return/path.php");
       exit;
   }

If there is a need to add a new permission for something, add it in the same way
as any other configuration file entries. This is explained further in the
developer documentation for configuration.
