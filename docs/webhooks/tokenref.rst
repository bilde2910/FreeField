Substitution token reference
============================

This is a list of all payload substitution tokens available and implemented in
FreeField. For information on implementing them into your payloads, please see
:ref:`sub-token-overview` on the main webhooks documentation page.

.. |br| raw:: html

   <br />

Pokéstop information
--------------------

These substitution tokens provide information about the Pokéstop on which field
research was reported.

``<%POI%>``
   The name of the Pokéstop.

``<%LAT%>``
   The latitude of the Pokéstop.

``<%LNG%>``
   The longitude of the Pokéstop.

``<%COORDS%>``
   A coordinate pair in decimal degrees format (e.g. "42.63445°N, 87.12012°E").
   You can specify the number of decimals in each coordinate by including it in
   parentheses directly after "COORDS". For example, ``<%COORDS(4)%>`` will
   return the string "42.6344°N, 87.1201°E."

Research task information
-------------------------

These substitution tokens provide information about the research task that was
reported.

``<%OBJECTIVE%>``
   The research objective, for example "Catch 5 Pokémon."

``<%REWARD%>``
   The research reward, for example "Pokémon encounter."

You can also include information on the context of the report:

``<%REPORTER%>``
   The nickname of the user who reported the research task.

``<%TIME(format)%>``
   The exact time the report was received by FreeField. You have to specify a
   time formatting string when using this token. Replace ``format`` with a valid
   `PHP date() string <https://secure.php.net/manual/en/function.date.php>`_.
   For example, ``<%TIME(Y-m-d H:i:s)%>`` would result in a timestamp like
   "2018-10-02 15:38:55," while ``<%TIME(c)%>`` would result in something like
   "2018-10-02T15:38:55+02:00." Please refer to the aforementioned PHP manual
   for more format examples.

Navigation and links
--------------------

``<%NAVURL%>``
   Inserts a link that users can click on to get turn-based navigation to the
   Pokéstop that research was reported on. By default, this uses the default
   navigation provider as configured in the "Map settings" sections of the
   administration pages in FreeField. You can specify that you wish use one
   particular navigation provider by passing it in parentheses directly after
   "NAVURL." For example, ``<%NAVURL(bing)%>`` will override the default
   provider for navigation links, and instead create a link for navigation on
   Bing Maps. Valid navigation providers are ``bing``, ``google``, ``here``,
   ``mapquest``, ``waze`` and ``yandex``.

``<%SITEURL%>``
   Inserts a link that users can click to visit the FreeField map.

Icon set images
---------------

Some services support displaying alerts with images and/or thumbnails (Discord
is a good example of such a service). For these services, FreeField supports
passing a URL that points to an image representing the reported research
objective or reward.

``<%OBJECTIVE_ICON(format,variant)%>``
   Returns a URL to an image representing the reported field research objective.

``<%REWARD_ICON(format,variant)%>``
   Returns a URL to an image representing the reported field research reward.

Both of these tokens require a format and a variant. The format is the kind of
image that should be returned - ``vector`` or ``raster`` - and which one you
should use depends on what you will be using it for. Vectors, if present, are
generally much clearer and scale better than raster (bitmap) images, but not all
services support vector graphics. Raster images have much better compatibility,
but they do not scale as well and will start looking pixelated or blurry if
scaled too high. If you specify the ``vector`` format, but the :ref:`webhook-icon-set` you
have chosen does not offer vector variants of its icons, ``raster`` will be used
as a fallback.

The icon tokens also require a variant. This is either ``light`` or ``dark`` -
you should choose the one that fits best with the context in which the icons are
to be displayed.

.. note:: Not all icon sets have separate light and dark icons. If you use an
          icon set that uses the same graphics for both light and dark icons,
          then the icons returned by these substitution tokens will be the same
          regardless of which variant you have chosen.

Localization tokens
-------------------

If the webhook triggers an alert in a chatroom in your community, you may want
the message to contain some phrases that describe the research that was just
reported. FreeField supports substitution of localization tokens for webhooks
for this purpose. This allows FreeField to use placeholder values for various
strings that are then localized to the correct language before the webhook is
triggered.

