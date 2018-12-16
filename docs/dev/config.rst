Configuration
=============

All configuration in FreeField is stored in includes/userdata/config.json and
managed using the ``Config`` class. To import the ``Config`` class for usage in
a script, the following should be placed at the top of the script file:

.. code-block:: php

   require_once("../includes/lib/global.php");
   __require("config");

Settings are defined in the `defs.php file
<https://github.com/bilde2910/FreeField/blob/master/includes/config/defs.php>`_.
An example entry may look like this:

.. code-block:: php

   /*
       The default zoom level of the map.
   */
   "map/default/zoom" => array(
       "domain" => "map",
       "section" => "default",
       "default" => 14.0,
       "option" => new FloatOption(0.0, 20.0)
   ),

The key of the array element is the path to the setting in the configuration
file, and should be placed such that is grouped with other similar/related
settings.

To retrieve a value from a setting, use
``Config::get("path/to/setting")->value()``. Storing a setting is always handled
using the administration pages, and should **never be saved directly from
code**. For completeness of the documentation, however, it should be stated that
the function to manually save configuration is ``Config::set()``, taking, at
minimum, an argument that is an associative array of the form
``"path/to/setting" => value``. Please see commentary for the ``Config::set()``
function before you ever use this to learn more about how it works and the
meaning of arguments that are passed to it.

.. danger:: Never use ``->value()`` as part of output! See :doc:`security` for
            information on ``->valueHTML()`` and other functions that should be
            used instead for output in order to prevent security/XSS
            vulnerabilities.

Internationalization
--------------------

All setting strings are internationalized. Setting names are assigned the key
``setting.<path>.name``, and descriptions helping the user understand the
purpose of each setting are ``setting.<path>.desc``, where ``<path>`` is the
path from the key of the setting array with slashes ``/`` converted to dots
``.`` and dashes ``-`` converted to underscores ``_``. For example,
``map/default/zoom`` are assigned the internationalization tokens
``setting.map.default.zoom.name`` for its label and
``setting.map.default.zoom.desc`` for its description. Please see the docs on
internationalization for more information.

Domains and sections
--------------------

Every setting in FreeField has an associated **domain** and **section** under
which it should be displayed. The **domain** is the sub-page of the
administration pages where the setting should appear. That page is further
denominated by various headers called **sections**, which are groupings of
similar or related settings. For example, the setting "Zoom level," defining the
default zoom level when viewing the map, is organized under the "Defaults"
section of the "Map settings" domain.

Adding new domains
^^^^^^^^^^^^^^^^^^

Domains always have a label (page title) and a short description (subtitle).
These must be added to the localization files under the tokens
``admin.domain.<domain>.name`` and ``admin.domain.<domain>.desc`` respectively.

There are two types of domains; **standard** and **custom** domains. Standard
domains have all their settings stored in the configuration definitions file
defs.php, and will render these on the page. Custom domains are not from
defs.php, but are instead rendered from custom output files
`includes/admin/<domain>.php
<https://github.com/bilde2910/FreeField/tree/master/includes/admin>`_. For
example. the ``users`` domain does not refer to or host any settings in
defs.php, but instead renders a list of all users of the FreeField instance in
the database. This logic is handled in `users.php
<https://github.com/bilde2910/FreeField/blob/master/includes/admin/users.php>`_,
which is ``include``'d to the administration page upon page load. If you are
adding a new domain for usage wthin defs.php, use the standard domain type
rather than custom domain.

The domain you are adding must be listed in the ``Config::listDomains()``
function. An example of such an entry:

.. code-block:: php

   // Map provider settings (e.g. map API keys)
   "map" => array(
       "icon" => "map",
       "custom-handler" => false
   ),

``custom-handler`` should be set to ``true`` if you are adding a custom page, or
``false`` if you are adding a standard domain. ``map`` corresponds to a
FontAwesome icon that should represent the domain the administration pages
sidebar.

When adding a new domain, you must also add a domain access permission setting
to defs.php to restrict access to the domain by default. This permission looks
like this:

