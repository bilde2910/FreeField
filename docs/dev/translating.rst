Translating FreeField
=====================

FreeField relies on crowd-sourced translation. If you speak another language
than English natively, you are very welcome to contribute your language to the
project.

Getting started
---------------

FreeField localization files are stored in `this folder
<https://github.com/bilde2910/FreeField/tree/master/includes/i18n>`_. Make a
copy of en-US.ini and rename the copy in the format ``<ISO 639-1>-<ISO
3166>.ini`` (i.e. language + country code) - for example, "fr-FR.ini" for French
(France) or "es-ES.ini" for Spanish (Spain). Open the file in a plain text
editor such as Notepad, Notepad++, Atom, Mousepad, Visual Studio Code etc. to
start translating.

Language metadata
-----------------

At the top of the file, there is a metadata section for briefly describing your
language. Enter the full name and region of your target language in both English
and your language in the respective fields.

String structure
----------------

Each string to be translated in the file is of the form ``token = "String"``.
Translate the string between the quotes. Do not attempt to translate the token.
Comments start with a semicolon ``;`` and will aid you in translating certain
text. Do not translate these comments.

.. note:: If any string should contain a double-quotes sign ``"``, this must be
          escaped using a backslash ``\`` preceding it. This means that if your
          string is e.g. ``This is a "word"``, it must be written as ``This is a
          \"word\"``.

Substitution tokens
^^^^^^^^^^^^^^^^^^^

Strings may contain substitution tokens. These look like this: ``{%1}``. Do not
remove these or translate them. Those tags will be replaced with another value
later, and you should translate the rest of the string in such a way that the
translation makes sense with the substituted values. For example:

.. code-block:: ini

   ; %1 = Authentication provider (e.g. "Discord", "Telegram", etc.)
   login.perform = "Log in using {%1}"

In this case, ``{%1}`` is replaced with something like "Discord" or "Telegram"
when used in FreeField, meaning the final string may look like this:

   Login in using Discord

All substitution tokens are explained with examples in comments preceding the
string in question.

Questions
---------

If you have any questions about translating FreeField, do not hesistate to raise
an issue on the `issue tracker on GitHub
<https://github.com/bilde2910/FreeField/issues>`_ with your question. We value
feedback on anything that might be unclear, and your input could help us write
better documentation for others who many have the same question as you.