``<%I18N(token[,arg1[,arg2...]])%>``
   Replaced by the localized value of ``token``, passing the given ``arg1..n``
   arguments to the localization function. E.g.
   ``<%I18N(webhook.report_title)%>`` is replaced with "Field research
   reported!" Arguments can be other substitution tokens, e.g.
   ``<%I18N(webhook.reported_by,<%REPORTER%>)%>`` is resolved to "Reported by,"
   followed by the nickname of the user who reported field research.

For more information on what localization tokens and arguments are, please refer
to Internationalization in the developer documentation.

.. tip:: Support for substitution of localization tokens was created in order
         for FreeField to support multiple languages out of the box for
         webhooks, by avoiding hardcoding English strings in payload presets. If
         you want to place strings like "Reported by" in your own payload, you
         could simply write out those strings in your native language directly
         in the payload, without using localization tokens, as you most likely
         won't change the language of a webhook later. Even if you do, you could
         just manually replace the strings with matching strings in the other
         language. In most cases, this is less work overall than setting up your
         webhook payloads to be completely internationalized.

Advanced report context data
----------------------------

Substitution tokens for report context data can be useful if you need to dig
deep into the internal workings of the reported research task.

.. caution:: These tokens extract data from FreeField at a much lower level than
             most other tokens, and as such, they might stop working or change
             behavior in future updates. If you use these tokens, pay particular
             attention to breaking changes announced in update changelogs when
             you update FreeField. These tokens may break even across stable
             updates.

``<%OBJECTIVE_PARAMETER(param[,index])%>`` |br| ``<%REWARD_PARAMETER(param[,index])%>``
   Returns the value of the given parameter ``param`` of the reported research
   objective or reward. Parameters can be any parameters listed in the developer
   documentation on research parameters. ``index`` is optional and used only to
   extract a particular value from the parameter data if the given parameter is
   internally represented by an array (please refer to the developer
   documentation to see if this is the case).

   -  If the requested parameter is not present in the reported research
      objective or reward, this token is substituted with an empty string.

   -  Otherwise, and if the parameter is not an array type, the value of the
      parameter is substituted in. ``index`` is ignored.

   -  If the parameter is an array type, and ``index`` is not specified, the
      substituted value will be the array joined together with commas (e.g.
      :math:`[1, 2, 3]` becomes "1,2,3").

   -  Otherwise, if ``index`` *is* specified, assuming ``index`` = :math:`n` and
      :math:`n=1` is the first item (the array is one-indexed), if there is no
      :math:`n^\text{th}` element in the parameter array (i.e. the index is out
      of bounds), an empty string is returned for the substitution.

   -  Otherwise, if there is an :math:`n^\text{th}` index in the parameters
      array, the value at that index is returned for the substitution.

   Example: ``<%OBJECTIVE_PARAMETER(type,2)%>`` returns the 2nd reported Pokémon
   type in the reported field research objective. If the reported objective is
   "Catch 5 Water- or Grass-type Pokémon," this substitution would return
   "grass." If the objective is "Catch 5 Water-type Pokémon," it would return an
   empty string since there is no 2nd reported Pokémon type. It would also
   return an empty string for objectives which do not offer the ``type``
   parameter, such as "Catch 5 Pokémon."

``<%OBJECTIVE_PARAMETER_COUNT(param)%>`` |br| ``<%REWARD_PARAMETER_COUNT(param)%>``
   Returns the number of submitted entries for the given parameter ``param`` of
   the reported research objective or reward. Parameters can be any parameters
   listed in the developer documentation on research parameters. The behavior of
   this substitution token is as follows:

   -  If the requested parameter is not present in the reported research
      objective or reward, this token is substituted by 0.

   -  Otherwise, and if the parameter is internally represented by an array type
      (please refer to the developer documentation to see if this is the case),
      this token is substituted by a number representing the size of that array.

   -  If the parameter is present and is not internally represented by an array,
      this token is substituted by 1.

   Example: ``<%OBJECTIVE_PARAMETER_COUNT(type)%>`` returns the number of
   different Pokémon types in the reported field research objective. E.g. if the
   reported objective is "Catch 5 Water- or Grass-type Pokémon," this
   substitution would return the number 2. If the objective is "Catch 5
   Water-type Pokémon," it would return 1. If it the objective does not offer
   the ``type`` parameter, such as "Catch 5 Pokémon," 0 is returned.

   Another example: ``<%REWARD_PARAMETER_COUNT(quantity)%>`` returns 1 if the
   reported field research reward has an associated quantity, E.g. if the
   reported reward is "3 Revives," this substitution token would return the
   number 1. If the reported reward does not offer a quantity, such as for
   "Pokémon encounter," 0 is returned.

