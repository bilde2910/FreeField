Code style
==========

This document serves as the main authoritative style guide for contributions to
FreeField, and goes into great detail on the coding standards that all
contributors to the project should adhere to.

.. hint:: This guide is comprehensive, and may be overwhelming. If you fail to
          comprehend, or simply forget some conventions, don't be afraid to make
          submissions to the repository. If anything, take a look at the section
          on `Non-compliance`_, which describes what will happen if your code
          does not comply with the code style outlined in this document. In most
          cases, nothing bad will happen at all - in the worst case, you will be
          asked in a friendly manner to fix some specific things before your
          contribution is accepted. This guide simply exists to help you avoid
          those pitfalls.

Non-compliance
--------------

If your submission does not meet the style requirements outlined in this
document, several things can happen.

The two most important parts of this guide is **follow indentation
requirements**, and **do not have copyrighted assets in the commit history of
your contribution** if you submit pull requests. If either of those are the
case, your contribution will be rejected, and you will have to fix the
submission so that it is compliant.

If your contribution is small, it might be accepted even if it is non-compliant,
in which case the errors will be corrected by FreeField maintainers. In other
cases, you may be asked to correct it yourself, in a friendly manner.

If your contribution is significant, or has a large number of, or significant,
code style violations, you will likely be asked to correct those yourself in a
friendly manner, as the maintainers do not always have the resources to make
such corrections.

When making contributions, you will always be treated respectfully and in line
with FreeField's `Code of Conduct
<https://github.com/bilde2910/FreeField/blob/master/.github/CODE_OF_CONDUCT.md>`_.

Indentation
-----------

For **all** code files (except reStructuredText), indentation must be done with
four spaces. reStructuredText files are indented with three spaces. Code
snippets within reStructuredText documentation should be indented with four
spaces (see e.g. the source code of this file, `codestyle.rst
<https://raw.githubusercontent.com/bilde2910/FreeField/master/docs/dev/codestyle.rst>`_,
on GitHub).

Under no circumstances should tabs be used. Contributions which do not satisfy
this requirement **will always** be rejected.

You may indent a line with more than four spaces if doing so is reasonable to
improve the readability or visual structure of your code. E.g. this is okay:

.. code-block:: php

   $string = "This is a really long string that will wrap across two lines in "
           . "the source code.";

Spacing
-------

-  Segments of code that do unrelated things should be separated with blank
   lines.

-  Use one space around operators:

   .. code-block:: php

      // Do this:
      $var = 1;
      $var = 2 + 5;
      $var = ($bool ? 10 : 20);
      for ($i = 0; $i < 10; $i++)
      if ($x == $y)

      // Don't do this:
      $var=1;
      $var = 2+5;
      $var = ($bool?10:20);
      for ($i=0; $i<10; $i++)
      if ($x==$y);

-  Parentheses in block statements should be wrapped with one space on each
   side:

   .. code-block:: php

      // Use one space on either side of (true) - i.e. don't do this:
      while      (true)         {
          execute();
      }
      // Also try not to do this:
      while(true){
          execute();
      }

Code blocks
-----------

This is the accepted format for code blocks (``if``, ``for``, ``switch``, etc.):

.. code-block:: php

   while (true) {
       execute();
   }

**In detail, this means:**

-  Opening curly braces should be on the same line as the statement that opens
   it:

   .. code-block:: php

      // Don't do this:
      while (true)
      {
          execute();
      }

-  The ending curly brace should be on its own line, at the same indentation
   level as the line that starts the block.

   .. code-block:: php

      // Don't do this:
      while (true) {
          execute(); }

      // Don't do this either:
      while (true) {
          execute();
          }

-  Short form blocks are allowed if the *entire* statement is short enough to
   fit on one line. Never mix the short-form with the curly brace form.

   .. code-block:: php

      // This is okay:
      while (true) execute();

      // This is not okay:
      if ($bool) execute1();
      else {
          execute2();
      }

``switch`` statements
^^^^^^^^^^^^^^^^^^^^^

-  Use ``switch`` instead of ``if`` where reasonably possible:

   .. code-block:: php

      // Do this:
      switch ($var) {
          case 1:
              execute1();
              break;
          case 2:
              execute2();
              break;
          default:
              execute();
              break;
      }

      // .. instead of this:
      if ($var == 1) {
          execute1();
      } elseif ($var == 2) {
          execute2();
      } else {
          execute();
      }

-  ``case`` statements are indented:

   .. code-block:: php

      switch ($var) {
          case 1:
              execute();
              break;
          case 2:
              execute();
              break;
      }

Wrapping
--------

Code, including comments, should not exceed 80 characters per line. If splitting
the code or comments over multiple lines is unreasonable (for example, if the
code is already indented >50 characters), an exception can be made. In those
cases, limit the lines to 100, 120, 140 etc. characters, depending on what you
consider reasonable.

If you want to split long lines, there are several ways you do that. Here are
some examples that are used consistently in existing FreeField source code:

Splitting HTML tags with many attributes
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: html

   <input type="text"
          id="sampleInput"
          name="sampleInput"
          class="some-long-class-name another-long-class-name"
          value="This is the value of the input box">

Splitting arrays
^^^^^^^^^^^^^^^^

.. code-block:: php

   $array = array(
       "element1" => 1,
       "element2" => 2,
       "element3" => 3
   );