.. code-block:: php

   "permissions/level/admin/{$domain}/general" => array(
       "domain" => "perms",
       "section" => "admin",
       "default" => PermissionOption::LEVEL_ADMIN,
       "option" => new PermissionOption()
   ),

Make sure to select a default permission level that is appropriate to the types
of settings you are adding.

.. tip:: `This commit
         <https://github.com/bilde2910/FreeField/commit/39c34b7a526bdc7fb75e1b7473998053c20d2ceb>`_
         contains an example of the "Mobile" domain being added to FreeField,
         along with its associated functionality and settings. You can use this
         as a rough template on how to implement a new domain.

Adding new sections
^^^^^^^^^^^^^^^^^^^

Like domains, sections always have a label (section header), though a
description is optional. Labels use the internationalization token
``admin.section.<domain>.<section>.name`` where ``<domain>`` is the parent
domain of the section.

.. note:: Sections are not added to custom-type domains - they are declared
          directly within the includes/admin/<domain>.php output file, but they
          should still follow the general internationalization conventions as
          other settings.

A section will have a description if, and only if, the section has an entry in
the ``SECTIONS_WITH_DESCRIPTIONS`` array in config.php. Please see the
commentary for that array to learn how to add descriptions to sections.

When adding a new section, you must also add a section access permission setting
to defs.php to restrict access to the section by default. This permission looks
like this:

.. code-block:: php

   "permissions/level/admin/{$domain}/section/{$section}" => array(
        "domain" => "perms",
        "section" => "admin",
        "indentation" => 1,
        "default" => PermissionOption::LEVEL_ADMIN,
        "option" => new PermissionOption()
   ),

Make sure to select a default permission level that is appropriate to the types
of settings that are manageable under this section.

Options and data types
----------------------

Every setting is of a certain **option** type. Available options are declared in
`types.php
<https://github.com/bilde2910/FreeField/blob/master/includes/config/types.php>`_.
The option type declares the type of data that is stored for the setting, and
provides parsing, storage and validation functions specific to that option type.
Instructions on implementing new options are available as commentary at the top
of that file.

Available option types
^^^^^^^^^^^^^^^^^^^^^^

This is a list of all available option types in FreeField. Please add any new
options you add to this list.

``StringOption``
""""""""""""""""

For storing short strings. Can be initialized with an optional regular
expressions pattern via the constructor, which, if specified, will reject all
strings that do not match this pattern as invalid.

Valid initializers
''''''''''''''''''

.. code-block:: php

   // Accept any string:
   "option" => new StringOption()

   // Using regex to e.g. only accept strings without spaces:
   "option" => new StringOption('^[^\s]+$')

Valid defaults
''''''''''''''

Any string, matching the regex if provided.

``ParagraphOption``
"""""""""""""""""""

For storing longer strings. Can optionally be initialized with the string
``"md"`` to display a live Markdown preview.

Valid initializers
''''''''''''''''''

.. code-block:: php

   // Plain-text paragraph input:
   "option" => new ParagraphOption()

   // Paragraph input with Markdown preview:
   "option" => new ParagraphOption("md")

Valid defaults
''''''''''''''

Any string.

``PasswordOption``
""""""""""""""""""

For storing passwords and other sensitive data. Stored in encrypted form in the
configuration file to prevent data leakage from misconfigured HTTP servers.

.. admonition:: Potentially unexpected behavior

   This option cannot store the string ``oqXb_&WkMrdHtRZ_@}qBM=?WheuO6Y``. This
   string is subject to change in the future. The reason is that this string is
   returned in lieu of the actual string in the configuration page when echoed
   to the page to give the user a visual impression that it is set to an
   existing value, as it will fill the input box with black dots. If this string
   is returned from the browser, it indicates that the input was not changed by
   the user, and is thus discarded.

Valid initializers
''''''''''''''''''

.. code-block:: php

   "option" => new PasswordOption()

Valid defaults
''''''''''''''

An empty string.

``BooleanOption``
"""""""""""""""""

For storing boolean values; displayed as a checkbox with a separate label next
to it.

.. attention:: This option requires that an additional internationalization
               token is declared for the label, i.e. ``setting.<path>.<label>``.
               This string is displayed next to the checkbox.