Conditional substitution
------------------------

``<%IF_EQUAL(expr,value,ifTrue[,ifFalse])%>`` |br| ``<%IF_NOT_EQUAL(expr,value,ifTrue[,ifFalse])%>``
   Checks whether ``expr`` is or is not equal to ``value``. If true, the value
   of ``ifTrue`` is substituted in, otherwise, ``ifFalse`` is used. ``ifFalse``
   is optional and defaults to an empty string.

``<%IF_LESS_THAN(expr,value,ifTrue[,ifFalse])%>`` |br| ``<%IF_LESS_OR_EQUAL(expr,value,ifTrue[,ifFalse])%>`` |br| ``<%IF_GREATER_THAN(expr,value,ifTrue[,ifFalse])%>`` |br| ``<%IF_GREATER_OR_EQUAL(expr,value,ifTrue[,ifFalse])%>``
   Converts ``expr`` and ``value`` to floating-point numbers :math:`e` and
   :math:`v`, and evaluates :math:`e < v`, :math:`e \leq v`, :math:`e > v`, or
   :math:`e \geq v` depending on your selected operation. If true, the value of
   ``ifTrue`` is substituted in, otherwise, ``ifFalse`` is used. ``ifFalse`` is
   optional and defaults to an empty string.

``<%IF_EMPTY(expr,ifTrue[,ifFalse])%>`` |br| ``<%IF_NOT_EMPTY(expr,ifTrue[,ifFalse])%>``
   Short-hand for ``<%IF_EQUAL(expr,,ifTrue,ifFalse)%>``. Checks whether
   ``expr`` is or is not an empty string. If true, the value of ``ifTrue`` is
   substituted in, otherwise, ``ifFalse`` is used. ``ifFalse`` is optional and
   defaults to an empty string.

``<%FALLBACK(expr,fallback)%>``
   Short-hand for ``<%IF_NOT_EMPTY(expr,expr,fallback)%>``. Checks whether or
   not ``expr`` is an empty string. If it is not, ``expr`` is substituted in,
   otherwise, ``fallback`` is used.

String manipulation
-------------------

``<%SUBSTRING(string,start[,length])%>``
   Returns a ``length`` long substring of ``string`` starting from the character
   index ``start``. ``length`` is optional.

   -  If ``start`` is negative, the substring starts at ``start`` index of
      characters relative to the end of the string.

   -  If ``start`` is beyond the end of the string, an empty string is returned.

   -  If ``length`` is not provided, the returned substring will end at the end
      of the string rather than enforcing a particular substring length.

   -  If ``length`` is negative, the given number of characters will be cut from
      the end of the string.

``<%LENGTH(string)%>``
   Returns the length of the given ``string``.

``<%PAD_LEFT(string,length[,padString])%>`` |br| ``<%PAD_RIGHT(string,length[,padString])%>``
   Left- or right-pads the given ``string`` to the given ``length`` using
   ``padString``. If ``padString`` is not specified, " " (space) is used. E.g.
   ``<%PAD_RIGHT(TestString,15,1)%>`` will right pad "TestString" to 15
   characters using "1" as the padding string, thus returning "TestString11111."

``<%LOWERCASE(string)%>``
   Converts the given input ``string`` to lowercase.

``<%UPPERCASE(string)%>``
   Converts the given input ``string`` to uppercase.