Splitting block statements
^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: php

   if (
       $int >= 3 &&
       substr($str1, 0, 10) !== substr($str2, 0, 10)
   ) {
       executeA();
       executeB();
   }

Splitting ternary operators
^^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: php

   $var = (
       isSomeStatementTrue()
       ? "valueIfTrue"
       : "valueIfFalse"
   );

Naming
------

-  Classes use ``CamelCase``, functions and variables use ``lowerCamelCase``.
-  All variables, functions, objects, classes, etc. must be written in **English
   only**.
-  Always substitute "Pokémon" with "species," "Pokéstop" with "POI," and "gym"
   with "arena" in all contributions. Do not reference individual Pokémon
   species by name. This does not apply to strings in the localization files,
   the documentation, and screenshots. It also does not apply to comments in the
   common-tasks.yaml, objectives.yaml and rewards.yaml files. It *does* apply to
   all comments in all other files.

Miscellaneous
-------------

-  Never use the short form opening tag ``<?``. Always use ``<?php``.
-  All strings that are displayed to the user must be internationalized using
   the ``I18N`` class. Look for examples in existing code if you're not sure.
-  Your code must run with no errors, notices, etc. using ``E_ALL``.
-  PHP code must also run under PHP 5.6. If this is not possible, raise an issue
   explaining why this is the case before you start making your contribution.
-  Do not use Composer dependencies. Feel free to raise an issue asking for
   guidance on alternatives if you want to add a contribution that requires a
   Composer dependency. Do this before you start writing your contribution.
-  Use a CDN for CSS and JavaScript libraries (e.g. CDNJS) wherever possible.
-  Files must be in UTF-8.
-  Outputted HTML must be W3C-compliant to the HTML5 standard (try e.g. `this
   validator <https://validator.w3.org/>`_).
-  **Comply with the license of FreeField.** This means that assets protected by
   copyright, including Pokémon imagery, must never be part of your
   contribution, **including in its commit history** if you are submitting a
   pull request. If your pull request contains, or has at any point contained,
   copyrighted assets for which usage in FreeField has not been granted, your
   pull request will be closed and will not be opened again, even if you remove
   the offending assets from your contribution. In such cases, re-fork the
   repository and re-add your commits without ever including the offending
   assets.
-  **Test your code** before submitting it. Broken code will be rejected.

Commenting
----------

All of the code in your contribution should be commented in such a way that
people other than yourself, who has never seen your code before, can understand
what it does.

In practice, this means that you:

-  Must describe the **purpose of each code file** in a comment at the top of
   the file. This comment must be in multi-line format ``/* ... */``, even if
   the comment only spans one line - this is for consistency.

-  Must describe the **purpose of each function and class** on lines immediately
   preceding that function/class, in multi-line format.

-  Should describe the **arguments and return values of functions** if they are
   not immediately clear/obvious.

-  Must describe the **purpose of variables** in classes and globally if they
   are not immediately clear/obvious. This must be done on separate lines
   preceding the variable(s).

-  Must explain **non-trivial/hard to understand code and algorithms** in
   detail - what it does, why it is there, an explanation of the calculations it
   performs, etc. Visualizations using ASCII-style graphics are welcome if you
   feel like making them, and if they contribute to a better understanding of
   the algorithm, but they are by no means required.

-  Should *not* comment what is obvious. E.g. if you have a ``while ($bool)`` in
   your code, you should not say "loops while $bool is true." You are welcome to
   comment on the *purpose or effects* of the code instead, if you believe it
   can promote better understanding of your code.

Style requirements for comments
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

-  Comments must always be made on lines preceding the statement it comments,
   and never shifted to the right on the same line:

   .. code-block:: php

      // This is good comment placement
      $var = true;

      $var = true;      // This is bad comment placement

-  Variable names, functions, classes, etc. in comments must be wrapped in
   backticks `````:

   .. code-block:: php

      // The variable `$foo` is passed to the `bar()` function.

-  Multi-line comments must have the starting ``/*`` and ending ``*/`` tags on
   lines by themselves.

-  Languages that do not support single-line comments ``//`` natively can
   substitute that functionality with multi-line comments. In those cases, the
   start and end tags are placed inline with the comment body, each of them
   separated by one space from the text, and the text of any subsequent lines
   indented to match the starting position of the first line (see `CSS`_ and
   `HTML`_ below for examples).

-  The body of multi-line comments must be indented once (i.e. by 4 spaces).

-  Lines in multi-line comments must not start with asterisks.

Examples
^^^^^^^^

PHP and JavaScript
""""""""""""""""""

.. code-block:: php

   /*
       This is a good multi-line comment. The opening and closing tags of the
       comment are on separate lines, and lines in the comment body are indented
       and do not start with asterisks.
   */

   /*
   This is not a good multi-line comment - the body text is not indented by four
   spaces.
   */

   /*
    * This is not a good multi-line comment - lines should not start with
    * asterisks.
    */

CSS
"""

.. code-block:: css

   /* This is an inline comment for CSS. CSS does not support single-line
      comments, so an inline style of multi-line comments is used as a
      substitution. */

   /* This is not a good CSS comment. Subsequent lines for this comment are not
   indented properly. */

   /*
       For multi-line comment blocks, the same comment style is used as for PHP
       and JavaScript.
   */

HTML
""""

.. code-block:: html

   <!--
       Multi-line comment blocks follow the same style as CSS, PHP and
       JavaScript.
   -->

   <!-- Single-line comment blocks follow the same style as CSS. -->