Valid initializers
''''''''''''''''''

.. code-block:: php

   "option" => new BooleanOption()

Valid defaults
''''''''''''''

``true`` or ``false``.

``IntegerOption``
"""""""""""""""""

For storing integers. Can be initialized with optional minimum and maximum
values (both inclusive).

Valid initializers
''''''''''''''''''

.. code-block:: php

   // Accept any integer:
   "option" => new IntegerOption()

   // Accept an integer with a certain mininum value (e.g. 10):
   "option" => new IntegerOption(10)

   // Accept any integer up to a certain maximum value (e.g. 20):
   "option" => new IntegerOption(null, 20)

   // Accept any integer in a range from a minimum to a maximum value:
   "option" => new IntegerOption(10, 20)

Valid defaults
''''''''''''''

Any integer, within the range if provided.

``FloatOption``
"""""""""""""""

Similar to ``IntegerOption``, but allows storing floating-point/decimal numbers.
Can be initialized with optional minimum and maximum values (both inclusive).

Valid initializers
''''''''''''''''''

.. code-block:: php

   // Accept any number:
   "option" => new FloatOption()

   // Accept a number with a certain mininum value (e.g. 10):
   "option" => new FloatOption(10.0)

   // Accept any number up to a certain maximum value (e.g. 20):
   "option" => new FloatOption(null, 20.0)

   // Accept any number in a range from a minimum to a maximum value:
   "option" => new FloatOption(10.0, 20.0)

Valid defaults
''''''''''''''

Any floating-point/decimal number, within the range if provided.

``GeofenceOption``
""""""""""""""""""

For selecting and storing references to a particular geofence, as defined by the
user on the geofencing section of the administration pages; see
:doc:`/geofencing`.

Running ``->value()`` on settings of this option type will return a ``Geofence``
object instance, or ``null`` if set to "<none>" or invalid. See `geo.php
<https://github.com/bilde2910/FreeField/blob/master/includes/lib/geo.php>`_ for
implementation and usage details.

Valid initializers
''''''''''''''''''

.. code-block:: php

   "option" => new GeofenceOption()

Valid defaults
''''''''''''''

``null``.

``SelectOption``
""""""""""""""""

For storing one value from a list of selectable valid values. Must be
initialized with an array of items and an optional data type ("string" or
"int"; default is "string").

.. attention:: This option requires additional internationalization tokens for
               each of the options in the supplied items array, i.e.
               ``setting.<path>.option.<option>``. Internationalization can be
               suppressed by passing ``true`` to the third parameter of the
               constructor of this option, though this is strongly recommended
               against unless there is a legitimate need to have unlocalized
               elements in the selection box.

Valid initializers
''''''''''''''''''

.. code-block:: php

   // Accept any item from given list of items:
   "option" => new SelectOption(array("one", "two", "three"))

   // Specify element data type:
   "option" => new SelectOption(array(24, 48, 72), "int")

Valid defaults
''''''''''''''

Any element in the provided list of options, e.g. ``"one"`` for the first example
above, or ``72`` for the second example.

``PermissionOption``
""""""""""""""""""""

For selecting a user group; see :doc:`/permissions` for more information.
Renders as a selection box of all available groups in the FreeField
installation.

Valid initializers
''''''''''''''''''

.. code-block:: php

   "option" => new PermissionOption()

Valid defaults
''''''''''''''

Any one of the following:

.. code-block:: php

   PermissionOption::LEVEL_HOST
   PermissionOption::LEVEL_ADMIN
   PermissionOption::LEVEL_MODERATOR
   PermissionOption::LEVEL_SUBMITTER
   PermissionOption::LEVEL_REGISTERED
   PermissionOption::LEVEL_READ_ONLY
   PermissionOption::LEVEL_ANONYMOUS

``IconSetOption``
"""""""""""""""""

For selecting an installed set of :ref:`map-markers`. Renders as a selection box
of all available marker sets in the FreeField installation. An option for
"default marker set" can be added to this selection box by passing an
internationalization token as argument to the constructor to indicate a string
that should be displayed to label the default option. If this is not passed, no
default option is provided to the user.

A preview box is displayed for the selected icon set at all times. If a default
option is selected, no preview is displayed, and an empty string will be
returned from this option type.

Valid initializers
''''''''''''''''''

.. code-block:: php

   // Standard marker set selection box with no "Default" option:
   "option" => new IconSetOption()

   // Selection box with a default option denoted by an I18N display label:
   "option" => new IconSetOption("setting.path_to_setting.option.default")

Valid defaults
''''''''''''''

A globally available icon set, i.e. only ``"freefield-3d-compass"`` is currently
permitted.

``FileOption``
""""""""""""""

For uploading files to FreeField as part of the configuration. Used for e.g. the
favicon. Uploaded files are stored in includes/userdata/files. The path of the
setting this option is used for must be passed as the first argument. An
optional array of file types and extensions can be passed, along with a maximum
file size.

Running ``->value()`` on settings of this option type will return a
``FileOptionValue`` object instance. This class is declared in types.php and has
the following methods:

.. code-block:: php

   // The following basic file-I/O functions exist:
   getExtension()             // Returns e.g. ".jpg"
   getFilename()              // Returns local filename, e.g. "path.to.setting.png"
   getUploadName()            // Returns origin filename, e.g. "My awesome image.png"
   getPath()                  // Returns local file path, e.g. "/var/html/path.to.setting.png"
   getMimeType()              // Returns MIME type, e.g. "image/png"
   getLength()                // Returns file size in bytes
   getUploadTime()            // Returns UNIX timestamp of time and date file was last changed

   // In addition, the following functions exist to provide file integrity:
   getHexEncodedSHA256()      // Returns hexadecimal-encoded SHA256 hash of file
   getBase64EncodedSHA256()   // Returns base64-encoded SHA256 hash of file

   // Finally, the following functions exist to read the file:
   outputWithCaching()        // Sets caching headers, echoes file, then terminates
   getDataURI()               // Returns file as a base64-encoded data URI


Valid initializers
''''''''''''''''''

.. code-block:: php

   // Accept any file
   "path/to/setting" => array(
       /* ... other fields ... */
       "option" => new FileOption(
           "path/to/setting"
       )
   ),

   // Accept only image files:
   "path/to/setting" => array(
       /* ... other fields ... */
       "option" => new FileOption(
           "path/to/setting",
           array(
               // This array is of format MIME type => default file extension.
               "image/png" => "png",
               "image/gif" => "gif",
               "image/jpeg" => "jpg"
           )
       )
   ),

   // Accept any file up to 256 KiB:
   "path/to/setting" => array(
       /* ... other fields ... */
       "option" => new FileOption(
           "path/to/setting",
           null,
           256 * 1024
       )
   ),

   // Accept only image files, and only up to 256 KiB:
   "path/to/setting" => array(
       /* ... other fields ... */
       "option" => new FileOption(
           "path/to/setting",
           array(
               // This array is of format MIME type => default file extension.
               "image/png" => "png",
               "image/gif" => "gif",
               "image/jpeg" => "jpg"
           ), 256 * 1024 // Max 256 KiB
       )
   ),

Valid defaults
''''''''''''''

An array of the following format:

.. code-block:: php

   "default" => array(
        "type"   => "image/png",
        "name"   => "default-file-name.png",
        "size"   => 2044,
        "sha256" => "0a330b612466ea389359db56ce93f2a5faaa89359087926335c7bcab45b539e4"
   )

The default file must be placed in `this directory
<https://github.com/bilde2910/FreeField/tree/master/includes/setup/templates/files>`_.
The filename must match the setting path with slashes ``/`` converted to dots
``.``. The size and SHA-256 hash of the file must be included in the ``default``
array as indicated in the example above.

``ColorOption``
"""""""""""""""

For selecting and storing an RGB color value. Displayed as a color picker, with
indicators for the current values of the red, green and blue color channels for
the selected color.

Valid initializers
''''''''''''''''''

.. code-block:: php

   "option" => new ColorOption()

Valid defaults
''''''''''''''

A hexadecimal color code string in the format ``#rrggbb``.
